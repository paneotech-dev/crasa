<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageVersion;
use App\Models\User;
use Database\Seeders\LegalPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Page history, and the client review state that replaced the draft banner.
 *
 * A privacy notice is a document somebody may have relied on. "What did this
 * page say on the day I read it" is a question a regulator is entitled to
 * ask, and an audit log recording that a block changed cannot answer it.
 *
 * The review state is the other half. It is an administration state and no
 * public template reads it, which is the whole reason it exists: the page
 * used to announce its own draft status to every public reader.
 */
class PageVersioningTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
        $this->editor = User::factory()->editor()->create();

        $this->seed(LegalPagesSeeder::class);
    }

    private function privacy(): Page
    {
        return Page::where('slug', 'privacy')->sole();
    }

    /** The first tracked edit leaves the state before it as well as after. */
    public function test_the_first_edit_records_both_sides_of_itself(): void
    {
        $page = $this->privacy();
        $this->assertSame(0, $page->versions()->count());

        $this->actingAs($this->editor)
            ->put('/en/admin/pages/privacy', ['en' => ['title' => 'Privacy notice, revised']])
            ->assertRedirect();

        $versions = $page->versions()->reorder('version_number')->get();

        $this->assertCount(2, $versions, 'The first edit must keep the state before it.');
        $this->assertSame('Privacy notice', $versions[0]->titleFor('en'));
        $this->assertSame('Privacy notice, revised', $versions[1]->titleFor('en'));
        $this->assertSame($this->editor->id, $versions[1]->changed_by);
    }

    /** A version keeps the text, not only the fact that text changed. */
    public function test_a_version_keeps_what_the_page_said(): void
    {
        $page = $this->privacy();
        $block = $page->blocks()->firstOrFail();
        $before = $block->translationValuesFor('body')['en'];

        $this->actingAs($this->editor)
            ->put("/en/admin/pages/privacy/blocks/{$block->id}", [
                'settings' => ['alignment' => 'start'],
                'en' => ['body' => 'Replaced.'],
            ])
            ->assertRedirect();

        $versions = $page->versions()->reorder('version_number')->get();

        $this->assertSame($before, $versions[0]->blockBody(0, 'en'));
        $this->assertSame('Replaced.', $versions[1]->blockBody(0, 'en'));
    }

    /** A status change is an editorial act and is recorded as one. */
    public function test_a_status_change_is_a_version(): void
    {
        $this->actingAs($this->administrator)
            ->put('/en/admin/pages/privacy/status', ['status' => PageStatus::Draft->value])
            ->assertRedirect();

        $latest = $this->privacy()->versions()->first();

        $this->assertSame('draft', $latest->payload['status']);
        $this->assertStringContainsString('Draft', $latest->change_summary);
    }

    /** Append only. A version that can be rewritten records nothing. */
    public function test_history_cannot_be_rewritten_or_deleted(): void
    {
        $this->actingAs($this->editor)
            ->put('/en/admin/pages/privacy', ['en' => ['title' => 'Changed']])
            ->assertRedirect();

        $version = PageVersion::where('page_id', $this->privacy()->id)->firstOrFail();

        $this->expectException(RuntimeException::class);
        $version->update(['change_summary' => 'Something else']);
    }

    public function test_history_cannot_be_deleted(): void
    {
        $this->actingAs($this->editor)
            ->put('/en/admin/pages/privacy', ['en' => ['title' => 'Changed']])
            ->assertRedirect();

        $version = PageVersion::where('page_id', $this->privacy()->id)->firstOrFail();

        $this->expectException(RuntimeException::class);
        $version->delete();
    }

    /* The client review state ------------------------------------------- */

    public function test_the_review_state_shows_in_the_page_list_and_the_dashboard(): void
    {
        $this->actingAs($this->administrator)->get('/en/admin/pages')
            ->assertOk()
            ->assertSee(__('admin.col_review'))
            ->assertSee(trans_choice('admin.review_outstanding_n', 7, ['n' => 7]));

        $this->actingAs($this->administrator)->get('/en/admin')
            ->assertOk()
            // An alert at the top of the dashboard since the shell.
            ->assertSee(__('admin.review_flag_title'))
            ->assertSee('Privacy notice');
    }

    /** The blanks are named on the editor, so nobody has to hunt for them. */
    public function test_the_editor_names_what_is_outstanding(): void
    {
        $this->actingAs($this->administrator)->get('/en/admin/pages/privacy')
            ->assertOk()
            ->assertSee(__('admin.client_review'))
            ->assertSee('CRASA to set retention periods', false);
    }

    /** Marking it reviewed clears it, and records who and when. */
    public function test_an_administrator_clears_the_flag(): void
    {
        $this->actingAs($this->administrator)
            ->put('/en/admin/pages/privacy/client-review', ['reviewed' => 1])
            ->assertRedirect();

        $page = $this->privacy();

        $this->assertFalse($page->awaitsClientReview());
        $this->assertNotNull($page->client_reviewed_at);
        $this->assertSame($this->administrator->id, $page->client_reviewed_by);

        // The band names every page still waiting, so it goes only when
        // the last one is signed off.
        $this->actingAs($this->administrator)
            ->put('/en/admin/pages/terms/client-review', ['reviewed' => 1])
            ->assertRedirect();

        $this->actingAs($this->administrator)->get('/en/admin')
            ->assertOk()
            ->assertDontSee(__('admin.review_flag_title'));
    }

    /**
     * Signing off is administrator-only, and publishing is not.
     *
     * Screen 13's matrix grants publishing to editors too. Recording that
     * CRASA has approved its own legal text is a different act, so it is
     * gated on the administrator-only grant in the same matrix.
     */
    public function test_an_editor_cannot_clear_the_flag(): void
    {
        $this->actingAs($this->editor)
            ->put('/en/admin/pages/privacy/client-review', ['reviewed' => 1])
            ->assertForbidden();

        $this->assertTrue($this->privacy()->awaitsClientReview());
    }

    /**
     * The list is derived from the text. Fill a blank in and it leaves;
     * a second list kept by hand would still be naming it a year later.
     */
    public function test_filling_a_blank_in_removes_it_from_the_list(): void
    {
        $page = $this->privacy();
        $before = count($page->outstandingClientItems());

        $block = $page->blocks()->firstOrFail();
        $body = $block->translationValuesFor('body')['en'];
        $block->setTranslations('en', ['body' => preg_replace(
            '/\[CRASA to set retention periods.*?\]/su',
            'Enquiries are kept for 24 months.',
            $body
        )]);

        $page->unsetRelation('blocks');

        $this->assertSame($before - 1, count($page->fresh()->outstandingClientItems()));
    }

    /** A legal body runs past the cap that protects a headline. */
    public function test_a_legal_body_can_be_saved_from_the_editor(): void
    {
        $page = $this->privacy();
        $block = $page->blocks()->firstOrFail();
        // Trimmed: setTranslations stores the trimmed value, and a test
        // that trips on a trailing space is testing the wrong thing.
        $long = trim(str_repeat('The Secretariat keeps this record. ', 300));

        $this->assertGreaterThan(4000, mb_strlen($long));

        $this->actingAs($this->editor)
            ->put("/en/admin/pages/privacy/blocks/{$block->id}", [
                'settings' => ['alignment' => 'start'],
                'en' => ['body' => $long],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($long, $block->fresh()->translationValuesFor('body')['en']);
    }

    /** A short field keeps its cap. The exemption is the body, not the form. */
    public function test_a_heading_still_cannot_run_to_a_document(): void
    {
        $page = Page::create(['slug' => 'cta-page', 'template' => 'standard', 'status' => PageStatus::Draft]);
        $block = PageBlock::create([
            'page_id' => $page->id,
            'block_type' => BlockType::CallToAction,
            'position' => 0,
            'is_visible' => true,
            'settings' => BlockType::CallToAction->defaultSettings(),
        ]);

        $this->assertSame(4000, BlockType::CallToAction->maxLengthFor('body'));
        $this->assertSame(4000, BlockType::RichText->maxLengthFor('heading'));

        $this->actingAs($this->editor)
            ->put("/en/admin/pages/cta-page/blocks/{$block->id}", [
                'settings' => $block->settings,
                'en' => ['headline' => str_repeat('a', 4100)],
            ])
            ->assertSessionHasErrors('en.headline');
    }

    /**
     * The flag is administration only, held statically as well as on the
     * rendered page. A block template added next month could read it and no
     * request-level test would notice until a public page carried it.
     */
    public function test_no_public_template_reads_the_review_state(): void
    {
        $root = dirname(__DIR__, 2).'/resources/views';
        $admin = ['/pages/admin/', '/components/admin/', '/layouts/admin'];
        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $path = str_replace($root, '', $file->getPathname());

            foreach ($admin as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    continue 2;
                }
            }

            $source = file_get_contents($file->getPathname());

            foreach (['needs_client_review', 'awaitsClientReview', 'outstandingClientItems', 'client_reviewed_at'] as $token) {
                if (str_contains($source, $token)) {
                    $offenders[] = $path.' reads '.$token;
                }
            }
        }

        $this->assertSame([], $offenders, "The client review state reached a public template:\n".implode("\n", $offenders));
    }
}