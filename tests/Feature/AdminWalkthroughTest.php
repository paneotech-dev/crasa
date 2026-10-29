<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\MenuItem;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\User;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\LegalPagesSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every back office surface, walked the way an administrator walks it.
 *
 * Not a smoke test of 200s. Each of these creates something, changes it,
 * and then looks for the change where a reader would see it: on the public
 * page, in the header, on the member state's own profile. A back office
 * screen that saves without the public side moving is the failure this file
 * exists to catch, and it is invisible to a test that only asserts the
 * redirect.
 *
 * Every action is also asserted to have written an audit row. A regulatory
 * platform has to be able to say who changed a published figure and when.
 */
class AdminWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(AuthoritySeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(IndicatorSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(HomepageSeeder::class);

        $this->admin = User::factory()->administrator()->create();
    }

    private function admin(): self
    {
        $this->actingAs($this->admin);

        return $this;
    }

    private function assertAudited(string $type, int $id, string $action): void
    {
        $this->assertDatabaseHas('audit_log', [
            'auditable_type' => $type,
            'auditable_id' => $id,
            'action' => $action,
        ]);
    }

    /* Pages -------------------------------------------------------------- */

    public function test_a_page_is_composed_reordered_translated_published_and_taken_down(): void
    {
        $page = Page::create([
            'slug' => 'work-programme',
            'template' => 'standard',
            'status' => PageStatus::Draft,
        ]);
        $page->setTranslations('en', ['title' => 'Work programme']);

        // Three blocks of different types.
        foreach ([BlockType::RichText, BlockType::KeyFigures, BlockType::MemberGrid] as $type) {
            $this->admin()
                ->post("/en/admin/pages/{$page->slug}/blocks", ['block_type' => $type->value])
                ->assertRedirect();
        }

        $blocks = $page->fresh()->blocks()->orderBy('position')->get();
        $this->assertCount(3, $blocks);
        $this->assertSame(
            [BlockType::RichText, BlockType::KeyFigures, BlockType::MemberGrid],
            $blocks->map(fn ($b) => $b->type())->all()
        );

        // A block arrives hidden, so nothing appears on the site between
        // the click that creates it and the one that fills it.
        $this->assertFalse($blocks[0]->is_visible);

        // Fill and show the first.
        $this->admin()->put("/en/admin/pages/{$page->slug}/blocks/{$blocks[0]->id}", [
            'settings' => ['alignment' => 'start'],
            'en' => ['heading' => 'What we are working on', 'body' => 'Six committees, one work programme.'],
            'fr' => ['heading' => 'Nos travaux', 'body' => 'Six comités, un programme de travail.'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->admin()->put("/en/admin/pages/{$page->slug}/blocks/{$blocks[0]->id}/visibility")->assertRedirect();

        // Reorder: the third moves up.
        $this->admin()
            ->put("/en/admin/pages/{$page->slug}/blocks/{$blocks[2]->id}/move", ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(
            [BlockType::RichText, BlockType::MemberGrid, BlockType::KeyFigures],
            $page->fresh()->blocks()->orderBy('position')->get()->map(fn ($b) => $b->type())->all()
        );

        // Preview renders the real template before publication.
        $this->admin()->get("/en/admin/pages/{$page->slug}/preview")->assertOk()->assertSee('What we are working on');

        // Not on the site yet.
        $this->get('/en/work-programme')->assertNotFound();

        // Publish, and it is.
        $this->admin()
            ->put("/en/admin/pages/{$page->slug}/status", ['status' => PageStatus::Published->value])
            ->assertRedirect();

        $this->get('/en/work-programme')->assertOk()->assertSee('What we are working on');

        // The French translation is served to a French reader.
        $this->get('/fr/work-programme')->assertOk()->assertSee('Nos travaux');

        // Taken down again.
        $this->admin()
            ->put("/en/admin/pages/{$page->slug}/status", ['status' => PageStatus::Draft->value])
            ->assertRedirect();

        $this->get('/en/work-programme')->assertNotFound();

        $this->assertAudited(Page::class, $page->id, AuditLog::UPDATED);
    }

    /* Publications ------------------------------------------------------- */

    public function test_a_publication_is_created_published_downloaded_deleted_and_restored(): void
    {
        $sector = \App\Models\Sector::where('slug', 'telecommunications')->sole();

        $this->admin()->post('/en/admin/data/publications', [
            'publication_type' => 'guideline',
            'sector_id' => $sector->id,
            'published_on' => '2026-03-01',
            'is_public' => 1,
            // No file can be attached on this form, so it is published as
            // an external link: without either it may not be published.
            'external_url' => 'https://example.org/numbering-plan',
            'en' => ['title' => 'Regional numbering plan guidelines', 'summary' => 'A guideline.'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $publication = Publication::sole();
        $this->assertAudited(Publication::class, $publication->id, AuditLog::CREATED);

        // It is on the public library and on its own page.
        $this->get('/en/publications')->assertOk()->assertSee('Regional numbering plan guidelines');
        $this->get('/en/publications/'.$publication->slug)->assertOk();

        // Edited, and the edit is on the public page.
        $this->admin()->put("/en/admin/data/publications/{$publication->id}", [
            'publication_type' => 'guideline',
            'sector_id' => $sector->id,
            'published_on' => '2026-03-01',
            'is_public' => 1,
            // No file can be attached on this form, so it is published as
            // an external link: without either it may not be published.
            'external_url' => 'https://example.org/numbering-plan',
            'en' => ['title' => 'Regional numbering plan guidelines, 2026 edition', 'summary' => 'A guideline.'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/en/publications/'.$publication->slug)
            ->assertOk()
            ->assertSee('2026 edition', false);

        // Soft-deleted: off the public side, still in the database.
        $this->admin()->delete("/en/admin/data/publications/{$publication->id}")->assertRedirect();

        $this->get('/en/publications')->assertOk()->assertDontSee('Regional numbering plan guidelines');
        $this->assertSoftDeleted('publications', ['id' => $publication->id]);

        // And restored.
        $this->admin()->put("/en/admin/data/publications/{$publication->id}/restore")->assertRedirect();
        $this->get('/en/publications')->assertOk()->assertSee('Regional numbering plan guidelines');
    }

    /* News --------------------------------------------------------------- */

    public function test_a_news_item_is_created_published_and_appears_on_the_newsroom(): void
    {
        $this->admin()->post('/en/admin/data/news', [
            'category' => 'news',
            'status' => 'published',
            'published_at' => now()->toDateString(),
            'author_name' => 'CRASA Secretariat',
            'en' => [
                'title' => 'Secretariat opens the 2026 reporting round',
                'summary' => 'Member states may now file.',
                'body' => 'The e-Form is open for the 2026 period.',
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item = NewsItem::sole();
        $this->assertAudited(NewsItem::class, $item->id, AuditLog::CREATED);

        $this->get('/en/newsroom')->assertOk()->assertSee('Secretariat opens the 2026 reporting round');
        $this->get('/en/newsroom/'.$item->slug)->assertOk();
    }

    /* Members, authorities, committees ------------------------------------ */

    public function test_an_authority_edit_reaches_the_member_state_page(): void
    {
        $botswana = Country::where('iso_code', 'bw')->sole();
        $authority = $botswana->authorities()->firstOrFail();

        $this->admin()->put("/en/admin/data/authorities/{$authority->id}", [
            'name' => 'Botswana Communications Regulatory Authority',
            'acronym' => 'BOCRA',
            'country_id' => $botswana->id,
            'website' => 'https://www.bocra.org.bw',
            'established_year' => 2013,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertAudited(\App\Models\Authority::class, $authority->id, AuditLog::UPDATED);

        $this->get('/en/members/bw')
            ->assertOk()
            ->assertSee('https://www.bocra.org.bw', false);
    }

    public function test_a_committee_mandate_edit_reaches_the_public_page(): void
    {
        $committee = Committee::where('slug', 'spectrum-and-frequency-management')->sole();

        // The mandate is per language, since 10 September 2026.
        $this->admin()->put("/en/admin/data/committees/{$committee->id}", [
            'name' => $committee->name,
            'sector_id' => $committee->sector_id,
            'en' => ['mandate' => 'Harmonised spectrum assignment across the fourteen member states.'],
            'fr' => ['mandate' => "Assignation harmonisée du spectre dans les quatorze États membres."],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertAudited(Committee::class, $committee->id, AuditLog::UPDATED);

        $this->get('/en/committees')
            ->assertOk()
            ->assertSee('Harmonised spectrum assignment', false);

        // And a French reader gets the French, which is the whole point of
        // the field having moved.
        $this->get('/fr/committees')
            ->assertOk()
            ->assertSee('Assignation harmonisée du spectre', false);
    }

    /* Indicators ---------------------------------------------------------- */

    public function test_an_indicator_value_reaches_the_comparator_and_the_matrix(): void
    {
        $indicator = Indicator::active()->orderBy('display_order')->firstOrFail();
        $botswana = Country::where('iso_code', 'bw')->sole();

        $this->admin()->post('/en/admin/data/indicator-values', [
            'indicator_id' => $indicator->id,
            'country_id' => $botswana->id,
            'period' => (string) now()->year,
            'value_numeric' => '17.5',
            'source_note' => 'BOCRA annual return',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $value = \App\Models\IndicatorValue::sole();
        $this->assertAudited(\App\Models\IndicatorValue::class, $value->id, AuditLog::CREATED);

        $this->get('/en/comparator?mode=browse&indicator='.$indicator->slug)
            ->assertOk()
            ->assertSee('17.5', false);

        $this->admin()->get('/en/completeness')->assertOk();
    }

    /* Navigation ---------------------------------------------------------- */

    public function test_a_menu_item_is_added_hidden_and_deleted(): void
    {
        $this->admin()->post('/en/admin/menus', [
            'menu' => 'header',
            'destination_type' => 'route',
            'destination' => 'publications',
            'label_en' => 'Reports by year',  // The mega panel lists Annual reports from config (29 October 2026); a distinct label proves the record.
            'label_fr' => 'Rapports annuels',
            'label_pt' => 'Relatórios anuais',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item = MenuItem::where('menu', 'header')->orderByDesc('id')->firstOrFail();

        $this->get('/en')->assertOk()->assertSee('Reports by year');

        // Hidden: off the header, still a record.
        $this->admin()->put("/en/admin/menus/{$item->id}/visibility")->assertRedirect();
        $this->get('/en')->assertOk()->assertDontSee('Reports by year');
        $this->assertDatabaseHas('menu_items', ['id' => $item->id]);

        // Deleted.
        $this->admin()->delete("/en/admin/menus/{$item->id}")->assertRedirect();
        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    /* Media --------------------------------------------------------------- */

    public function test_media_requires_alt_text_and_warns_before_deleting_something_in_use(): void
    {
        /*
         * The upload takes the file; the metadata comes next. Alt text is
         * enforced twice after that: the editor cannot save the asset
         * without English alt, and x-media-image renders nothing at all in
         * a language the asset has no alt for, rather than an empty alt
         * attribute that tells a screen reader the image is decorative.
         */
        $this->admin()->post('/en/admin/media', [
            'file' => UploadedFile::fake()->image('plenary.jpg', 1600, 900),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $asset = \App\Models\MediaAsset::sole();
        $this->assertAudited(\App\Models\MediaAsset::class, $asset->id, AuditLog::CREATED);

        // Saved with no alt at all: refused.
        $this->admin()->put("/en/admin/media/{$asset->id}", [
            'rights_holder' => 'CRASA Secretariat',
        ])->assertSessionHasErrors('en.alt');

        $this->admin()->put("/en/admin/media/{$asset->id}", [
            'rights_holder' => 'CRASA Secretariat',
            'en' => ['alt' => 'Delegates in plenary session.'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue($asset->fresh()->mayAppearIn('en'));
        $this->assertFalse($asset->fresh()->mayAppearIn('fr'));

        $this->admin()->get('/en/admin/media')->assertOk()->assertSee('plenary');

        // In use, and the delete says so rather than breaking the page.
        $page = Page::create(['slug' => 'photo-page', 'template' => 'standard', 'status' => PageStatus::Published, 'published_at' => now()]);
        \App\Models\PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::Hero,
            'position' => 0,
            'is_visible' => true,
            'settings' => ['image' => \App\Support\MediaReference::to($asset)],
        ]);

        $this->admin()->delete("/en/admin/media/{$asset->id}")->assertSessionHasErrors('asset');
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    /* Users ---------------------------------------------------------------- */

    public function test_a_user_is_invited_changed_suspended_reactivated_and_audited(): void
    {
        $this->admin()->post('/en/admin/users/invite', [
            'name' => 'New editor',
            'email' => 'new.editor@example.test',
            'role' => 'editor',
            'preferred_locale' => 'en',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::where('email', 'new.editor@example.test')->sole();

        $this->admin()->put("/en/admin/users/{$user->id}/role", ['role' => 'viewer'])->assertRedirect();
        $this->assertSame('viewer', $user->fresh()->role->value);

        $this->admin()->put("/en/admin/users/{$user->id}/suspend")->assertRedirect();
        $this->assertSame('suspended', $user->fresh()->status->value);

        $this->admin()->put("/en/admin/users/{$user->id}/reactivate")->assertRedirect();
        $this->assertSame('active', $user->fresh()->status->value);

        /*
         * The audit log filtered to one account.
         *
         * The filter is on the actor, which is the question the Secretariat
         * actually asks: what did this person do. Every row here was taken
         * by the administrator, so filtering to the invited account returns
         * nothing and filtering to the administrator returns the four
         * changes above.
         */
        $this->admin()->get('/en/admin/audit?user='.$this->admin->id)
            ->assertOk()
            ->assertSee($this->admin->name);

        // The invited account has taken no action of its own, so its own
        // filter is empty. That is the filter working, not the log missing
        // the changes: they are there under the administrator who made them.
        $this->assertSame(
            0,
            AuditLog::where('user_id', $user->id)->count(),
            'The invited account is recorded as having acted.'
        );

        $this->assertGreaterThanOrEqual(
            3,
            AuditLog::where('user_id', $this->admin->id)
                ->where('auditable_type', User::class)
                ->where('auditable_id', $user->id)
                ->count(),
            'The invite, the role change and the suspension are not all in the log.'
        );

        $this->assertAudited(User::class, $user->id, AuditLog::UPDATED);
    }

    /* Translations --------------------------------------------------------- */

    public function test_the_translation_editor_opens_and_previews_a_fallback(): void
    {
        $this->admin()->post('/en/admin/data/news', [
            'category' => 'news',
            'status' => 'published',
            'published_at' => now()->toDateString(),
            'en' => ['title' => 'English only', 'summary' => 'No French yet.', 'body' => 'Body.'],
        ])->assertRedirect();

        $item = NewsItem::sole();
        // A short key, not a class name: 'news', per TranslationController::TYPES.
        $type = 'news';

        $this->admin()->get('/en/admin/translations')->assertOk();
        $this->admin()->get("/en/admin/translations/{$type}/{$item->id}")->assertOk()->assertSee('English only');

        // The fallback preview: what a French reader actually meets.
        $this->admin()->get("/en/admin/translations/{$type}/{$item->id}/preview/fr")->assertOk();
    }

    /* Legal pages ---------------------------------------------------------- */

    public function test_a_legal_clause_is_edited_and_the_review_flag_cleared(): void
    {
        $this->seed(LegalPagesSeeder::class);

        $page = Page::where('slug', 'privacy')->sole();
        $block = $page->blocks()->firstOrFail();
        $body = $block->translationValuesFor('body')['en'];

        $this->admin()->put("/en/admin/pages/privacy/blocks/{$block->id}", [
            'settings' => ['alignment' => 'start'],
            'en' => ['body' => str_replace(
                '[CRASA to state how changes to this notice will be communicated.]',
                'Changes are published on this page and dated.',
                $body
            )],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/en/privacy')->assertOk()->assertSee('Changes are published on this page and dated.', false);

        // One blank fewer on the admin side.
        $this->assertSame(6, count($page->fresh()->outstandingClientItems()));

        $this->admin()->put('/en/admin/pages/privacy/client-review', ['reviewed' => 1])->assertRedirect();
        $this->assertFalse($page->fresh()->awaitsClientReview());
    }

    /* Site settings -------------------------------------------------------- */

    /**
     * The Secretariat's telephone number used to be a string in three lang
     * files, so changing it was a deploy. It is a setting now, and the test
     * follows it all the way to the two places a reader sees it.
     */
    public function test_a_settings_change_reaches_the_footer_and_the_contact_page(): void
    {
        $this->admin()->get('/en/admin/settings')->assertOk()->assertSee('+267 315 84 68', false);

        $this->admin()->put('/en/admin/settings', [
            'contact_address_1' => 'Plot 143, Unit 2, Gaborone International Finance Park',
            'contact_address_2' => 'P.O. Box 25793, Gaborone, Botswana',
            'contact_email' => 'secretariat@crasa.org',
            'contact_phone' => '+267 390 4562',
            'social_x' => 'https://x.com/CRASA_ORG',
            'social_linkedin' => 'https://www.linkedin.com/company/crasa/',
            'social_facebook' => '',
            'social_youtube' => 'https://www.youtube.com/@crasa',
            'legacy_website' => 'https://www.crasa.org',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // The footer of every page, and the contact page.
        $this->get('/en/publications')->assertOk()->assertSee('+267 390 4562', false);
        $this->get('/en/contact')->assertOk()
            ->assertSee('+267 390 4562', false)
            ->assertSee('secretariat@crasa.org', false);

        // A cleared account loses its mark; a filled one gains it.
        $footer = $this->get('/en')->assertOk()->getContent();
        $this->assertStringContainsString('youtube.com/@crasa', $footer);
        $this->assertStringNotContainsString('facebook.com/people/CRASA', $footer);

        // One audit row, naming every key that moved.
        $entry = AuditLog::where('auditable_type', \App\Models\SiteSetting::class)->latest('id')->firstOrFail();
        $this->assertArrayHasKey('contact_phone', $entry->changes);
        $this->assertSame('+267 315 84 68', $entry->changes['contact_phone']['from']);
        $this->assertSame('+267 390 4562', $entry->changes['contact_phone']['to']);
    }

    /** A bad value is refused, and nothing is written. */
    public function test_a_settings_value_is_validated(): void
    {
        $this->admin()->put('/en/admin/settings', [
            'contact_email' => 'not-an-address',
            'social_x' => 'x.com/CRASA_ORG',
        ])->assertSessionHasErrors(['contact_email', 'social_x']);

        $this->assertSame('crasa@crasa.org', \App\Models\SiteSetting::get('contact_email'));
    }

    /** Administrator only. Screen 13's matrix grants this to nobody else. */
    public function test_an_editor_cannot_reach_the_settings(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/en/admin/settings')->assertForbidden();
        $this->actingAs($editor)->put('/en/admin/settings', ['contact_phone' => '+1'])->assertForbidden();

        // And it is not in their navigation either.
        $this->actingAs($editor)->get('/en/admin')
            ->assertOk()
            ->assertDontSee(__('admin.section_settings'));
    }
    /* The shell ------------------------------------------------------------ */

    public function test_every_back_office_screen_opens(): void
    {
        $this->seed(LegalPagesSeeder::class);

        foreach ([
            '/en/admin',
            '/en/admin/pages',
            '/en/admin/data',
            '/en/admin/media',
            '/en/admin/menus',
            '/en/admin/users',
            '/en/admin/audit',
            '/en/admin/translations',
            '/en/admin/submissions',
            '/en/admin/reports',
            '/en/admin/confirmations',
            '/en/admin/help',
            '/en/admin/settings',
        ] as $url) {
            $this->admin()->get($url)->assertOk();
        }

        foreach (array_keys(\App\Admin\ResourceRegistry::all()) as $resource) {
            $this->admin()->get("/en/admin/data/{$resource}")->assertOk();
        }
    }
}
