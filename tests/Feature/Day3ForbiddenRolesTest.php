<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\Country;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The forbidden-role cases the Day 3 screens were missing: the delegate
 * registrations screens, a user's page and its edit form, and the
 * members-only registration form. Each refusal is a 403 or a 404, never a
 * 500, and the allowed case is held beside it.
 *
 * The documents screen and its bin are held in BackOfficeDocumentsAccessTest.
 */
class Day3ForbiddenRolesTest extends TestCase
{
    use RefreshDatabase;

    private Country $botswana;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->botswana = Country::where('iso_code', 'bw')->sole();
    }

    private function as(string $state): User
    {
        return in_array($state, ['memberStateRep', 'committeeMember'], true)
            ? User::factory()->{$state}($this->botswana)->create()
            : User::factory()->{$state}()->create();
    }

    private function registration(): DelegateRegistration
    {
        $meeting = Meeting::factory()->upcoming()->create(['clearance' => 'public']);

        return DelegateRegistration::create([
            'meeting_id' => $meeting->id,
            'delegate_name' => 'Thandiwe Phiri',
            'delegate_email' => 'thandiwe@example.org',
            'arrival_on' => $meeting->starts_on->copy()->subDay()->toDateString(),
            'departure_on' => $meeting->starts_on->toDateString(),
            'status' => 'filed',
        ]);
    }

    /* Delegate registrations ------------------------------------------------ */

    public static function withoutMeetings(): array
    {
        return [
            'moderator' => ['moderator'], 'auditor' => ['auditor'],
            'member' => ['memberStateRep'], 'committee' => ['committeeMember'], 'viewer' => ['viewer'],
        ];
    }

    #[DataProvider('withoutMeetings')]
    public function test_delegate_registrations_are_refused(string $state): void
    {
        $user = $this->as($state);
        $row = $this->registration();
        $slug = $row->meeting->slug;

        $this->actingAs($user)->get('/en/admin/registrations')->assertForbidden();
        $this->actingAs($user)->get('/en/admin/registrations/export.csv')->assertForbidden();
        $this->actingAs($user)->get('/en/admin/registrations/export.xlsx')->assertForbidden();
        $this->actingAs($user)->put("/en/admin/registrations/{$row->id}/status", ['status' => 'confirmed'])->assertForbidden();
        $this->actingAs($user)->post("/en/admin/registrations/{$row->id}/resend")->assertForbidden();
        $this->actingAs($user)->get("/en/admin/meetings/{$slug}/delegates")->assertForbidden();
        $this->actingAs($user)->get("/en/admin/meetings/{$slug}/delegates.csv")->assertForbidden();

        $this->assertSame('filed', $row->fresh()->status);
    }

    /**
     * The editor holds ManageMeetings on the roles grid as shipped, so the
     * registrations screens are theirs, like the events they belong to.
     */
    public function test_administrators_and_editors_reach_delegate_registrations(): void
    {
        $row = $this->registration();

        foreach ([User::factory()->administrator()->create(), $this->as('editor')] as $user) {
            $this->actingAs($user)->get('/en/admin/registrations')->assertOk()->assertSee('Thandiwe Phiri');
            $this->actingAs($user)->get("/en/admin/meetings/{$row->meeting->slug}/delegates")->assertOk();
        }
    }

    /* A user's page and its edit form -------------------------------------- */

    public static function withoutUserRights(): array
    {
        return [
            'moderator' => ['moderator'], 'auditor' => ['auditor'],
            'member' => ['memberStateRep'], 'committee' => ['committeeMember'], 'viewer' => ['viewer'],
        ];
    }

    #[DataProvider('withoutUserRights')]
    public function test_a_users_page_and_edit_form_are_refused(string $state): void
    {
        $user = $this->as($state);
        $target = User::factory()->create(['name' => 'Target Account']);

        $this->actingAs($user)->get("/en/admin/users/{$target->id}")->assertForbidden();
        $this->actingAs($user)->get("/en/admin/users/{$target->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/en/admin/users/{$target->id}", ['name' => 'Changed', 'email' => 'changed@example.org'])->assertForbidden();

        $this->assertSame('Target Account', $target->fresh()->name);
    }

    public function test_an_administrator_opens_a_users_page_and_edit_form(): void
    {
        $admin = User::factory()->administrator()->create();
        $target = User::factory()->create(['name' => 'Target Account']);

        $this->actingAs($admin)->get("/en/admin/users/{$target->id}")->assertOk()->assertSee('Target Account');
        $this->actingAs($admin)->get("/en/admin/users/{$target->id}/edit")->assertOk();
    }

    /* The members-only registration form ------------------------------------ */

    public function test_a_signed_in_reader_who_may_not_see_the_meeting_is_refused_the_form(): void
    {
        $members = Meeting::factory()->upcoming()->create(['clearance' => 'member']);
        $committee = Committee::first();
        $confidential = Meeting::factory()->upcoming()->create(['clearance' => 'confidential', 'committee_id' => $committee->id]);
        $payload = [
            'delegate_name' => 'Not Allowed',
            'delegate_email' => 'not-allowed@example.org',
            'arrival_on' => $members->starts_on->copy()->subDay()->toDateString(),
            'departure_on' => $members->starts_on->toDateString(),
        ];

        $viewer = $this->as('viewer');
        foreach ([$members, $confidential] as $meeting) {
            $this->assertContains($this->actingAs($viewer)->get('/en/events/'.$meeting->slug.'/register')->getStatusCode(), [403, 404]);
            $this->assertContains($this->actingAs($viewer)->post('/en/events/'.$meeting->slug.'/register', $payload)->getStatusCode(), [403, 404]);
        }

        // A representative without a seat on the committee.
        $rep = $this->as('memberStateRep');
        $this->assertContains($this->actingAs($rep)->get('/en/events/'.$confidential->slug.'/register')->getStatusCode(), [403, 404]);
        $this->assertContains($this->actingAs($rep)->post('/en/events/'.$confidential->slug.'/register', $payload)->getStatusCode(), [403, 404]);

        $this->assertSame(0, DelegateRegistration::withoutGlobalScopes()->count());

        // The allowed case: a representative reads the members' meeting form.
        $this->actingAs($rep)->get('/en/events/'.$members->slug.'/register')->assertOk();
    }
}
