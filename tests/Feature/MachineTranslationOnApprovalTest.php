<?php

namespace Tests\Feature;

use App\Jobs\DraftMachineTranslations;
use App\Models\NewsItem;
use App\Models\Translation;
use App\Models\User;
use App\Services\MachineTranslation\DeeplTranslator;
use App\Services\MachineTranslation\MachineDrafts;
use App\Services\MachineTranslation\NoTranslator;
use App\Services\MachineTranslation\Translator;
use App\Support\Approvals;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * Item 1b: approving an item in one language drafts the other two by
 * machine, flagged as machine drafts, shown with the notice on the public
 * page and listed for review in the back office. A translation a person
 * wrote is never overwritten.
 */
class MachineTranslationOnApprovalTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    private User $admin;

    private User $editor;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        Notification::fake();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seedComposedPages();

        $this->admin = User::factory()->administrator()->create();
        $this->editor = User::factory()->editor()->create();
        $this->moderator = User::factory()->moderator()->create();
    }

    /** A stand-in service: prefixes the target language, records each call. */
    private function fakeTranslator(): Translator
    {
        $fake = new class implements Translator
        {
            public array $calls = [];

            public function name(): string
            {
                return 'fake';
            }

            public function available(): bool
            {
                return true;
            }

            public function translate(string $text, string $from, string $to, bool $html = false): ?string
            {
                $this->calls[] = [$from, $to, $text];

                return '['.$to.'] '.$text;
            }
        };

        $this->app->instance(Translator::class, $fake);

        return $fake;
    }

    private function englishOnlyNews(): NewsItem
    {
        $news = NewsItem::factory()->draft()->create(['slug' => 'spectrum-workshop-lilongwe']);
        $news->setTranslations('en', [
            'title' => 'Spectrum workshop held in Lilongwe',
            'summary' => 'Regulators met to align spectrum plans.',
            'body' => '<p>Fourteen regulators attended.</p>',
        ]);

        return $news;
    }

    private function approve(NewsItem $news): void
    {
        $approval = Approvals::submit($news, $this->editor);

        $this->actingAs($this->moderator)
            ->put("/en/admin/approvals/{$approval->id}/approve", ['comment' => ''])
            ->assertRedirect();
    }

    private function row(NewsItem $news, string $locale, string $field): ?Translation
    {
        return Translation::where('translatable_type', $news->getMorphClass())
            ->where('translatable_id', $news->id)->where('locale', $locale)->where('field', $field)->first();
    }

    public function test_approving_an_english_only_news_item_drafts_french_and_portuguese(): void
    {
        $this->fakeTranslator();
        $news = $this->englishOnlyNews();

        $this->approve($news);

        foreach (['fr', 'pt'] as $locale) {
            foreach (['title', 'summary', 'body'] as $field) {
                $row = $this->row($news, $locale, $field);
                $this->assertNotNull($row, "$locale.$field");
                $this->assertTrue($row->is_machine_draft, "$locale.$field");
                $this->assertStringStartsWith('['.$locale.'] ', $row->value);
            }
        }

        $this->assertFalse($this->row($news, 'en', 'title')->is_machine_draft);
        $this->assertDatabaseHas('audit_log', ['action' => 'machine_translation_drafted', 'auditable_id' => $news->id, 'user_id' => $this->moderator->id]);
    }

    public function test_the_drafts_are_queued_on_approval(): void
    {
        Queue::fake();
        $news = $this->englishOnlyNews();

        $this->approve($news);

        Queue::assertPushed(DraftMachineTranslations::class, fn ($job) => $job->id === $news->id && $job->approvedBy === $this->moderator->id);
    }

    public function test_public_french_and_portuguese_pages_show_the_draft_and_the_notice(): void
    {
        $this->fakeTranslator();
        $news = $this->englishOnlyNews();
        $this->approve($news);
        auth()->logout();

        $this->get('/fr/newsroom/spectrum-workshop-lilongwe')->assertOk()
            ->assertSee('[fr] Spectrum workshop held in Lilongwe')
            ->assertSee('Traduction automatique, en cours de relecture par la CRASA.')
            ->assertSee('Cette page a été traduite automatiquement');

        $this->get('/pt/newsroom/spectrum-workshop-lilongwe')->assertOk()
            ->assertSee('[pt] Spectrum workshop held in Lilongwe')
            ->assertSee('Tradução automática, em revisão pela CRASA.')
            ->assertSee('Esta página foi traduzida automaticamente');

        $this->get('/en/newsroom/spectrum-workshop-lilongwe')->assertOk()
            ->assertSee('Spectrum workshop held in Lilongwe')
            ->assertDontSee('Automatic translation, under review by CRASA.');
    }

    public function test_the_translations_screen_lists_the_drafts_with_a_link_to_the_item(): void
    {
        $this->fakeTranslator();
        $news = $this->englishOnlyNews();
        $this->approve($news);

        $this->actingAs($this->admin)->get('/en/admin/translations')->assertOk()
            ->assertSee('Machine translations to review')
            ->assertSee('data-mt-review', false);

        $this->actingAs($this->admin)->get('/en/admin/translations')
            ->assertSee('Spectrum workshop held in Lilongwe')
            ->assertSee('French, Portuguese')
            ->assertSee(route('admin.resources.edit', ['locale' => 'en', 'resource' => 'news', 'id' => $news->id]), false)
            ->assertSee(route('admin.translations.edit', ['locale' => 'en', 'type' => 'news', 'id' => $news->id]), false)
            ->assertDontSee('data-mt-unconfigured', false);

        $this->actingAs($this->admin)->get('/fr/admin/translations')->assertOk()->assertSee('Traductions automatiques à relire');
        $this->actingAs($this->admin)->get('/pt/admin/translations')->assertOk()->assertSee('Traduções automáticas para rever');
    }

    public function test_a_reviewed_draft_leaves_the_list(): void
    {
        $this->fakeTranslator();
        $news = $this->englishOnlyNews();
        $this->approve($news);

        Translation::where('translatable_id', $news->id)->where('translatable_type', $news->getMorphClass())
            ->whereIn('locale', ['fr', 'pt'])->update(['is_machine_draft' => false]);

        $this->actingAs($this->admin)->get('/en/admin/translations')->assertOk()
            ->assertDontSee('data-mt-review', false)
            ->assertSee('No machine translations wait for review.');
    }

    public function test_a_translation_written_by_a_person_is_never_overwritten(): void
    {
        $fake = $this->fakeTranslator();
        $news = $this->englishOnlyNews();
        $news->setTranslation('title', 'fr', 'Atelier sur le spectre à Lilongwe', $this->editor->id);
        Translation::create([
            'translatable_type' => $news->getMorphClass(), 'translatable_id' => $news->id,
            'locale' => 'pt', 'field' => 'title', 'value' => 'old machine draft', 'is_machine_draft' => true,
        ]);

        $this->approve($news);

        $fr = $this->row($news, 'fr', 'title');
        $this->assertSame('Atelier sur le spectre à Lilongwe', $fr->value);
        $this->assertFalse($fr->is_machine_draft);
        $this->assertSame($this->editor->id, $fr->translated_by);
        $this->assertNotContains(['en', 'fr', 'Spectrum workshop held in Lilongwe'], $fake->calls);

        // A field still in machine draft is drafted again from the approved text.
        $this->assertSame('[pt] Spectrum workshop held in Lilongwe', $this->row($news, 'pt', 'title')->value);
        $this->assertTrue($this->row($news, 'fr', 'summary')->is_machine_draft);
    }

    public function test_an_item_approved_in_french_only_is_drafted_in_english_and_portuguese(): void
    {
        $this->fakeTranslator();
        $news = NewsItem::factory()->draft()->create();
        $news->setTranslations('fr', ['title' => 'Atelier régional', 'summary' => 'Résumé', 'body' => 'Texte']);

        $this->approve($news);

        $this->assertSame('[en] Atelier régional', $this->row($news, 'en', 'title')->value);
        $this->assertTrue($this->row($news, 'en', 'title')->is_machine_draft);
        $this->assertSame('[pt] Atelier régional', $this->row($news, 'pt', 'title')->value);
    }

    public function test_without_a_configured_service_nothing_is_drafted_and_the_hook_says_so(): void
    {
        config(['machine_translation.driver' => 'none']);
        Http::fake();
        $news = $this->englishOnlyNews();

        $this->approve($news);

        $this->assertSame('published', $news->fresh()->status);
        $this->assertNull($this->row($news, 'fr', 'title'));
        $this->assertDatabaseHas('audit_log', ['action' => 'machine_translation_skipped', 'auditable_id' => $news->id]);
        Http::assertNothingSent();

        $this->actingAs($this->admin)->get('/en/admin/translations')->assertOk()
            ->assertSee('data-mt-unconfigured', false)
            ->assertSee('No machine translation service is configured');
    }

    public function test_a_failing_service_never_blocks_the_approval(): void
    {
        $this->app->instance(Translator::class, new class implements Translator
        {
            public function name(): string
            {
                return 'broken';
            }

            public function available(): bool
            {
                return true;
            }

            public function translate(string $text, string $from, string $to, bool $html = false): ?string
            {
                throw new RuntimeException('service down');
            }
        });
        $news = $this->englishOnlyNews();

        $this->approve($news);

        $this->assertSame('published', $news->fresh()->status);
        $this->assertNull($this->row($news, 'fr', 'title'));
    }

    public function test_the_deepl_driver_sends_nothing_without_a_key(): void
    {
        Http::fake();
        config(['machine_translation.driver' => 'deepl', 'machine_translation.deepl.key' => null]);

        $this->assertFalse(MachineDrafts::configured());
        $this->assertNull((new DeeplTranslator(null))->translate('Hello', 'en', 'fr'));
        Http::assertNothingSent();
    }

    public function test_the_deepl_driver_asks_for_european_portuguese_with_the_key_in_a_header(): void
    {
        Http::fake(['api-free.deepl.com/*' => Http::response(['translations' => [['text' => 'Olá']]])]);
        config(['machine_translation.driver' => 'deepl', 'machine_translation.deepl.key' => 'test-key:fx']);

        $this->assertInstanceOf(DeeplTranslator::class, MachineDrafts::translator());
        $this->assertSame('Olá', MachineDrafts::translator()->translate('<p>Hello</p>', 'en', 'pt', true));

        Http::assertSent(fn ($request) => $request->url() === 'https://api-free.deepl.com/v2/translate'
            && $request->hasHeader('Authorization', 'DeepL-Auth-Key test-key:fx')
            && $request['target_lang'] === 'PT-PT'
            && $request['source_lang'] === 'EN'
            && $request['tag_handling'] === 'html');
    }

    public function test_the_default_driver_is_none(): void
    {
        $this->assertInstanceOf(NoTranslator::class, MachineDrafts::translator());
    }

    public function test_a_member_without_the_translation_permission_cannot_open_the_review_list(): void
    {
        $member = User::factory()->memberStateRep()->create();

        $status = $this->actingAs($member)->get('/en/admin/translations')->status();
        $this->assertContains($status, [403, 404]);
    }
}
