<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Enums\PageStatus;
use App\Models\Approval;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\Approvals;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Day 3, editor flow (item 1a): editors save with "Save draft" and "Save
 * and send for approval", never a status; a status they post is dropped;
 * the message says what happens next; a change to a published item waits
 * for approval while the public page keeps the published text; the
 * moderator's badge and dashboard notice say how many items wait.
 */
class M3EditorFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $editor;

    protected User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        Notification::fake();

        $this->admin = User::factory()->administrator()->create();
        $this->editor = User::factory()->editor()->create();
        $this->moderator = User::factory()->moderator()->create();
    }

    private function publishedNews(string $title = 'Spectrum harmonisation workshop held in Gaborone'): NewsItem
    {
        $news = NewsItem::factory()->create(['status' => 'published', 'published_at' => now()->subDay()]);
        $news->setTranslations('en', ['title' => $title, 'body' => 'The published body.']);

        return $news;
    }

    private function newsForm(array $overrides = []): array
    {
        return array_replace_recursive([
            'category' => 'news',
            'en' => ['title' => 'Postal regulators meet in Lusaka', 'body' => 'Draft body.'],
        ], $overrides);
    }

    /* No status for an editor --------------------------------------------- */

    public function test_the_editor_form_has_no_status_and_shows_both_buttons(): void
    {
        $this->actingAs($this->editor)->get('/en/admin/data/news/new')->assertOk()
            ->assertDontSee('name="status"', false)
            ->assertSee(__('m3_edflow.save_draft'))->assertSee(__('m3_edflow.save_send'))
            ->assertSee('name="intent" value="draft"', false)->assertSee('name="intent" value="send"', false);

        $this->actingAs($this->editor)->get('/en/admin/data/publications/new')->assertOk()
            ->assertDontSee('name="is_public"', false)->assertSee(__('m3_edflow.save_send'));

        // An administrator keeps the status control and direct publishing.
        $this->actingAs($this->admin)->get('/en/admin/data/news/new')->assertOk()
            ->assertSee('name="status"', false)->assertDontSee(__('m3_edflow.save_send'));
    }

    public function test_a_forged_status_from_an_editor_is_ignored(): void
    {
        $this->actingAs($this->editor)->post('/en/admin/data/news', $this->newsForm(['status' => 'published', 'intent' => 'draft']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $news = NewsItem::query()->withoutGlobalScopes()->latest('id')->first();
        $this->assertSame('draft', $news->status);
        $this->assertSame(Approvals::DRAFT, Approvals::state($news));

        $this->actingAs($this->editor)->put("/en/admin/data/news/{$news->id}", $this->newsForm(['status' => 'published']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft', $news->fresh()->status);

        $publication = Publication::factory()->create(['is_public' => false]);
        $this->actingAs($this->editor)->put("/en/admin/data/publications/{$publication->id}", [
            'publication_type' => $publication->typeSlug(), 'is_public' => '1', 'external_url' => 'https://www.crasa.org/x.pdf',
            'en' => ['title' => 'Annual report'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse((bool) $publication->fresh()->is_public);
    }

    /* The two buttons and the message --------------------------------------- */

    public function test_save_draft_keeps_it_a_draft_and_says_so(): void
    {
        $this->actingAs($this->editor)->post('/en/admin/data/news', $this->newsForm(['intent' => 'draft']))
            ->assertRedirect()->assertSessionHas('status', __('m3_edflow.saved_draft'));

        $news = NewsItem::query()->withoutGlobalScopes()->latest('id')->first();
        $this->assertNull(Approvals::pending($news));
    }

    public function test_save_and_send_opens_a_request_and_says_what_happens_next(): void
    {
        $response = $this->actingAs($this->editor)->post('/en/admin/data/news', $this->newsForm(['intent' => 'send', 'note' => 'Dates checked']));
        $response->assertRedirect()->assertSessionHas('status', __('m3_edflow.sent'));

        $news = NewsItem::query()->withoutGlobalScopes()->latest('id')->first();
        $approval = Approvals::pending($news);
        $this->assertNotNull($approval);
        $this->assertSame('Dates checked', $approval->note);
        $this->assertSame('review', $news->fresh()->status);

        $this->actingAs($this->editor)->get($response->headers->get('Location'))->assertOk()
            ->assertSee(__('m3_edflow.sent'))->assertSee(__('evening.locked_notice'));
    }

    /* A published item: no gap on the public site ----------------------------- */

    public function test_editing_a_published_item_leaves_the_public_page_until_approved(): void
    {
        $news = $this->publishedNews();
        $public = "/en/newsroom/{$news->slug}";
        $this->get($public)->assertOk()->assertSee('Spectrum harmonisation workshop held in Gaborone');

        // Save draft: held, nothing public changes.
        $this->actingAs($this->editor)->put("/en/admin/data/news/{$news->id}", $this->newsForm([
            'intent' => 'draft', 'status' => 'draft',
            'en' => ['title' => 'Spectrum workshop: final communique', 'body' => 'The new body.'],
        ]))->assertRedirect()->assertSessionHas('status', __('m3_edflow.saved_revision'));

        $this->assertSame('published', $news->fresh()->status);
        $this->assertSame('Spectrum harmonisation workshop held in Gaborone', $news->fresh()->translated('title', '', 'en'));
        auth()->logout();
        $this->get($public)->assertOk()->assertSee('Spectrum harmonisation workshop held in Gaborone')->assertDontSee('final communique');

        // The editor sees the draft of the changes, not the live text.
        $this->actingAs($this->editor)->get("/en/admin/data/news/{$news->id}")->assertOk()
            ->assertSee('Spectrum workshop: final communique')->assertSee(__('m3_edflow.revision_draft'));

        // Save and send: the message the client asked for.
        $this->actingAs($this->editor)->put("/en/admin/data/news/{$news->id}", $this->newsForm([
            'intent' => 'send',
            'en' => ['title' => 'Spectrum workshop: final communique', 'body' => 'The new body.'],
        ]))->assertRedirect()->assertSessionHas('status', __('m3_edflow.sent_revision'));

        $this->assertSame('Your changes are waiting for approval; the published version stays online until a moderator approves them.', __('m3_edflow.sent_revision'));
        $this->actingAs($this->editor)->get("/en/admin/data/news/{$news->id}")->assertOk()
            ->assertSee(__('m3_edflow.revision_waiting'));

        $approval = Approvals::pending($news);
        $this->assertNotNull($approval);
        $this->assertSame(1, Approval::where('approvable_id', $news->id)->where('approvable_type', $news->getMorphClass())->count());
        $this->assertSame('published', $news->fresh()->status);
        auth()->logout();
        $this->get($public)->assertOk()->assertSee('Spectrum harmonisation workshop held in Gaborone');

        // The moderator sees the change and approves it; then the public page has it.
        $this->actingAs($this->moderator)->get("/en/admin/approvals/{$approval->id}")->assertOk()
            ->assertSee('Spectrum workshop: final communique')->assertSee(__('m3_edflow.moderator_revision'))
            ->assertSee('Spectrum harmonisation workshop held in Gaborone');
        $this->actingAs($this->moderator)->put("/en/admin/approvals/{$approval->id}/approve")
            ->assertRedirect()->assertSessionHasNoErrors();

        auth()->logout();
        $this->get($public)->assertOk()->assertSee('Spectrum workshop: final communique')->assertSee('The new body.');
        $this->assertSame('published', $news->fresh()->status);
        $this->assertDatabaseHas('audit_log', ['action' => 'revision_applied', 'auditable_id' => $news->id]);
    }

    public function test_a_returned_change_leaves_the_published_version_and_reopens_for_the_editor(): void
    {
        $news = $this->publishedNews();

        $this->actingAs($this->editor)->put("/en/admin/data/news/{$news->id}", $this->newsForm([
            'intent' => 'send', 'en' => ['title' => 'A rushed title'],
        ]))->assertSessionHasNoErrors();

        $approval = Approvals::pending($news);
        $this->actingAs($this->moderator)->put("/en/admin/approvals/{$approval->id}/return", ['comment' => 'Title needs the date'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Spectrum harmonisation workshop held in Gaborone', $news->fresh()->translated('title', '', 'en'));
        $this->assertSame('published', $news->fresh()->status);
        $this->actingAs($this->editor)->get("/en/admin/data/news/{$news->id}")->assertOk()
            ->assertSee('A rushed title')->assertSee('Title needs the date')->assertSee(__('m3_edflow.revision_returned'));
    }

    public function test_the_four_eyes_rule_holds_for_a_held_change(): void
    {
        $news = $this->publishedNews();
        $this->actingAs($this->editor)->put("/en/admin/data/news/{$news->id}", $this->newsForm([
            'intent' => 'send', 'en' => ['title' => 'Editor change'],
        ]))->assertSessionHasNoErrors();

        $approval = Approvals::pending($news);
        $this->assertFalse(Approvals::mayDecide($this->editor, $approval));
        $this->actingAs($this->editor)->put("/en/admin/approvals/{$approval->id}/approve")->assertForbidden();
        $this->assertSame('Spectrum harmonisation workshop held in Gaborone', $news->fresh()->translated('title', '', 'en'));
        $this->assertTrue(Approvals::mayDecide($this->moderator, $approval));
    }

    public function test_an_administrator_still_edits_a_published_item_directly(): void
    {
        $news = $this->publishedNews();

        $this->actingAs($this->admin)->put("/en/admin/data/news/{$news->id}", $this->newsForm([
            'status' => 'published', 'en' => ['title' => 'Corrected by the administrator'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Corrected by the administrator', $news->fresh()->translated('title', '', 'en'));
        $this->assertNull(Approvals::latest($news));
    }

    /* Social posts ------------------------------------------------------------ */

    public function test_a_published_social_post_keeps_its_caption_until_approved(): void
    {
        $post = SocialPost::create([
            'network' => 'linkedin', 'url' => 'https://www.linkedin.com/feed/update/urn:li:activity:7000000000000000002/',
            'posted_at' => now()->subDay(), 'caption' => 'Spectrum meeting in Maputo', 'status' => 'published', 'published_at' => now()->subDay(),
            'author_id' => $this->editor->id,
        ]);

        $this->actingAs($this->editor)->get("/en/admin/social-posts/{$post->id}")->assertOk()
            ->assertSee(__('m3_edflow.save_draft'))->assertSee(__('m3_edflow.save_send'))->assertDontSee(__('fb.publish'));

        $this->actingAs($this->editor)->put("/en/admin/social-posts/{$post->id}", [
            'url' => $post->url, 'caption' => 'Spectrum meeting in Maputo, communique', 'status' => 'draft', 'intent' => 'send',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', __('m3_edflow.sent_revision'));

        $this->assertSame('Spectrum meeting in Maputo', $post->fresh()->caption);
        $this->assertSame('published', $post->fresh()->status);

        $this->actingAs($this->moderator)->put('/en/admin/approvals/'.Approvals::pending($post)->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame('Spectrum meeting in Maputo, communique', $post->fresh()->caption);
        $this->assertSame('published', $post->fresh()->status);
    }

    /* Events ------------------------------------------------------------------ */

    public function test_an_editor_event_is_not_public_until_approved_and_a_public_one_is_held(): void
    {
        $this->actingAs($this->editor)->get('/en/admin/meetings/new')->assertOk()
            ->assertDontSee('<option value="public"', false)->assertSee(__('m3_edflow.save_send'));

        $meeting = Meeting::factory()->create(['title' => 'SPECTRUM committee annual meeting', 'clearance' => Clearance::Public]);
        $this->get("/en/events/{$meeting->slug}")->assertOk()->assertSee('SPECTRUM committee annual meeting');

        $form = [
            'title' => 'SPECTRUM committee annual meeting, new venue', 'kind' => 'committee',
            'starts_on' => $meeting->starts_on->toDateString(), 'clearance' => 'public', 'intent' => 'send',
        ];
        $this->actingAs($this->editor)->put("/en/admin/meetings/{$meeting->slug}", $form)
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', __('m3_edflow.sent_revision'));

        auth()->logout();
        $this->get("/en/events/{$meeting->slug}")->assertOk()->assertSee('SPECTRUM committee annual meeting')->assertDontSee('new venue');

        $this->actingAs($this->moderator)->put('/en/admin/approvals/'.Approvals::pending($meeting)->id.'/approve')->assertSessionHasNoErrors();
        auth()->logout();
        $this->get("/en/events/{$meeting->slug}")->assertOk()->assertSee('SPECTRUM committee annual meeting, new venue');
        $this->assertSame(Clearance::Public, $meeting->fresh()->clearance);
    }

    public function test_an_event_created_public_by_an_editor_starts_members_only(): void
    {
        $this->actingAs($this->editor)->post('/en/admin/meetings', [
            'title' => 'Postal workshop', 'kind' => 'workshop', 'starts_on' => now()->addMonth()->toDateString(),
            'clearance' => 'public', 'intent' => 'draft',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', __('m3_edflow.saved_draft'));

        $this->assertSame(Clearance::Member, Meeting::where('title', 'Postal workshop')->first()->clearance);
    }

    /* Pages ------------------------------------------------------------------- */

    public function test_a_published_page_keeps_its_title_until_approved_and_the_editor_has_no_status_buttons(): void
    {
        $page = Page::create(['slug' => 'held-page', 'template' => 'standard', 'status' => PageStatus::Published, 'published_at' => now()->subDay()]);
        $page->setTranslations('en', ['title' => 'Regional roaming framework']);

        $this->actingAs($this->editor)->get('/en/admin/pages/held-page')->assertOk()
            ->assertSee(__('m3_edflow.save_send'))->assertSee(__('m3_edflow.page_status_help'))
            ->assertDontSee('name="status" value="archived"', false);
        // Refused whatever the target: 403 where publishing is involved, an explanation otherwise.
        foreach (['archived', 'draft', 'published'] as $target) {
            $refused = $this->actingAs($this->editor)->put('/en/admin/pages/held-page/status', ['status' => $target]);
            $this->assertContains($refused->status(), [302, 403, 422], $target);
        }
        $this->assertSame(PageStatus::Published, $page->fresh()->status);

        $this->actingAs($this->editor)->put('/en/admin/pages/held-page', ['en' => ['title' => 'Regional roaming framework, 2026 edition'], 'intent' => 'send'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', __('m3_edflow.sent_revision'));

        $this->assertSame('Regional roaming framework', $page->fresh()->translated('title', '', 'en'));
        $this->assertSame(PageStatus::Published, $page->fresh()->status);

        $this->actingAs($this->moderator)->put('/en/admin/approvals/'.Approvals::pending($page)->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame('Regional roaming framework, 2026 edition', $page->fresh()->translated('title', '', 'en'));
        $this->assertSame(PageStatus::Published, $page->fresh()->status);
    }

    /* Review fixes: blocks of a published page; only the differences are held -- */

    public function test_an_editor_cannot_change_the_blocks_of_a_published_page_live(): void
    {
        $page = Page::create(['slug' => 'block-page', 'template' => 'standard', 'status' => PageStatus::Published, 'published_at' => now()->subDay()]);
        $block = \App\Models\PageBlock::create(['page_id' => $page->id, 'block_type' => \App\Enums\BlockType::RichText, 'position' => 0, 'is_visible' => true, 'settings' => []]);
        $block->setTranslations('en', ['body' => 'The published paragraph.']);
        $base = '/en/admin/pages/block-page/blocks';

        $this->actingAs($this->editor)->get('/en/admin/pages/block-page')->assertOk()
            ->assertSee('data-blocks-held', false)->assertSee(__('m3_edflow.blocks_held'));

        $this->actingAs($this->editor)->put("{$base}/{$block->id}", ['settings' => [], 'en' => ['body' => 'Changed live']])
            ->assertSessionHasErrors(['approval' => __('m3_edflow.blocks_held')]);
        $this->actingAs($this->editor)->post($base, ['type' => 'rich_text'])->assertSessionHasErrors('approval');
        $this->actingAs($this->editor)->put("{$base}/{$block->id}/move", ['direction' => 'down'])->assertSessionHasErrors('approval');
        $this->actingAs($this->editor)->post("{$base}/{$block->id}/duplicate")->assertSessionHasErrors('approval');
        $this->actingAs($this->editor)->put("{$base}/{$block->id}/visibility")->assertSessionHasErrors('approval');
        $this->actingAs($this->editor)->delete("{$base}/{$block->id}")->assertSessionHasErrors('approval');

        $this->assertSame(1, $page->blocks()->count());
        $this->assertTrue((bool) $block->fresh()->is_visible);
        $this->assertSame('The published paragraph.', $block->fresh()->translated('body', '', 'en'));

        // An administrator still edits a published page's blocks directly.
        $this->actingAs($this->admin)->put("{$base}/{$block->id}/visibility")->assertSessionHasNoErrors();
        $this->assertFalse((bool) $block->fresh()->is_visible);
    }

    public function test_only_the_changed_fields_are_held_and_a_later_live_change_survives_approval(): void
    {
        $news = $this->publishedNews();
        $news->update(['author_name' => 'Secretariat']);

        $this->actingAs($this->editor)->put("/en/admin/data/news/{$news->id}", [
            'category' => $news->categorySlug(), 'published_at' => $news->published_at->toDateString(), 'author_name' => 'Secretariat',
            'en' => ['title' => 'Spectrum workshop: final communique', 'body' => 'The published body.'],
            'intent' => 'send',
        ])->assertSessionHasNoErrors();

        $approval = Approvals::pending($news);
        $this->assertSame([], $approval->revision['attributes']);
        $this->assertSame(['en' => ['title' => 'Spectrum workshop: final communique']], $approval->revision['translations']);

        // No warning while the live row is as it was.
        $this->actingAs($this->moderator)->get("/en/admin/approvals/{$approval->id}")->assertOk()
            ->assertDontSee('data-live-changed', false);

        // Somebody changes the live row after the request was sent.
        $this->travel(5)->minutes();
        $news->update(['author_name' => 'Press office']);

        $this->actingAs($this->moderator)->get("/en/admin/approvals/{$approval->id}")->assertOk()
            ->assertSee('data-live-changed', false);

        $this->actingAs($this->moderator)->put("/en/admin/approvals/{$approval->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame('Press office', $news->fresh()->author_name);
        $this->assertSame('Spectrum workshop: final communique', $news->fresh()->translated('title', '', 'en'));
        $this->assertSame('The published body.', $news->fresh()->translated('body', '', 'en'));
    }

    /* The moderator's badge and dashboard notice -------------------------------- */

    public function test_the_moderator_sees_the_badge_and_the_dashboard_notice(): void
    {
        $this->actingAs($this->moderator)->get('/en/admin')->assertOk()->assertDontSee('data-approval-inbox-notice', false);

        $this->actingAs($this->editor)->post('/en/admin/data/news', $this->newsForm(['intent' => 'send']))->assertSessionHasNoErrors();

        $this->actingAs($this->moderator)->get('/en/admin')->assertOk()
            ->assertSee('data-approval-inbox-notice', false)
            ->assertSee(__('m3_edflow.inbox_title'))
            ->assertSee(trans_choice('m3_edflow.inbox_body', 1, ['count' => 1]))
            ->assertSee(__('m3_edflow.inbox_open'))
            ->assertSee('a-side__badge a-side__badge--approvals', false);

        // The editor who sent it is not asked to decide it.
        $this->actingAs($this->editor)->get('/en/admin')->assertOk()->assertDontSee('data-approval-inbox-notice', false);
    }

    public function test_the_new_strings_exist_in_three_languages(): void
    {
        $en = require lang_path('en/m3_edflow.php');

        foreach (['fr', 'pt'] as $locale) {
            $other = require lang_path($locale.'/m3_edflow.php');
            $this->assertSame(array_keys($en), array_keys($other), $locale);

            foreach ($en as $key => $value) {
                $this->assertNotSame($value, $other[$key], $locale.'.'.$key);
            }
        }
    }
}
