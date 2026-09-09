<?php

namespace Tests\Feature;

use App\Enums\CommitteeRole;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Which member states hold seats on which committee.
 *
 * The committees have been in the platform since the schema was written and
 * the seeder has never filled this in, deliberately: inventing which member
 * state sits on which committee is the one thing that seeder must not do.
 * So the committees page told every reader "seats not yet recorded" and
 * there was no way to record them. This is the screen the confirmation
 * session in Lilongwe writes into.
 */
class CommitteeSeatsTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Committee $committee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(PublicPagesSeeder::class);

        $this->administrator = User::factory()->administrator()->create();
        $this->committee = Committee::where('slug', 'spectrum-and-frequency-management')->sole();
    }

    private function url(string $suffix = ''): string
    {
        return '/en/admin/committees/'.$this->committee->slug.'/seats'.$suffix;
    }

    private function country(string $iso): Country
    {
        return Country::where('iso_code', $iso)->sole();
    }

    public function test_the_screen_opens_and_says_nothing_is_recorded_yet(): void
    {
        $this->actingAs($this->administrator)->get($this->url())
            ->assertOk()
            ->assertSee(__('admin.seats_empty_title'))
            ->assertSee($this->committee->name);
    }

    public function test_a_seat_is_added_changed_and_removed(): void
    {
        $botswana = $this->country('bw');

        $this->actingAs($this->administrator)->post($this->url(), [
            'country_id' => $botswana->id,
            'role' => CommitteeRole::Member->value,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('committee_country', [
            'committee_id' => $this->committee->id,
            'country_id' => $botswana->id,
            'role' => 'member',
            // Recorded by the Secretariat, so the go-live strip leaves it.
            'is_sample' => false,
        ]);

        $this->actingAs($this->administrator)->put($this->url('/'.$botswana->iso_code), [
            'role' => CommitteeRole::Observer->value,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('committee_country', [
            'committee_id' => $this->committee->id,
            'country_id' => $botswana->id,
            'role' => 'observer',
        ]);

        $this->actingAs($this->administrator)->delete($this->url('/'.$botswana->iso_code))->assertRedirect();

        $this->assertDatabaseMissing('committee_country', [
            'committee_id' => $this->committee->id,
            'country_id' => $botswana->id,
        ]);
    }

    /**
     * A committee has one chair. Naming a second moves the first to member
     * rather than refusing: the intent of the click is unambiguous, and
     * refusing would make the operator demote the old chair first, which
     * leaves the committee chairless in between.
     */
    public function test_naming_a_second_chair_stands_the_first_down(): void
    {
        $botswana = $this->country('bw');
        $zambia = $this->country('zm');

        $this->actingAs($this->administrator)->post($this->url(), [
            'country_id' => $botswana->id, 'role' => CommitteeRole::Chair->value,
        ])->assertRedirect();

        $this->actingAs($this->administrator)->post($this->url(), [
            'country_id' => $zambia->id, 'role' => CommitteeRole::Chair->value,
        ])->assertRedirect();

        $roles = $this->committee->fresh()->countries()->get()
            ->mapWithKeys(fn (Country $c) => [$c->iso_code => $c->pivot->role]);

        $this->assertSame('member', $roles['bw']);
        $this->assertSame('chair', $roles['zm']);

        $this->assertSame(
            1,
            $this->committee->countries()->wherePivot('role', 'chair')->count(),
            'A committee has more than one chair.'
        );
    }

    /** The same on a role change, not only on an add. */
    public function test_promoting_a_seated_state_stands_the_chair_down(): void
    {
        $botswana = $this->country('bw');
        $zambia = $this->country('zm');

        $this->actingAs($this->administrator)->post($this->url(), [
            'country_id' => $botswana->id, 'role' => CommitteeRole::Chair->value,
        ])->assertRedirect();

        $this->actingAs($this->administrator)->post($this->url(), [
            'country_id' => $zambia->id, 'role' => CommitteeRole::Member->value,
        ])->assertRedirect();

        $this->actingAs($this->administrator)->put($this->url('/'.$zambia->iso_code), [
            'role' => CommitteeRole::Chair->value,
        ])->assertRedirect();

        $this->assertSame(1, $this->committee->countries()->wherePivot('role', 'chair')->count());
    }

    /** Adding a state that already sits here is refused, not a silent change. */
    public function test_a_state_cannot_take_two_seats_on_one_committee(): void
    {
        $botswana = $this->country('bw');

        $this->actingAs($this->administrator)->post($this->url(), [
            'country_id' => $botswana->id, 'role' => CommitteeRole::Member->value,
        ])->assertRedirect();

        $this->actingAs($this->administrator)->post($this->url(), [
            'country_id' => $botswana->id, 'role' => CommitteeRole::Chair->value,
        ])->assertSessionHasErrors('country_id');

        $this->assertSame(1, $this->committee->countries()->count());

        // And the form does not offer it.
        $this->actingAs($this->administrator)->get($this->url())
            ->assertOk()
            ->assertDontSee('value="'.$botswana->id.'" ', false);
    }

    /** Every change is on the record. */
    public function test_every_seat_change_writes_an_audit_row(): void
    {
        $botswana = $this->country('bw');

        $this->actingAs($this->administrator)->post($this->url(), [
            'country_id' => $botswana->id, 'role' => CommitteeRole::Member->value,
        ])->assertRedirect();

        $this->actingAs($this->administrator)->delete($this->url('/'.$botswana->iso_code))->assertRedirect();

        $entries = AuditLog::where('auditable_type', Committee::class)
            ->where('auditable_id', $this->committee->id)
            ->where('action', AuditLog::UPDATED)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame('Botswana', $entries[0]->changes['seat_added']);
        $this->assertSame('Botswana', $entries[1]->changes['seat_removed']);
    }

    /** The counts on the public pages read this pivot, so they follow. */
    public function test_the_public_committee_page_follows(): void
    {
        $this->get('/en/committees')->assertOk();

        $this->assertFalse(Committee::seatsRecorded());

        $this->actingAs($this->administrator)->post($this->url(), [
            'country_id' => $this->country('bw')->id,
            'role' => CommitteeRole::Chair->value,
        ])->assertRedirect();

        $this->assertTrue(Committee::seatsRecorded());

        $this->get('/en/committees')->assertOk()->assertSee($this->committee->name);
    }

    /* Who may ------------------------------------------------------------ */

    public function test_a_representative_cannot_change_seats(): void
    {
        $rep = User::factory()->memberStateRep($this->country('bw'))->create();

        $this->actingAs($rep)->get($this->url())->assertForbidden();
        $this->actingAs($rep)->post($this->url(), [
            'country_id' => $this->country('bw')->id, 'role' => 'chair',
        ])->assertForbidden();

        $this->assertSame(0, $this->committee->countries()->count());
    }

    /** The screen is reachable from the committee it belongs to. */
    public function test_the_committee_editor_links_to_its_seats(): void
    {
        $this->actingAs($this->administrator)
            ->get('/en/admin/data/committees/'.$this->committee->id)
            ->assertOk()
            ->assertSee($this->url(), false);
    }
}
