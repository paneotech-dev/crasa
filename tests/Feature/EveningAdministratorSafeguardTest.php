<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Evening pass, verification of AP-12 and F1: the last two active
 * administrators can be neither suspended, demoted nor deleted; a suspended
 * user can be deleted and stays in the audit log as "Former user".
 */
class EveningAdministratorSafeguardTest extends TestCase
{
    use RefreshDatabase;

    private User $a;

    private User $b;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        Notification::fake();

        $this->a = User::factory()->administrator()->create();
        $this->b = User::factory()->administrator()->create();
    }

    public function test_with_two_active_administrators_neither_can_be_suspended_demoted_or_deleted(): void
    {
        $this->actingAs($this->a)->put("/en/admin/users/{$this->b->id}/suspend")->assertSessionHasErrors('user');
        $this->actingAs($this->a)->put("/en/admin/users/{$this->b->id}/role", ['role' => 'editor'])->assertSessionHasErrors('role');
        $this->actingAs($this->a)->put("/en/admin/users/{$this->b->id}/role", ['role' => 'moderator'])->assertSessionHasErrors('role');
        // Deleting needs a suspended account, which the guard above prevents.
        $this->actingAs($this->a)->delete("/en/admin/users/{$this->b->id}")->assertSessionHasErrors('user');
        // Nobody suspends their own account.
        $this->actingAs($this->a)->put("/en/admin/users/{$this->a->id}/suspend")->assertForbidden();

        $b = $this->b->fresh();
        $this->assertSame(Role::Administrator, $b->role);
        $this->assertSame(UserStatus::Active, $b->status);
        $this->assertNull($b->anonymised_at);
    }

    public function test_a_third_administrator_can_go_then_the_last_two_are_protected(): void
    {
        $c = User::factory()->administrator()->create(['name' => 'Third Admin']);
        AuditLog::record(AuditLog::LOGIN, null, null, $c);

        $this->actingAs($this->a)->put("/en/admin/users/{$c->id}/suspend")->assertSessionHasNoErrors();
        $this->assertSame(UserStatus::Suspended, $c->fresh()->status);

        // The suspended one is no longer counted: demoting it is allowed.
        $this->actingAs($this->a)->put("/en/admin/users/{$c->id}/role", ['role' => 'viewer'])->assertSessionHasNoErrors();

        $this->actingAs($this->a)->delete("/en/admin/users/{$c->id}")->assertRedirect('/en/admin/users');
        $this->assertSame('Former user', $c->fresh()->name);
        $this->assertDatabaseHas('audit_log', ['action' => AuditLog::DELETED, 'auditable_id' => $c->id]);
        $this->actingAs($this->a)->get('/en/admin/users'); // the deletion flash names the person once
        $this->actingAs($this->a)->get('/en/admin/audit')->assertOk()->assertSee('Former user')->assertDontSee('Third Admin');

        $this->actingAs($this->a)->put("/en/admin/users/{$this->b->id}/suspend")->assertSessionHasErrors('user');
    }

    public function test_reactivating_a_suspended_administrator_restores_the_count(): void
    {
        $c = User::factory()->administrator()->create(['status' => UserStatus::Suspended]);

        $this->actingAs($this->a)->put("/en/admin/users/{$this->b->id}/suspend")->assertSessionHasErrors('user');
        $this->actingAs($this->a)->put("/en/admin/users/{$c->id}/reactivate")->assertSessionHasNoErrors();
        $this->actingAs($this->a)->put("/en/admin/users/{$this->b->id}/suspend")->assertSessionHasNoErrors();
    }
}
