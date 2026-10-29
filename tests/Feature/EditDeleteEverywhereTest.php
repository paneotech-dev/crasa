<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\User;
use App\Notifications\AdminActivity;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F1: delete with confirmation, menu-guarded pages, deleted users kept as "former user". */
class EditDeleteEverywhereTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->admin = User::factory()->administrator()->create();
        $this->other = User::factory()->administrator()->create();
    }

    public function test_a_page_in_a_menu_cannot_be_deleted_until_removed_from_it(): void
    {
        $page = Page::create(['slug' => 'spectrum-notes', 'template' => 'standard', 'status' => PageStatus::Published]);
        $item = MenuItem::create(['menu' => 'footer', 'destination_type' => 'page', 'destination' => 'spectrum-notes', 'is_visible' => true, 'position' => 9]);

        $this->actingAs($this->admin)->delete('/en/admin/pages/spectrum-notes')->assertSessionHasErrors('page');
        $this->assertNotSoftDeleted('pages', ['id' => $page->id]);

        $item->delete();
        $this->actingAs($this->admin)->delete('/en/admin/pages/spectrum-notes')->assertRedirect('/en/admin/pages');
        $this->assertSoftDeleted('pages', ['id' => $page->id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'deleted', 'auditable_id' => $page->id]);
    }

    public function test_editor_deletes_a_page_viewer_cannot(): void
    {
        $page = Page::create(['slug' => 'old-draft', 'template' => 'standard', 'status' => PageStatus::Draft]);
        $this->actingAs(User::factory()->viewer()->create())->delete('/en/admin/pages/old-draft')->assertForbidden();
        $this->actingAs(User::factory()->editor()->create())->delete('/en/admin/pages/old-draft')->assertRedirect();
        $this->assertSoftDeleted('pages', ['id' => $page->id]);
    }

    public function test_lists_offer_delete_with_a_confirmation_that_says_what_happens(): void
    {
        $news = NewsItem::factory()->create();
        $news->setTranslations('en', ['title' => 'Postal forum report']);
        Page::create(['slug' => 'about-test', 'template' => 'standard', 'status' => PageStatus::Draft]);

        $this->actingAs($this->admin)->get('/en/admin/data/news')->assertOk()
            ->assertSee('data-confirm="&quot;Postal forum report&quot; is taken off the site', false);
        $this->actingAs($this->admin)->get('/en/admin/pages')->assertOk()
            ->assertSee('New page')->assertSee('The page /about-test is taken off the site', false);
        $this->actingAs($this->admin)->get('/en/admin/pages/about-test')->assertOk()->assertSee('Delete this page');
    }

    public function test_announcement_delete(): void
    {
        $a = Announcement::create(['author_id' => $this->admin->id, 'slug' => 'closing-date-moved', 'status' => 'draft']);
        $a->setTranslations('en', ['title' => 'Closing date moved']);
        $this->actingAs($this->admin)->get('/en/admin/announcements')->assertOk()->assertSee('data-confirm', false);
        $this->actingAs($this->admin)->delete("/en/admin/announcements/{$a->slug}")->assertRedirect('/en/admin/announcements');
        $this->assertSoftDeleted('announcements', ['id' => $a->id]);
    }

    public function test_only_a_suspended_user_is_deleted_and_stays_in_the_audit_log_as_former_user(): void
    {
        $user = User::factory()->editor()->create(['name' => 'Jo Leaving', 'email' => 'jo@example.org']);
        AuditLog::record(AuditLog::LOGIN, null, null, $user);

        $this->actingAs($this->admin)->delete("/en/admin/users/{$user->id}")->assertSessionHasErrors('user');
        $this->assertSame('Jo Leaving', $user->fresh()->name);

        $user->update(['status' => UserStatus::Suspended]);
        $this->actingAs($this->admin)->get('/en/admin/users')->assertSee('Jo Leaving loses access for good', false)->assertSee('data-confirm', false);
        $this->actingAs($this->admin)->delete("/en/admin/users/{$user->id}")->assertRedirect('/en/admin/users');

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->anonymised_at);
        $this->assertSame('Former user', $fresh->name);
        $this->assertStringNotContainsString('jo@', $fresh->email);
        $this->assertFalse($fresh->mayUsePlatform() && $fresh->status === UserStatus::Active);
        $this->assertSame(1, AuditLog::where('user_id', $user->id)->where('action', AuditLog::LOGIN)->count());
        $this->assertDatabaseHas('audit_log', ['action' => 'deleted', 'auditable_id' => $user->id]);

        $this->actingAs($this->admin)->get('/en/admin/users')->assertDontSee('former-user-'.$user->id);
        $this->actingAs($this->admin)->get('/en/admin/audit')->assertSee('Former user');
        $this->assertSame(1, $this->other->notifications()->where('type', AdminActivity::class)->count());
    }

    public function test_media_and_menu_deletes_use_the_dialog(): void
    {
        MenuItem::create(['menu' => 'footer', 'destination_type' => 'url', 'destination' => 'https://www.crasa.org', 'is_visible' => true, 'position' => 1])
            ->setTranslation('label', 'en', 'CRASA');
        $html = $this->actingAs($this->admin)->get('/en/admin/menus?menu=footer')->assertOk()->getContent();
        $this->assertStringContainsString('data-confirm=', $html);
        $this->assertStringNotContainsString('return confirm(', $html);
    }
}
