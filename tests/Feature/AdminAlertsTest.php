<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use App\Notifications\AdminActivity;
use App\Support\AdminAlerts;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F10: the other administrators are told. */
class AdminAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $other;

    private User $third;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->admin = User::factory()->administrator()->create(['name' => 'First Admin']);
        $this->other = User::factory()->administrator()->create();
        $this->third = User::factory()->administrator()->create();
    }

    private function noticesFor(User $user): int
    {
        return $user->notifications()->where('type', AdminActivity::class)->count();
    }

    public function test_suspend_and_reactivate_tell_every_other_administrator(): void
    {
        $editor = User::factory()->editor()->create(['name' => 'Sam Editor']);

        $this->actingAs($this->admin)->put("/en/admin/users/{$editor->id}/suspend")->assertRedirect();
        $this->actingAs($this->admin)->put("/en/admin/users/{$editor->id}/reactivate")->assertRedirect();

        $this->assertSame(2, $this->noticesFor($this->other));
        $this->assertSame(2, $this->noticesFor($this->third));
        $this->assertSame(0, $this->noticesFor($this->admin));
        $this->assertSame(0, $editor->notifications()->count());
        $titles = AdminAlerts::recentFor($this->other)->pluck('title')->all();
        $this->assertContains('First Admin suspended the account of Sam Editor.', $titles);
        $this->assertContains('First Admin reactivated the account of Sam Editor.', $titles);
        $this->assertSame(4, \DB::table('held_mail')->where('status', 'held')->count());
    }

    public function test_role_change_tells_other_administrators_with_before_and_after(): void
    {
        $user = User::factory()->viewer()->create(['name' => 'Pat Viewer']);
        $this->actingAs($this->admin)->put("/en/admin/users/{$user->id}/role", ['role' => 'moderator'])->assertRedirect();

        $this->assertSame('First Admin changed the role of Pat Viewer from Viewer to Moderator.', AdminAlerts::recentFor($this->other)->first()['title']);
    }

    public function test_publishing_a_page_without_approval_tells_other_administrators(): void
    {
        Page::create(['slug' => 'urgent-notice', 'template' => 'standard', 'status' => PageStatus::Draft]);

        $this->actingAs($this->admin)->put('/en/admin/pages/urgent-notice/status', ['status' => 'published'])->assertRedirect();

        $this->assertSame(PageStatus::Published, Page::where('slug', 'urgent-notice')->first()->status);
        $this->assertSame(1, $this->noticesFor($this->other));
        $this->assertDatabaseHas('audit_log', ['action' => 'published_without_approval']);
        $this->actingAs($this->other)->get('/en/notifications')->assertOk()->assertSee('without an approval');
    }
}
