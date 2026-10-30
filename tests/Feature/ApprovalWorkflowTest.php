<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Enums\PageStatus;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\User;
use App\Notifications\AdminActivity;
use App\Notifications\ApprovalActivity;
use App\Support\Approvals;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * F2: moderated publishing. Every transition, the four-eyes rule, the
 * rules page, and the generic editor no longer publishing past it.
 */
class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $admin2;

    private User $editor;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->admin = User::factory()->administrator()->create();
        $this->admin2 = User::factory()->administrator()->create();
        $this->editor = User::factory()->editor()->create();
        $this->moderator = User::factory()->moderator()->create();
    }

    private function draftNews(): NewsItem
    {
        $news = NewsItem::factory()->draft()->create();
        $news->setTranslations('en', ['title' => 'Spectrum workshop held in Lilongwe']);

        return $news;
    }

    public function test_rules_default_to_one_approval_for_the_four_types(): void
    {
        foreach (['publications', 'news', 'events', 'pages'] as $type) {
            $this->assertTrue(Approvals::requiresApproval($type), $type);
        }
    }

    public function test_draft_to_in_review_on_send(): void
    {
        Notification::fake();
        $news = $this->draftNews();
        $this->assertSame(Approvals::DRAFT, Approvals::state($news));

        $this->actingAs($this->editor)
            ->post("/en/admin/approvals/send/news/{$news->id}", ['note' => 'Dates checked'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $news->refresh();
        $this->assertSame(Approvals::IN_REVIEW, Approvals::state($news));
        $this->assertSame('review', $news->status);
        $this->assertDatabaseHas('approvals', ['approvable_id' => $news->id, 'state' => 'in_review', 'submitted_by' => $this->editor->id, 'note' => 'Dates checked']);
        $this->assertDatabaseHas('audit_log', ['action' => 'approval_submitted', 'auditable_id' => $news->id, 'user_id' => $this->editor->id]);
        Notification::assertSentTo([$this->moderator, $this->admin], ApprovalActivity::class);
        Notification::assertNotSentTo($this->editor, ApprovalActivity::class);
    }

    public function test_in_review_to_published_on_approve(): void
    {
        Notification::fake();
        $news = $this->draftNews();
        $approval = Approvals::submit($news, $this->editor);

        $this->actingAs($this->moderator)
            ->put("/en/admin/approvals/{$approval->id}/approve", ['comment' => ''])
            ->assertRedirect('/en/admin/approvals');

        $news->refresh();
        $this->assertSame('published', $news->status);
        $this->assertNotNull($news->published_at);
        $this->assertSame(Approvals::PUBLISHED, Approvals::state($news));
        $this->assertDatabaseHas('approvals', ['id' => $approval->id, 'state' => 'approved', 'decided_by' => $this->moderator->id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'approval_approved', 'user_id' => $this->moderator->id]);
        Notification::assertSentTo($this->editor, ApprovalActivity::class, fn ($n) => $n->event === 'approval_approved');
    }

    public function test_in_review_to_returned_with_comment_then_back_to_review(): void
    {
        Notification::fake();
        $page = Page::create(['slug' => 'mandate-note', 'template' => 'standard', 'status' => PageStatus::Draft]);
        $page->setTranslations('en', ['title' => 'Mandate note']);
        $approval = Approvals::submit($page, $this->editor);
        $this->assertSame(PageStatus::Review, $page->fresh()->status);

        // A return needs a comment.
        $this->actingAs($this->moderator)
            ->put("/en/admin/approvals/{$approval->id}/return", ['comment' => ''])
            ->assertSessionHasErrors('comment');

        $this->actingAs($this->moderator)
            ->put("/en/admin/approvals/{$approval->id}/return", ['comment' => 'Add the 1997 protocol reference.'])
            ->assertRedirect();

        $page->refresh();
        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertSame(Approvals::RETURNED, Approvals::state($page));
        $this->assertDatabaseHas('audit_log', ['action' => 'approval_returned', 'user_id' => $this->moderator->id]);
        Notification::assertSentTo($this->editor, ApprovalActivity::class, fn ($n) => $n->event === 'approval_returned' && $n->comment === 'Add the 1997 protocol reference.');

        // The comment is visible to the author on the item.
        $this->actingAs($this->editor)->get('/en/admin/pages/mandate-note')
            ->assertOk()->assertSee('Add the 1997 protocol reference.')->assertSee('Returned');

        // Resubmitting opens a new request.
        Approvals::submit($page, $this->editor);
        $this->assertSame(Approvals::IN_REVIEW, Approvals::state($page->fresh()));
        $this->assertSame(2, Approval::count());
    }

    public function test_four_eyes_author_cannot_approve_own_item_even_as_administrator(): void
    {
        $news = $this->draftNews();
        $approval = Approvals::submit($news, $this->admin);

        $this->actingAs($this->admin)
            ->put("/en/admin/approvals/{$approval->id}/approve")
            ->assertSessionHasErrors('approval');
        $this->assertSame('review', $news->fresh()->status);

        $this->actingAs($this->admin)
            ->put("/en/admin/approvals/{$approval->id}/return", ['comment' => 'self'])
            ->assertSessionHasErrors('approval');

        $this->expectException(DomainException::class);
        Approvals::approve($approval->fresh(), $this->admin);
    }

    public function test_four_eyes_another_administrator_may_approve(): void
    {
        $news = $this->draftNews();
        $approval = Approvals::submit($news, $this->admin);
        Approvals::approve($approval, $this->admin2);
        $this->assertSame('published', $news->fresh()->status);
    }

    public function test_editor_cannot_approve_by_address(): void
    {
        $other = User::factory()->editor()->create();
        $approval = Approvals::submit($this->draftNews(), $other);

        $this->actingAs($this->editor)->put("/en/admin/approvals/{$approval->id}/approve")->assertForbidden();
        $this->actingAs($this->editor)->get("/en/admin/approvals/{$approval->id}")->assertForbidden();
    }

    public function test_decided_request_cannot_be_decided_twice(): void
    {
        $approval = Approvals::submit($this->draftNews(), $this->editor);
        Approvals::approve($approval, $this->moderator);

        $this->actingAs($this->admin)
            ->put("/en/admin/approvals/{$approval->id}/return", ['comment' => 'late'])
            ->assertSessionHasErrors('approval');
        $this->actingAs($this->moderator)->get("/en/admin/approvals/{$approval->id}")->assertOk()->assertDontSee('data-approve', false);
    }

    public function test_cannot_send_twice(): void
    {
        $news = $this->draftNews();
        Approvals::submit($news, $this->editor);

        $this->actingAs($this->editor)
            ->post("/en/admin/approvals/send/news/{$news->id}")
            ->assertSessionHasErrors('approval');
    }

    public function test_editor_cannot_publish_news_through_the_generic_editor(): void
    {
        $news = $this->draftNews();

        $this->actingAs($this->editor)
            ->put("/en/admin/data/news/{$news->id}", [
                'category' => 'news', 'status' => 'published', 'en' => ['title' => 'Spectrum workshop held in Lilongwe'],
            ])
            // Day 3 (editor flow): an editor has no status field; a posted one is ignored.
            ->assertSessionHasNoErrors();

        $this->assertSame('draft', $news->fresh()->status);
    }

    public function test_editor_cannot_create_news_as_published(): void
    {
        $this->actingAs($this->editor)
            ->post('/en/admin/data/news', ['category' => 'news', 'status' => 'published', 'en' => ['title' => 'Straight to live']])
            ->assertSessionHasNoErrors();
        // Day 3 (editor flow): the posted status is ignored; the item is a draft.
        $this->assertSame(0, NewsItem::query()->withoutGlobalScopes()->where('status', 'published')->count());
        $this->assertSame('draft', NewsItem::query()->withoutGlobalScopes()->latest('id')->first()->status);
    }

    public function test_editor_cannot_publish_a_page_directly(): void
    {
        Page::create(['slug' => 'direct', 'template' => 'standard', 'status' => PageStatus::Draft]);

        $this->actingAs($this->editor)->put('/en/admin/pages/direct/status', ['status' => 'published'])->assertForbidden();
        $this->assertSame(PageStatus::Draft, Page::where('slug', 'direct')->first()->status);

        // "Send for review" opens an approval.
        $this->actingAs($this->editor)->put('/en/admin/pages/direct/status', ['status' => 'review'])->assertRedirect();
        $this->assertDatabaseHas('approvals', ['state' => 'in_review', 'submitted_by' => $this->editor->id]);
    }

    public function test_publication_approval_sets_it_public(): void
    {
        $publication = Publication::factory()->create(['is_public' => false, 'external_url' => 'https://www.crasa.org/doc.pdf']);
        $approval = Approvals::submit($publication, $this->editor);
        $this->assertSame(Approvals::IN_REVIEW, Approvals::state($publication));

        Approvals::approve($approval, $this->moderator);
        $this->assertTrue($publication->fresh()->is_public);
    }

    public function test_event_approval_makes_it_public(): void
    {
        $meeting = Meeting::factory()->create(['clearance' => Clearance::Member]);
        $approval = Approvals::submit($meeting, $this->admin);
        Approvals::approve($approval, $this->moderator);

        $this->assertSame(Clearance::Public, $meeting->fresh()->clearance);
    }

    public function test_administrator_publishing_without_approval_is_audited_and_told_to_other_admins(): void
    {
        Notification::fake();
        $news = $this->draftNews();

        $this->actingAs($this->admin)
            ->put("/en/admin/data/news/{$news->id}", [
                'category' => 'news', 'status' => 'published', 'en' => ['title' => 'Spectrum workshop held in Lilongwe'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('published', $news->fresh()->status);
        $this->assertDatabaseHas('audit_log', ['action' => 'published_without_approval', 'auditable_id' => $news->id]);
        Notification::assertSentTo($this->admin2, AdminActivity::class);
        Notification::assertNotSentTo($this->admin, AdminActivity::class);
        $this->assertDatabaseHas('held_mail', ['recipient' => $this->admin2->email, 'status' => 'held']);
    }

    public function test_rule_none_lets_an_editor_publish_and_records_the_rule(): void
    {
        DB::table('approval_rules')->where('content_type', 'news')->update(['mode' => 'none']);
        $news = $this->draftNews();

        $this->actingAs($this->editor)
            ->put("/en/admin/data/news/{$news->id}", [
                'category' => 'news', 'status' => 'published', 'en' => ['title' => 'Spectrum workshop held in Lilongwe'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('published', $news->fresh()->status);
        $entry = AuditLog::where('action', 'published')->where('auditable_id', $news->id)->first();
        $this->assertSame('none', $entry->changes['rule']);
    }

    public function test_rules_page_is_for_settings_holders(): void
    {
        $this->actingAs($this->editor)->get('/en/admin/approvals/rules')->assertForbidden();
        $this->actingAs($this->admin)->get('/en/admin/approvals/rules')->assertOk();

        $this->actingAs($this->admin)->put('/en/admin/approvals/rules', ['mode' => [
            'publications' => 'one', 'news' => 'none', 'events' => 'one', 'pages' => 'one', 'social' => 'one',
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse(Approvals::requiresApproval('news'));
        $this->assertDatabaseHas('audit_log', ['action' => 'approval_rule_changed']);
    }

    public function test_inbox_and_count(): void
    {
        $news = $this->draftNews();
        Approvals::submit($news, $this->editor);
        $own = $this->draftNews();
        Approvals::submit($own, $this->admin);

        $this->assertSame(2, Approvals::awaitingCountFor($this->moderator));
        $this->assertSame(1, Approvals::awaitingCountFor($this->admin));
        $this->assertSame(0, Approvals::awaitingCountFor($this->editor));
        $this->assertSame(0, Approvals::awaitingCountFor(null));

        $this->actingAs($this->moderator)->get('/en/admin/approvals')->assertOk()->assertSee('Spectrum workshop held in Lilongwe');
        $this->actingAs($this->moderator)->get('/en/admin')->assertOk()->assertSee('Awaiting approval');
        $this->actingAs(User::factory()->viewer()->create())->get('/en/admin/approvals')->assertForbidden();
    }

    public function test_moderator_reads_but_cannot_edit_content(): void
    {
        $news = $this->draftNews();
        $this->actingAs($this->moderator)->get("/en/admin/data/news/{$news->id}")->assertOk();
        $this->actingAs($this->moderator)->put("/en/admin/data/news/{$news->id}", ['category' => 'news', 'status' => 'draft'])->assertForbidden();
        $this->actingAs($this->moderator)->post("/en/admin/approvals/send/news/{$news->id}")->assertForbidden();
    }
}
