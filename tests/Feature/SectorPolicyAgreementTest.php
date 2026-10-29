<?php

namespace Tests\Feature;

use App\Models\Sector;
use App\Models\User;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SectorPolicy refused creating and deleting a sector to everyone while
 * Content > Taxonomies let administrators do both. The policy now answers
 * as the screen does (ManageSettings), the screen asks the policy, and the
 * generic data screen, which cannot write a sector's slug, icon and three
 * names, sends the reader who may to Taxonomies.
 */
class SectorPolicyAgreementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SectorSeeder::class);
    }

    public function test_the_policy_and_the_taxonomies_screen_agree_for_an_administrator(): void
    {
        $admin = User::factory()->administrator()->create();
        $postal = Sector::where('slug', 'postal')->sole();

        $this->assertTrue($admin->can('create', Sector::class));
        $this->assertTrue($admin->can('delete', $postal));

        $this->actingAs($admin)->get('/en/admin/taxonomies/sectors/new')->assertOk();
        $this->actingAs($admin)->post('/en/admin/taxonomies/sectors', [
            'name' => ['en' => 'Broadband', 'fr' => 'Haut débit', 'pt' => 'Banda larga'],
            'slug' => 'broadband',
            'sort_order' => 9,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $broadband = Sector::where('slug', 'broadband')->sole();
        $this->actingAs($admin)->delete('/en/admin/taxonomies/sectors/'.$broadband->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(Sector::find($broadband->id));

        // The generic screen still corrects names only, and says where.
        $this->actingAs($admin)->get('/en/admin/data/sectors')->assertOk()
            ->assertSee(__('d3_security.sectors_in_taxonomies'))
            ->assertSee(route('admin.taxonomies', ['locale' => 'en', 'kind' => 'sectors']), false);
        $this->actingAs($admin)->get('/en/admin/data/sectors/new')->assertForbidden();
    }

    public function test_no_other_role_creates_or_deletes_a_sector_anywhere(): void
    {
        $postal = Sector::where('slug', 'postal')->sole();

        foreach (['editor', 'moderator', 'auditor', 'viewer', 'memberStateRep', 'committeeMember'] as $state) {
            $user = User::factory()->{$state}()->create();

            $this->assertFalse($user->can('create', Sector::class), $state);
            $this->assertFalse($user->can('delete', $postal), $state);

            $this->actingAs($user)->get('/en/admin/taxonomies/sectors/new')->assertForbidden();
            $this->actingAs($user)->post('/en/admin/taxonomies/sectors', [
                'name' => ['en' => 'X', 'fr' => 'X', 'pt' => 'X'], 'slug' => 'x', 'sort_order' => 1,
            ])->assertForbidden();
            $this->actingAs($user)->delete('/en/admin/taxonomies/sectors/'.$postal->id)->assertForbidden();
            $this->actingAs($user)->post('/en/admin/data/sectors', ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->delete('/en/admin/data/sectors/'.$postal->id)->assertForbidden();
        }

        $this->assertSame(3, Sector::count());
    }
}
