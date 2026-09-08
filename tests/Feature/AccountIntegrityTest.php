<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Enums\CommitteeRole;
use App\Enums\Permission;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Rulings 2, 3 and 5 from task 1.
 */
class AccountIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/t/anything', fn () => response('reached'))->name('t.anything');
    }

    /* Ruling 5 — authority must belong to the account's country ------------ */

    public function test_an_authority_from_another_country_is_refused(): void
    {
        $namibia = Country::factory()->create(['iso_code' => 'na']);
        $zambia = Country::factory()->create(['iso_code' => 'zm']);
        $zambianRegulator = Authority::factory()->for($zambia)->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belongs to a different member state');

        User::factory()->memberStateRep($namibia)->create([
            'authority_id' => $zambianRegulator->id,
        ]);
    }

    public function test_an_authority_with_no_country_on_the_account_is_refused(): void
    {
        $authority = Authority::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has an authority but no country');

        User::factory()->create(['country_id' => null, 'authority_id' => $authority->id]);
    }

    public function test_a_matching_authority_and_country_is_accepted(): void
    {
        $authority = Authority::factory()->create();

        $user = User::factory()->forAuthority($authority)->create();

        $this->assertSame($authority->country_id, $user->country_id);
    }

    public function test_an_account_with_no_authority_is_unaffected(): void
    {
        $user = User::factory()->administrator()->create();

        $this->assertNull($user->authority_id);
    }

    public function test_the_rule_also_holds_on_update_not_only_on_create(): void
    {
        $namibia = Country::factory()->create(['iso_code' => 'na']);
        $zambia = Country::factory()->create(['iso_code' => 'zm']);
        $zambianRegulator = Authority::factory()->for($zambia)->create();

        $user = User::factory()->memberStateRep($namibia)->create();

        $this->expectException(RuntimeException::class);

        $user->update(['authority_id' => $zambianRegulator->id]);
    }

    /* Ruling 3 — suspension ------------------------------------------------ */

    public function test_a_suspended_account_authenticates_and_is_sent_to_the_notice(): void
    {
        $user = User::factory()->editor()->suspended()->create();

        $this->actingAs($user)
            ->get('/t/anything')
            ->assertRedirect(route('account.suspended', ['locale' => 'en']));
    }

    public function test_the_suspension_notice_itself_is_reachable(): void
    {
        $this->actingAs(User::factory()->editor()->suspended()->create())
            ->get(route('account.suspended', ['locale' => 'en']))
            ->assertStatus(403)
            ->assertSee('Contact the CRASA Secretariat');
    }

    public function test_a_suspended_account_holds_no_permissions(): void
    {
        $admin = User::factory()->administrator()->suspended()->create();

        foreach (Permission::cases() as $permission) {
            $this->assertFalse(
                $admin->hasPermission($permission),
                'A suspended administrator still held '.$permission->value
            );
        }
    }

    public function test_an_active_account_is_unaffected(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->get('/t/anything')
            ->assertOk();
    }

    public function test_an_invited_account_cannot_yet_use_the_platform(): void
    {
        $user = User::factory()->editor()->invited()->create();

        $this->assertFalse($user->mayUsePlatform());
        $this->assertFalse($user->hasPermission(Permission::ManageContent));
    }

    /* Ruling 2 — clearance is per-committee-role --------------------------- */

    public function test_a_public_document_needs_no_account(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->assertTrue($viewer->mayOpenClearance(Clearance::Public));
    }

    public function test_member_clearance_follows_the_permission_matrix(): void
    {
        $country = Country::factory()->create();

        $this->assertTrue(User::factory()->administrator()->create()->mayOpenClearance(Clearance::Member));
        $this->assertTrue(User::factory()->editor()->create()->mayOpenClearance(Clearance::Member));
        $this->assertTrue(User::factory()->memberStateRep($country)->create()->mayOpenClearance(Clearance::Member));
        $this->assertFalse(User::factory()->viewer()->create()->mayOpenClearance(Clearance::Member));
    }

    public function test_confidential_is_opened_by_the_seat_held_on_the_owning_committee(): void
    {
        $country = Country::factory()->create();
        $committee = Committee::factory()->create();
        $rep = User::factory()->memberStateRep($country)->create();

        // No seat: refused, even though the account is a member state rep.
        $this->assertFalse($rep->mayOpenClearance(Clearance::Confidential, $committee->id));

        $committee->users()->attach($rep, ['role' => CommitteeRole::Member->value]);

        $this->assertTrue($rep->fresh()->mayOpenClearance(Clearance::Confidential, $committee->id));
    }

    public function test_an_observer_attends_but_does_not_open_confidential_papers(): void
    {
        $country = Country::factory()->create();
        $committee = Committee::factory()->create();
        $rep = User::factory()->memberStateRep($country)->create();

        $committee->users()->attach($rep, ['role' => CommitteeRole::Observer->value]);

        $this->assertFalse($rep->fresh()->mayOpenClearance(Clearance::Confidential, $committee->id));
    }

    public function test_a_seat_on_one_committee_does_not_open_anothers_papers(): void
    {
        $country = Country::factory()->create();
        $ownCommittee = Committee::factory()->create(['slug' => 'own']);
        $otherCommittee = Committee::factory()->create(['slug' => 'other']);
        $rep = User::factory()->memberStateRep($country)->create();

        $ownCommittee->users()->attach($rep, ['role' => CommitteeRole::Chair->value]);

        $this->assertTrue($rep->fresh()->mayOpenClearance(Clearance::Confidential, $ownCommittee->id));
        $this->assertFalse($rep->fresh()->mayOpenClearance(Clearance::Confidential, $otherCommittee->id));
    }

    public function test_secretariat_clearance_is_administrators_only(): void
    {
        $country = Country::factory()->create();
        $committee = Committee::factory()->create();
        $rep = User::factory()->memberStateRep($country)->create();
        $committee->users()->attach($rep, ['role' => CommitteeRole::Chair->value]);

        $this->assertTrue(User::factory()->administrator()->create()->mayOpenClearance(Clearance::Secretariat));
        $this->assertFalse(User::factory()->editor()->create()->mayOpenClearance(Clearance::Secretariat));
        $this->assertFalse($rep->fresh()->mayOpenClearance(Clearance::Secretariat, $committee->id));
    }

    public function test_a_suspended_committee_chair_opens_nothing(): void
    {
        $country = Country::factory()->create();
        $committee = Committee::factory()->create();
        $rep = User::factory()->memberStateRep($country)->suspended()->create();
        $committee->users()->attach($rep, ['role' => CommitteeRole::Chair->value]);

        $this->assertFalse($rep->fresh()->mayOpenClearance(Clearance::Confidential, $committee->id));
        $this->assertTrue($rep->fresh()->mayOpenClearance(Clearance::Public));
    }

    public function test_the_clearance_ladder_is_ordered(): void
    {
        $this->assertLessThan(Clearance::Member->rank(), Clearance::Public->rank());
        $this->assertLessThan(Clearance::Confidential->rank(), Clearance::Member->rank());
        $this->assertLessThan(Clearance::Secretariat->rank(), Clearance::Confidential->rank());
    }
}
