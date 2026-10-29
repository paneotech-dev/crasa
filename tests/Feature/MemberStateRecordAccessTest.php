<?php

namespace Tests\Feature;

use App\Enums\ContributionState;
use App\Models\AuthorityChange;
use App\Models\Country;
use App\Models\Instrument;
use App\Models\Sector;
use App\Models\User;
use App\Support\ShellNavigation;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Contributions and the member state's own record: the member area and a
 * country to read, the representative's permission to file. A country on
 * its own used to be enough, so a viewer, moderator or auditor carrying one
 * reached both pages and could propose a change to a national regulator's
 * record.
 */
class MemberStateRecordAccessTest extends TestCase
{
    use RefreshDatabase;

    private Country $botswana;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        $this->botswana = Country::where('iso_code', 'bw')->firstOrFail();
    }

    public static function outsideTheMemberArea(): array
    {
        return ['viewer' => ['viewer'], 'moderator' => ['moderator'], 'auditor' => ['auditor']];
    }

    private function withCountry(string $state): User
    {
        return User::factory()->withoutTwoFactor()->{$state}()->create(['country_id' => $this->botswana->id]);
    }

    /** @return array<string, mixed> */
    private function contribution(): array
    {
        return [
            'reference_number' => 'BW Act 3 of 2024',
            'instrument_type' => 'act',
            'sector_id' => Sector::first()->id,
            'status' => 'in_force',
            'title_en' => 'Botswana communications act',
        ];
    }

    /** @return array<string, mixed> */
    private function proposal(): array
    {
        return ['name' => 'Botswana Communications Regulatory Authority', 'acronym' => 'BOCRA'];
    }

    private function draft(): Instrument
    {
        $rep = User::factory()->memberStateRep($this->botswana)->create();
        $this->actingAs($rep)->post('/en/contributions', $this->contribution())->assertRedirect();

        return Instrument::withContributor()->latest('id')->firstOrFail();
    }

    #[DataProvider('outsideTheMemberArea')]
    public function test_a_role_outside_the_member_area_is_refused_even_with_a_country(string $state): void
    {
        $user = $this->withCountry($state);
        $instrument = $this->draft();

        $this->actingAs($user)->get('/en/contributions')->assertForbidden();
        $this->actingAs($user)->get('/en/contributions/new')->assertForbidden();
        $this->actingAs($user)->post('/en/contributions', $this->contribution())->assertForbidden();
        $this->actingAs($user)->get('/en/contributions/'.$instrument->slug)->assertForbidden();
        $this->actingAs($user)->put('/en/contributions/'.$instrument->slug, $this->contribution())->assertForbidden();
        $this->actingAs($user)->post('/en/contributions/'.$instrument->slug.'/submit')->assertForbidden();
        $this->actingAs($user)->get('/en/authority')->assertForbidden();
        $this->actingAs($user)->put('/en/authority', $this->proposal())->assertForbidden();

        $this->assertSame(1, Instrument::withContributor()->count());
        $this->assertSame(0, AuthorityChange::count());
    }

    public function test_a_committee_member_reads_both_pages_and_files_nothing(): void
    {
        $member = User::factory()->withoutTwoFactor()->committeeMember($this->botswana)->create();
        $instrument = $this->draft();

        $this->actingAs($member)->get('/en/contributions')->assertOk()
            ->assertSee($instrument->reference_number)
            ->assertDontSee('/en/contributions/new');
        $this->actingAs($member)->get('/en/contributions/'.$instrument->slug)->assertOk()
            ->assertDontSee(__('contributions.save'));
        $this->actingAs($member)->get('/en/authority')->assertOk()
            ->assertSee(__('d3_security.authority_read_only'))
            ->assertDontSee(__('authority.propose'));

        $this->actingAs($member)->get('/en/contributions/new')->assertForbidden();
        $this->actingAs($member)->post('/en/contributions', $this->contribution())->assertForbidden();
        $this->actingAs($member)->put('/en/contributions/'.$instrument->slug, $this->contribution())->assertForbidden();
        $this->actingAs($member)->post('/en/contributions/'.$instrument->slug.'/submit')->assertForbidden();
        $this->actingAs($member)->put('/en/authority', $this->proposal())->assertForbidden();

        $this->assertSame(ContributionState::Draft, $instrument->fresh()->contribution_state);
        $this->assertSame(0, AuthorityChange::count());
    }

    public function test_a_representative_reads_files_and_proposes(): void
    {
        $rep = User::factory()->withoutTwoFactor()->memberStateRep($this->botswana)->create();

        $this->actingAs($rep)->get('/en/contributions')->assertOk()->assertSee('/en/contributions/new');
        $this->actingAs($rep)->get('/en/contributions/new')->assertOk();
        $this->actingAs($rep)->post('/en/contributions', $this->contribution())->assertRedirect();
        $instrument = Instrument::withContributor()->latest('id')->firstOrFail();
        $this->actingAs($rep)->post('/en/contributions/'.$instrument->slug.'/submit')->assertRedirect();
        $this->assertSame(ContributionState::Submitted, $instrument->fresh()->contribution_state);

        $this->actingAs($rep)->get('/en/authority')->assertOk()->assertSee(__('authority.propose'));
        $this->actingAs($rep)->put('/en/authority', $this->proposal())->assertRedirect();
        $this->assertSame(1, AuthorityChange::count());
    }

    public function test_the_sidebar_offers_the_two_rows_exactly_where_the_pages_open(): void
    {
        $cases = [
            [User::factory()->memberStateRep($this->botswana)->create(), true],
            [User::factory()->committeeMember($this->botswana)->create(), true],
            [$this->withCountry('viewer'), false],
            [$this->withCountry('moderator'), false],
            [$this->withCountry('auditor'), false],
        ];

        foreach ($cases as [$user, $expected]) {
            $this->actingAs($user);
            $keys = collect(ShellNavigation::extranet($user))->pluck('items')->flatten(1)->pluck('key')->all();

            $this->assertSame($expected, in_array('contributions', $keys, true), $user->role->value.' contributions');
            $this->assertSame($expected, in_array('authority', $keys, true), $user->role->value.' authority');
        }
    }
}
