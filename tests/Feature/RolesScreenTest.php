<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\RoleDefinition;
use App\Models\User;
use App\Notifications\AdminActivity;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** F3: the roles screen under People. */
class RolesScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->admin = User::factory()->administrator()->create();
        User::factory()->administrator()->create();
        User::factory()->administrator()->create();
    }

    public function test_presets_are_seeded_including_moderator_and_auditor(): void
    {
        $keys = RoleDefinition::where('is_built_in', true)->pluck('key')->all();
        foreach (['administrator', 'moderator', 'editor', 'member_state_rep', 'committee_member', 'viewer', 'auditor'] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_list_shows_roles_with_user_counts(): void
    {
        User::factory()->editor()->count(2)->create();

        $this->actingAs($this->admin)->get('/en/admin/roles')->assertOk()
            ->assertSee('Moderator')->assertSee('Auditor')->assertSee('Editor');
        $this->assertSame(2, RoleDefinition::where('key', 'editor')->first()->userCount());
        $this->assertSame(3, RoleDefinition::where('key', 'administrator')->first()->userCount());
    }

    public function test_only_role_assigners_reach_the_screen(): void
    {
        foreach (['editor', 'moderator', 'auditor', 'viewer'] as $state) {
            $this->actingAs(User::factory()->{$state}()->create())->get('/en/admin/roles')->assertForbidden();
        }
    }

    public function test_copy_create_assign_and_the_grid_applies(): void
    {
        Notification::fake();
        $this->actingAs($this->admin)->get('/en/admin/roles/new?copy=editor')->assertOk()->assertSee('Copy of Editor');

        $grant = ['content.read' => 'granted', 'content.approve' => 'granted', 'content.view_published' => 'granted'];
        $this->actingAs($this->admin)->post('/en/admin/roles', [
            'name_en' => 'Reviewer', 'name_fr' => 'Relecteur', 'name_pt' => 'Revisor', 'base_role' => 'viewer', 'grant' => $grant,
        ])->assertRedirect('/en/admin/roles/reviewer');

        $role = RoleDefinition::where('key', 'reviewer')->firstOrFail();
        $this->assertFalse($role->is_built_in);
        Notification::assertSentTo(User::where('id', '!=', $this->admin->id)->get(), AdminActivity::class);

        $user = User::factory()->viewer()->create();
        $this->actingAs($this->admin)->put("/en/admin/users/{$user->id}/role", ['role' => 'reviewer'])->assertRedirect();
        $user->refresh();
        $this->assertSame($role->id, $user->custom_role_id);
        $this->assertTrue($user->hasPermission(Permission::ApproveContent));
        $this->assertFalse($user->hasPermission(Permission::ManageContent));
        $this->assertSame('Reviewer', $user->roleLabel());

        // In use: refused.
        $this->actingAs($this->admin)->delete('/en/admin/roles/reviewer')->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['key' => 'reviewer']);

        // Moved away: deletable, and audited.
        $this->actingAs($this->admin)->put("/en/admin/users/{$user->id}/role", ['role' => 'viewer']);
        $this->assertNull($user->fresh()->custom_role_id);
        $this->actingAs($this->admin)->delete('/en/admin/roles/reviewer')->assertRedirect('/en/admin/roles');
        $this->assertDatabaseMissing('roles', ['key' => 'reviewer']);
        $this->assertDatabaseHas('audit_log', ['action' => 'role_deleted']);
    }

    public function test_presets_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)->delete('/en/admin/roles/viewer')->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['key' => 'viewer']);
    }

    public function test_editing_a_preset_changes_what_its_holders_may_do(): void
    {
        $viewer = User::factory()->viewer()->create();
        $this->assertFalse($viewer->hasPermission(Permission::ReadAuditLog));

        $role = RoleDefinition::where('key', 'viewer')->first();
        $grid = $role->grid();
        $grid['audit.read'] = 'granted';

        $this->actingAs($this->admin)->put('/en/admin/roles/viewer', ['name_en' => 'Viewer', 'grant' => $grid])->assertRedirect();
        \App\Support\PermissionMatrix::flush();

        $this->assertTrue($viewer->fresh()->hasPermission(Permission::ReadAuditLog));
        $this->assertDatabaseHas('audit_log', ['action' => 'role_saved']);
    }

    public function test_administrator_preset_keeps_user_and_role_management(): void
    {
        $grid = RoleDefinition::where('key', 'administrator')->first()->grid();
        $grid['users.assign_roles'] = 'denied';

        $this->actingAs($this->admin)->put('/en/admin/roles/administrator', ['name_en' => 'Administrator', 'grant' => $grid])
            ->assertSessionHasErrors('grant');
        $this->assertTrue($this->admin->fresh()->hasPermission(Permission::AssignRoles));
    }

    public function test_name_is_required_in_words(): void
    {
        $this->actingAs($this->admin)->post('/en/admin/roles', ['name_en' => '', 'base_role' => 'viewer'])
            ->assertSessionHasErrors(['name_en' => 'Name in English is required: it is how the role appears in the users list.']);
    }

    public function test_auditor_reads_the_audit_log_and_content_lists_but_changes_nothing(): void
    {
        $auditor = User::factory()->auditor()->create();
        $this->actingAs($auditor)->get('/en/admin')->assertOk();
        $this->actingAs($auditor)->get('/en/admin/audit')->assertOk();
        $this->actingAs($auditor)->get('/en/admin/data/news')->assertOk();
        $this->actingAs($auditor)->post('/en/admin/data/news', ['category' => 'news', 'status' => 'draft'])->assertForbidden();
        $this->actingAs($auditor)->get('/en/admin/users')->assertForbidden();
        $this->assertFalse($auditor->hasPermission(Permission::ApproveContent));
    }

    public function test_two_active_administrators_must_remain(): void
    {
        $others = User::where('role', Role::Administrator->value)->where('id', '!=', $this->admin->id)->get();
        // Three administrators: one may be downgraded.
        $this->actingAs($this->admin)->put("/en/admin/users/{$others[0]->id}/role", ['role' => 'editor'])->assertSessionHasNoErrors();
        // Two left: the next is refused, as is suspending.
        $this->actingAs($this->admin)->put("/en/admin/users/{$others[1]->id}/role", ['role' => 'editor'])->assertSessionHasErrors('role');
        $this->actingAs($this->admin)->put("/en/admin/users/{$others[1]->id}/suspend")->assertSessionHasErrors('user');
        $this->assertSame(Role::Administrator, $others[1]->fresh()->role);
    }
}
