<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\AuthorityChange;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Scopes\CountryScope;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A member state maintaining its own authority record. Extranet item 3.
 *
 * The record is what the public members page shows and what a ministry
 * cites, so a representative proposes and the Secretariat applies. These
 * hold that boundary, and they hold that approving a proposal is the same
 * act as confirming a record in the room at Lilongwe: it clears is_sample
 * and names the person who vouched.
 */
class AuthorityProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $botswanaRep;

    private User $mozambiqueRep;

    private User $secretariat;

    private Country $botswana;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        $this->botswana = Country::where('iso_code', 'bw')->firstOrFail();

        $this->botswanaRep = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => $this->botswana->id,
        ]);

        $this->mozambiqueRep = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => Country::where('iso_code', 'mz')->value('id'),
        ]);

        $this->secretariat = User::factory()->administrator()->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Botswana Communications Regulatory Authority',
            'acronym' => 'BOCRA',
            'website' => 'https://www.bocra.org.bw',
            'established_year' => 2013,
            'sectors' => [Sector::first()->id],
            'committees' => [Committee::first()->id],
        ], $overrides);
    }

    public function test_the_page_opens_on_this_states_own_record(): void
    {
        $this->actingAs($this->botswanaRep)
            ->get('/en/authority')
            ->assertOk()
            ->assertSee('Botswana');
    }

    public function test_a_representative_proposes_rather_than_writing_the_record(): void
    {
        $before = Authority::where('country_id', $this->botswana->id)->value('name');

        $this->actingAs($this->botswanaRep)
            ->put('/en/authority', $this->payload())
            ->assertRedirect();

        $this->assertSame(
            $before,
            Authority::where('country_id', $this->botswana->id)->value('name'),
            'A representative edit reached the live record without the Secretariat seeing it.'
        );

        $change = AuthorityChange::query()->withoutGlobalScope(CountryScope::class)->sole();
        $this->assertSame('submitted', $change->status);
        $this->assertSame('BOCRA', $change->payload['acronym']);
    }

    /** One open proposal per member state, so the Secretariat reviews one version. */
    public function test_a_second_edit_revises_the_open_proposal_rather_than_opening_another(): void
    {
        $this->actingAs($this->botswanaRep)->put('/en/authority', $this->payload());
        $this->actingAs($this->botswanaRep)->put('/en/authority', $this->payload(['acronym' => 'BOCRA2']));

        $changes = AuthorityChange::query()->withoutGlobalScope(CountryScope::class)->get();

        $this->assertCount(1, $changes);
        $this->assertSame('BOCRA2', $changes->first()->payload['acronym']);
    }

    public function test_the_form_shows_what_was_proposed_rather_than_what_is_still_held(): void
    {
        $this->actingAs($this->botswanaRep)->put('/en/authority', $this->payload(['acronym' => 'PROPOSED']));

        $this->actingAs($this->botswanaRep)
            ->get('/en/authority')
            ->assertOk()
            ->assertSee('PROPOSED');
    }

    public function test_approval_applies_the_record_and_records_who_vouched(): void
    {
        $this->actingAs($this->botswanaRep)->put('/en/authority', $this->payload());
        $change = AuthorityChange::query()->withoutGlobalScope(CountryScope::class)->sole();

        $this->actingAs($this->secretariat)
            ->put('/en/admin/authority-changes/'.$change->id.'/approve', [
                'confirmed_by_name' => 'A named delegate',
            ])
            ->assertRedirect();

        $authority = Authority::where('country_id', $this->botswana->id)->sole();

        $this->assertSame('BOCRA', $authority->acronym);
        $this->assertSame('A named delegate', $authority->confirmed_by_name);
        $this->assertNotNull($authority->confirmed_at);

        // Confirmed by its own regulator, so the go-live purge must not
        // take it. This is the same outcome the Lilongwe session produces.
        $this->assertFalse($authority->is_sample);
        $this->assertSame('approved', $change->fresh()->status);
    }

    public function test_approval_without_naming_who_vouched_is_refused(): void
    {
        $this->actingAs($this->botswanaRep)->put('/en/authority', $this->payload());
        $change = AuthorityChange::query()->withoutGlobalScope(CountryScope::class)->sole();

        $this->actingAs($this->secretariat)
            ->put('/en/admin/authority-changes/'.$change->id.'/approve', ['confirmed_by_name' => ''])
            ->assertSessionHasErrors('confirmed_by_name');

        $this->assertSame('submitted', $change->fresh()->status);
    }

    public function test_a_returned_change_carries_its_reason_back_to_the_representative(): void
    {
        $this->actingAs($this->botswanaRep)->put('/en/authority', $this->payload());
        $change = AuthorityChange::query()->withoutGlobalScope(CountryScope::class)->sole();

        $this->actingAs($this->secretariat)
            ->put('/en/admin/authority-changes/'.$change->id.'/return', [
                'review_note' => 'The acronym does not match the gazette notice.',
            ])
            ->assertRedirect();

        $this->actingAs($this->botswanaRep)
            ->get('/en/authority')
            ->assertOk()
            ->assertSee('does not match the gazette notice');
    }

    public function test_the_sectors_proposed_become_the_authoritys_mandate_on_approval(): void
    {
        $postal = Sector::where('slug', 'postal')->firstOrFail();

        $this->actingAs($this->botswanaRep)->put('/en/authority', $this->payload(['sectors' => [$postal->id]]));
        $change = AuthorityChange::query()->withoutGlobalScope(CountryScope::class)->sole();

        $this->actingAs($this->secretariat)
            ->put('/en/admin/authority-changes/'.$change->id.'/approve', ['confirmed_by_name' => 'A delegate']);

        $authority = Authority::where('country_id', $this->botswana->id)->sole();

        $this->assertSame([$postal->id], $authority->sectors->pluck('id')->all());
    }

    public function test_a_logo_is_stored_on_the_public_disk_under_the_iso_code(): void
    {
        $this->actingAs($this->botswanaRep)
            ->put('/en/authority', $this->payload([
                'logo' => UploadedFile::fake()->image('bocra.png', 200, 200),
            ]))
            ->assertRedirect();

        Storage::disk('public')->assertExists('images/authorities/bw.png');
    }

    /* The boundary ---------------------------------------------------------- */

    public function test_botswana_cannot_see_or_touch_mozambiques_proposal(): void
    {
        $this->actingAs($this->mozambiqueRep)->put('/en/authority', $this->payload(['acronym' => 'INCM']));

        $this->actingAs($this->botswanaRep)
            ->get('/en/authority')
            ->assertOk()
            ->assertDontSee('INCM');

        // The global scope makes another member state's proposal invisible
        // to the query, not merely hidden in the view.
        $this->actingAs($this->botswanaRep);
        $this->assertSame(0, AuthorityChange::count());
    }

    public function test_a_representative_cannot_reach_the_secretariats_review_queue(): void
    {
        $this->actingAs($this->botswanaRep)->get('/en/admin/authority-changes')->assertForbidden();
    }
}
