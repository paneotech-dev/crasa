<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Enums\Role;
use App\Models\Country;
use App\Models\Folder;
use App\Models\User;
use App\Support\Documents\FolderAccess;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A folder rule naming roles offers only roles that can reach the member
 * area, where folders are read. It used to offer viewer, moderator and
 * auditor, none of whom ever reaches a folder.
 */
class FolderRuleRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_roles_offered_are_those_the_member_area_gate_admits(): void
    {
        $this->seed(CountrySeeder::class);

        $this->assertSame(
            [Role::Administrator, Role::Editor, Role::MemberStateRep, Role::CommitteeMember],
            FolderAccess::roles(),
        );

        foreach (Role::cases() as $role) {
            $user = $role === Role::MemberStateRep || $role === Role::CommitteeMember
                ? User::factory()->create(['role' => $role, 'country_id' => Country::where('iso_code', 'bw')->value('id')])
                : User::factory()->create(['role' => $role]);

            $this->assertSame($user->can('enter-member-area'), in_array($role, FolderAccess::roles(), true), $role->value);
        }
    }

    public function test_the_form_does_not_offer_them_and_the_server_refuses_them(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $admin = User::factory()->administrator()->create();
        $folder = Folder::withoutGlobalScopes()->create(['name' => 'Regional', 'slug' => 'regional', 'clearance' => Clearance::Member]);

        $html = $this->actingAs($admin)->get('/en/admin/documents/folders/'.$folder->id.'/edit')->assertOk()->getContent();
        $this->assertStringContainsString('value="committee_member"', $html);
        foreach (['viewer', 'moderator', 'auditor'] as $role) {
            $this->assertStringNotContainsString('[roles][]" value="'.$role.'"', $html);
        }

        $this->actingAs($admin)->put('/en/admin/documents/folders/'.$folder->id, [
            'name' => 'Regional', 'clearance' => 'member',
            'access' => [
                'view' => ['type' => 'roles', 'roles' => ['viewer']],
                'upload' => ['type' => 'secretariat'],
                'validate' => ['type' => 'secretariat'],
            ],
        ])->assertSessionHasErrors('access.view.roles.0');

        $this->assertNull(Folder::withoutGlobalScopes()->find($folder->id)->access);
    }
}
