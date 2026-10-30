<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\Permission;
use App\Models\Approval;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\RoleDefinition;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\Approvals;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Evening pass, moderation gaps: an item In review is locked; an
 * administrator withdraws it to Draft (audited); approval is a permission per
 * content type; social posts join the approval flow.
 */
class EveningModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $editor;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        Notification::fake();

        $this->admin = User::factory()->administrator()->create();
        User::factory()->administrator()->create();
        $this->editor = User::factory()->editor()->create();
        $this->moderator = User::factory()->moderator()->create();
    }

    private function newsInReview(): NewsItem
    {
        $news = NewsItem::factory()->draft()->create();
        $news->setTranslations('en', ['title' => 'Postal committee met in Windhoek']);
        Approvals::submit($news, $this->editor);

        return $news;
    }

    private function socialPost(): SocialPost
    {
        return SocialPost::create([
            'network' => 'linkedin', 'url' => 'https://www.linkedin.com/feed/update/urn:li:activity:7000000000000000001/',
            'posted_at' => now(), 'caption' => 'Spectrum meeting', 'status' => 'draft', 'author_id' => $this->editor->id,
        ]);
    }

    /* (a) Locked while In review ---------------------------------------- */

    public function test_an_item_in_review_cannot_be_saved_by_its_author(): void
    {
        $news = $this->newsInReview();

        $this->assertTrue(Approvals::isLocked($news));
        $this->assertFalse(Gate::forUser($this->editor)->allows('update', $news));

        $this->actingAs($this->editor)
            ->put("/en/admin/data/news/{$news->id}", ['category' => 'news', 'status' => 'draft', 'en' => ['title' => 'Changed behind the moderator']])
            ->assertRedirect()->assertSessionHasErrors('approval');

        $this->assertSame('Postal committee met in Windhoek', $news->fresh()->translated('title', '', 'en'));
    }

    public function test_the_editor_still_opens_it_and_sees_the_lock_and_the_moderator_reads_it(): void
    {
        $news = $this->newsInReview();
        $approval = Approvals::pending($news);

        $this->actingAs($this->editor)->get("/en/admin/data/news/{$news->id}")->assertOk()
            ->assertSee(__('evening.locked_notice'))->assertDontSee(__('evening.withdraw_button'))
            ->assertSee('<fieldset class="aw-lockset" disabled', false);
        $this->actingAs($this->moderator)->get("/en/admin/data/news/{$news->id}")->assertOk();
        $this->actingAs($this->moderator)->get("/en/admin/approvals/{$approval->id}")->assertOk()
            ->assertSee('Postal committee met in Windhoek');
    }

    public function test_a_page_in_review_refuses_detail_status_and_block_changes(): void
    {
        $page = Page::create(['slug' => 'locked-page', 'template' => 'standard', 'status' => PageStatus::Draft]);
        Approvals::submit($page, $this->editor);

        $this->actingAs($this->editor)->get('/en/admin/pages/locked-page')->assertOk();
        $this->actingAs($this->editor)->put('/en/admin/pages/locked-page', ['en' => ['title' => 'New']])->assertSessionHasErrors('approval');
        $this->actingAs($this->editor)->put('/en/admin/pages/locked-page/status', ['status' => 'draft'])->assertSessionHasErrors('approval');
        $this->actingAs($this->editor)->post('/en/admin/pages/locked-page/blocks', ['type' => 'text'])->assertSessionHasErrors('approval');

        $this->assertSame(PageStatus::Review, $page->fresh()->status);
        $this->assertNotNull(Approvals::pending($page));
    }

    public function test_an_event_in_review_refuses_changes(): void
    {
        $meeting = Meeting::factory()->create();
        Approvals::submit($meeting, $this->editor);

        $this->assertFalse(Gate::forUser($this->editor)->allows('update', $meeting));
        $this->assertTrue(Gate::forUser($this->editor)->allows('edit', $meeting));
        $this->actingAs($this->editor)->put("/en/admin/meetings/{$meeting->slug}", ['title' => 'x'])->assertSessionHasErrors('approval');
    }

    public function test_an_administrator_withdraws_to_draft_with_an_audit_entry_then_it_opens_again(): void
    {
        $news = $this->newsInReview();
        $approval = Approvals::pending($news);

        $this->actingAs($this->admin)->get("/en/admin/data/news/{$news->id}")->assertOk()->assertSee(__('evening.withdraw_button'));

        $this->actingAs($this->admin)
            ->put("/en/admin/approvals/{$approval->id}/withdraw", ['comment' => 'Dates to recheck'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(Approval::WITHDRAWN, $approval->fresh()->state);
        $this->assertSame($this->admin->id, $approval->fresh()->decided_by);
        $this->assertSame(Approvals::DRAFT, Approvals::state($news->fresh()));
        $this->assertSame('draft', $news->fresh()->status);
        $this->assertDatabaseHas('audit_log', ['action' => 'approval_withdrawn', 'auditable_id' => $news->id]);
        $this->assertFalse(Approvals::isLocked($news));

        $this->actingAs($this->editor)
            ->put("/en/admin/data/news/{$news->id}", ['category' => 'news', 'status' => 'draft', 'en' => ['title' => 'Postal committee met in Windhoek, 27 to 29 October']])
            ->assertSessionHasNoErrors();
        $this->assertSame('Postal committee met in Windhoek, 27 to 29 October', $news->fresh()->translated('title', '', 'en'));
    }

    public function test_only_administrators_withdraw(): void
    {
        $approval = Approvals::pending($this->newsInReview());

        $this->actingAs($this->editor)->put("/en/admin/approvals/{$approval->id}/withdraw")->assertForbidden();
        $this->actingAs($this->moderator)->put("/en/admin/approvals/{$approval->id}/withdraw")->assertForbidden();
        $this->assertTrue($approval->fresh()->isOpen());
    }

    public function test_after_a_return_the_author_edits_again(): void
    {
        $news = $this->newsInReview();
        Approvals::return(Approvals::pending($news), $this->moderator, 'Add the dates');

        $this->assertFalse(Approvals::isLocked($news));
        $this->assertTrue(Gate::forUser($this->editor)->allows('update', $news));
    }

    /* (b) Approval per content type -------------------------------------- */

    public function test_presets_hold_every_type_permission_so_nothing_changes(): void
    {
        foreach (Permission::approveTypes() as $permission) {
            $this->assertTrue($this->admin->hasPermission($permission), $permission->value);
            $this->assertTrue($this->moderator->hasPermission($permission), $permission->value);
            $this->assertFalse($this->editor->hasPermission($permission), $permission->value);
        }

        $this->assertTrue($this->moderator->hasPermission(Permission::ApproveContent));
        $this->assertFalse($this->editor->hasPermission(Permission::ApproveContent));
    }

    public function test_the_roles_grid_shows_one_approve_permission_per_type(): void
    {
        $html = $this->actingAs($this->admin)->get('/en/admin/roles/moderator')->assertOk()->getContent();

        foreach (['publications', 'news', 'events', 'pages', 'social'] as $type) {
            $this->assertStringContainsString('name="grant[content.approve_'.$type.']"', $html);
        }
        $this->assertStringNotContainsString('name="grant[content.approve]"', $html);
    }

    public function test_a_news_only_approver_decides_news_but_not_publications(): void
    {
        $role = RoleDefinition::create(['key' => 'news-approver', 'name_en' => 'News approver', 'base_role' => 'viewer', 'is_built_in' => false]);
        foreach (['content.read', 'content.approve_news', 'content.view_published'] as $permission) {
            \DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission, 'grant' => 'granted']);
        }
        \App\Support\PermissionMatrix::flush();
        $approver = User::factory()->viewer()->create(['custom_role_id' => $role->id]);

        $news = $this->newsInReview();
        $publication = Publication::factory()->create(['is_public' => false, 'external_url' => 'https://www.crasa.org/doc.pdf']);
        $pubApproval = Approvals::submit($publication, $this->editor);

        $this->assertSame(1, Approvals::awaitingCountFor($approver));
        $this->assertSame(2, Approvals::awaitingCountFor($this->moderator));

        $this->actingAs($approver)->put("/en/admin/approvals/{$pubApproval->id}/approve")->assertForbidden();
        $this->assertFalse($publication->fresh()->is_public);

        $this->actingAs($approver)->put('/en/admin/approvals/'.Approvals::pending($news)->id.'/approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('published', $news->fresh()->status);
    }

    public function test_the_migration_gives_custom_roles_the_grant_they_had_for_approve_content(): void
    {
        $role = RoleDefinition::create(['key' => 'old-reviewer', 'name_en' => 'Old reviewer', 'base_role' => 'viewer', 'is_built_in' => false]);
        \DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission' => 'content.approve', 'grant' => 'granted']);

        $migration = require database_path('migrations/2026_10_29_180000_approval_per_content_type.php');
        $migration->up();
        $migration->up(); // idempotent
        \App\Support\PermissionMatrix::flush();

        $this->assertSame(5, \DB::table('role_permissions')->where('role_id', $role->id)->where('permission', 'like', 'content.approve_%')->where('grant', 'granted')->count());
        $user = User::factory()->viewer()->create(['custom_role_id' => $role->id]);
        $this->assertTrue($user->hasPermission(Permission::ApprovePages));
    }

    /* (c) Social posts in the flow --------------------------------------- */

    public function test_social_posts_default_to_one_approval_and_are_on_the_rules_page(): void
    {
        $this->assertArrayHasKey('social', Approvals::TYPES);
        $this->assertTrue(Approvals::requiresApproval('social'));
        $this->actingAs($this->admin)->get('/en/admin/approvals/rules')->assertOk()
            ->assertSee('name="mode[social]"', false)->assertSee(__('fa.types_social'));
    }

    public function test_an_editor_sends_a_social_post_and_a_moderator_approves_it(): void
    {
        $post = $this->socialPost();

        $this->actingAs($this->editor)->put("/en/admin/social-posts/{$post->id}/publish")->assertForbidden();
        $this->assertSame('draft', $post->fresh()->status);

        $this->actingAs($this->editor)->get("/en/admin/social-posts/{$post->id}")->assertOk()->assertSee(__('m3_edflow.save_send'));
        $this->actingAs($this->editor)->post("/en/admin/approvals/send/social/{$post->id}")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(Approvals::IN_REVIEW, Approvals::state($post));

        // Locked while in review.
        $this->actingAs($this->editor)->put("/en/admin/social-posts/{$post->id}", ['url' => $post->url, 'caption' => 'Changed'])
            ->assertSessionHasErrors('approval');

        $approval = Approvals::pending($post);
        $this->actingAs($this->moderator)->get('/en/admin/approvals')->assertOk()->assertSee('Spectrum meeting');
        $this->actingAs($this->editor)->put("/en/admin/approvals/{$approval->id}/approve")->assertForbidden();
        $this->actingAs($this->moderator)->put("/en/admin/approvals/{$approval->id}/approve")->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('published', $post->fresh()->status);
        $this->assertDatabaseHas('audit_log', ['action' => 'approval_approved', 'auditable_id' => $post->id]);
    }

    public function test_an_administrator_publishing_a_social_post_directly_is_recorded(): void
    {
        $post = $this->socialPost();

        $this->actingAs($this->admin)->put("/en/admin/social-posts/{$post->id}/publish")->assertRedirect();
        $this->assertSame('published', $post->fresh()->status);
        $this->assertDatabaseHas('audit_log', ['action' => 'published_without_approval', 'auditable_id' => $post->id]);
    }
}
