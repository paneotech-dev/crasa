<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\RoleDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Users > Invite a user offers every assignable role, the custom roles of
 * the roles screen as well as the built-in ones, and the invited account
 * holds the role chosen.
 */
class InviteWithCustomRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_custom_role_is_offered_and_the_invited_user_holds_it(): void
    {
        config(['crasa.two_factor_required' => false]);
        Notification::fake();
        $role = RoleDefinition::create(['key' => 'workshop-observer', 'name_en' => 'Workshop observer', 'name_fr' => 'Observateur d\'atelier', 'name_pt' => 'Observador do seminário', 'base_role' => 'viewer', 'is_built_in' => false]);
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/en/admin/users/invite')->assertOk()
            ->assertSee('<option value="workshop-observer"', false)
            ->assertSee('Workshop observer')
            ->assertSee('<option value="editor"', false)
            ->assertSee('<option value="administrator"', false);
        $this->actingAs($admin)->get('/pt/admin/users/invite')->assertOk()->assertSee('Observador do seminário');

        $this->actingAs($admin)->post('/en/admin/users/invite', [
            'name' => 'Invited Observer',
            'email' => 'observer@example.test',
            'role' => 'workshop-observer',
            'preferred_locale' => 'en',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $invited = User::where('email', 'observer@example.test')->sole();
        $this->assertSame($role->id, $invited->custom_role_id);
        $this->assertSame(Role::Viewer, $invited->role);
        $this->assertSame('Workshop observer', $invited->roleLabel());
        $this->assertDatabaseHas('audit_log', ['action' => AuditLog::CREATED, 'auditable_id' => $invited->id]);

        $this->actingAs($admin)->post('/en/admin/users/invite', [
            'name' => 'Invited Editor',
            'email' => 'editor@example.test',
            'role' => 'editor',
            'preferred_locale' => 'en',
        ])->assertSessionHasNoErrors();
        $editor = User::where('email', 'editor@example.test')->sole();
        $this->assertSame(Role::Editor, $editor->role);
        $this->assertNull($editor->custom_role_id);

        $this->actingAs($admin)->post('/en/admin/users/invite', [
            'name' => 'Unknown',
            'email' => 'unknown@example.test',
            'role' => 'no-such-role',
            'preferred_locale' => 'en',
        ])->assertSessionHasErrors('role');
    }

    public function test_an_editor_cannot_invite(): void
    {
        RoleDefinition::create(['key' => 'workshop-observer', 'name_en' => 'Workshop observer', 'base_role' => 'viewer', 'is_built_in' => false]);
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/en/admin/users/invite')->assertForbidden();
        $this->actingAs($editor)->post('/en/admin/users/invite', ['name' => 'X', 'email' => 'x@example.test', 'role' => 'workshop-observer', 'preferred_locale' => 'en'])->assertForbidden();
    }

    public function test_an_inviter_without_assign_roles_may_invite_only_into_the_default_role(): void
    {
        config(['crasa.two_factor_required' => false]);
        Notification::fake();
        RoleDefinition::create(['key' => 'workshop-observer', 'name_en' => 'Workshop observer', 'base_role' => 'viewer', 'is_built_in' => false]);
        $clerk = RoleDefinition::create(['key' => 'account-clerk', 'name_en' => 'Account clerk', 'base_role' => 'viewer', 'is_built_in' => false]);
        foreach (['users.manage', 'content.view_published'] as $permission) {
            \DB::table('role_permissions')->insert(['role_id' => $clerk->id, 'permission' => $permission, 'grant' => 'granted']);
        }
        \App\Support\PermissionMatrix::flush();
        $inviter = User::factory()->viewer()->create(['custom_role_id' => $clerk->id]);
        $this->assertTrue($inviter->hasPermission(\App\Enums\Permission::ManageUsers));
        $this->assertFalse($inviter->hasPermission(\App\Enums\Permission::AssignRoles));

        $this->actingAs($inviter)->get('/en/admin/users/invite')->assertOk()
            ->assertSee('<option value="viewer"', false)
            ->assertDontSee('<option value="administrator"', false)
            ->assertDontSee('<option value="workshop-observer"', false)
            ->assertDontSee('<option value="account-clerk"', false);

        foreach (['administrator', 'workshop-observer', 'account-clerk'] as $i => $role) {
            $this->actingAs($inviter)->post('/en/admin/users/invite', [
                'name' => 'Raised', 'email' => "raised{$i}@example.test", 'role' => $role, 'preferred_locale' => 'en',
            ])->assertForbidden();
        }
        $this->assertSame(0, User::where('email', 'like', 'raised%')->count());

        $this->actingAs($inviter)->post('/en/admin/users/invite', [
            'name' => 'Plain Viewer', 'email' => 'viewer@example.test', 'role' => 'viewer', 'preferred_locale' => 'en',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $viewer = User::where('email', 'viewer@example.test')->sole();
        $this->assertSame(Role::Viewer, $viewer->role);
        $this->assertNull($viewer->custom_role_id);

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post('/en/admin/users/invite', [
            'name' => 'New Admin', 'email' => 'admin2@example.test', 'role' => 'administrator', 'preferred_locale' => 'en',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(Role::Administrator, User::where('email', 'admin2@example.test')->sole()->role);
    }
}
