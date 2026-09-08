<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Sector;
use App\Support\Locales;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 2 of docs/DATA_MODEL.md.
 */
class ReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    private function seed_reference_data(): void
    {
        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_all_fourteen_member_states_are_seeded(): void
    {
        $this->seed_reference_data();

        $this->assertSame(14, Country::count());
    }

    public function test_the_member_states_carry_the_iso_codes_from_the_data_model(): void
    {
        $this->seed_reference_data();

        $this->assertEqualsCanonicalizing(
            ['ao', 'bw', 'km', 'cd', 'sz', 'ls', 'mw', 'mu', 'mz', 'na', 'za', 'tz', 'zm', 'zw'],
            Country::pluck('iso_code')->all()
        );
    }

    public function test_every_member_state_is_served_in_at_least_one_platform_locale(): void
    {
        $this->seed_reference_data();

        foreach (Country::all() as $country) {
            $this->assertNotEmpty($country->locales(), $country->name.' has no platform locale.');

            foreach ($country->locales() as $locale) {
                $this->assertContains($locale, Locales::SUPPORTED);
            }
        }
    }

    public function test_the_lusophone_and_francophone_states_are_not_defaulted_to_english(): void
    {
        $this->seed_reference_data();

        $this->assertSame(['pt'], Country::where('iso_code', 'ao')->sole()->locales());
        $this->assertSame(['pt'], Country::where('iso_code', 'mz')->sole()->locales());
        $this->assertSame(['fr'], Country::where('iso_code', 'cd')->sole()->locales());
        $this->assertSame(['fr'], Country::where('iso_code', 'km')->sole()->locales());
        $this->assertSame(['en', 'fr'], Country::where('iso_code', 'mu')->sole()->locales());
    }

    public function test_no_accession_date_is_invented(): void
    {
        $this->seed_reference_data();

        $this->assertSame(0, Country::whereNotNull('joined_at')->count());
    }

    public function test_exactly_the_three_sectors_are_seeded(): void
    {
        $this->seed_reference_data();

        $this->assertEqualsCanonicalizing(Sector::SLUGS, Sector::pluck('slug')->all());
    }

    public function test_sector_accents_name_a_token_and_never_a_status_colour(): void
    {
        $this->seed_reference_data();

        foreach (Sector::all() as $sector) {
            $this->assertStringStartsWith('--c-', $sector->accent_token);
            $this->assertNotContains(
                $sector->accent_token,
                ['--c-green', '--c-green-dark', '--c-amber', '--c-red'],
                'A sector accent must not reuse a status colour.'
            );
        }
    }

    public function test_every_member_state_has_a_regulator(): void
    {
        $this->seed_reference_data();

        $this->assertSame(14, Authority::count());

        foreach (Country::with('authority')->get() as $country) {
            $this->assertNotNull($country->authority, $country->name.' has no authority.');
        }
    }

    public function test_every_seeded_authority_is_flagged_as_unverified(): void
    {
        $this->seed_reference_data();

        $this->assertSame(14, Authority::sample()->count());
        $this->assertSame(0, Authority::verified()->count());
    }

    public function test_no_authority_founding_year_is_invented(): void
    {
        $this->seed_reference_data();

        $this->assertSame(0, Authority::whereNotNull('established_year')->count());
    }

    public function test_every_authority_holds_at_least_one_sector_mandate(): void
    {
        $this->seed_reference_data();

        foreach (Authority::with('sectors')->get() as $authority) {
            $this->assertNotEmpty($authority->sectors, $authority->acronym.' has no sector mandate.');
        }
    }

    public function test_a_mandate_can_be_asked_about_by_sector(): void
    {
        $this->seed_reference_data();

        $icasa = Authority::where('acronym', 'ICASA')->sole();

        $this->assertTrue($icasa->regulates(Sector::TELECOMMUNICATIONS));
        $this->assertTrue($icasa->regulates(Sector::POSTAL));

        $postalRegulators = Sector::where('slug', Sector::POSTAL)->sole()->authorities;
        $this->assertGreaterThan(0, $postalRegulators->count());
    }

    public function test_committees_are_seeded_and_flagged_as_unverified(): void
    {
        $this->seed_reference_data();

        $this->assertSame(6, Committee::count());
        $this->assertSame(6, Committee::sample()->count());
        $this->assertSame(6, Committee::active()->count());
    }

    public function test_no_committee_chair_is_asserted(): void
    {
        $this->seed_reference_data();

        $this->assertSame(0, Committee::whereNotNull('chair_authority_id')->count());
    }

    public function test_a_committee_may_be_cross_sector(): void
    {
        $this->seed_reference_data();

        $this->assertTrue(Committee::where('slug', 'economics')->sole()->isCrossSector());
        $this->assertFalse(Committee::where('slug', 'postal-and-logistics')->sole()->isCrossSector());
    }

    public function test_committee_membership_is_recorded_with_a_role_and_the_sample_flag(): void
    {
        $this->seed_reference_data();

        $committee = Committee::with('countries')->where('slug', 'economics')->sole();

        $this->assertSame(14, $committee->countries->count());

        $member = $committee->countries->first();
        $this->assertSame('member', $member->pivot->role);
        $this->assertTrue((bool) $member->pivot->is_sample);
    }

    public function test_a_country_reaches_its_committees(): void
    {
        $this->seed_reference_data();

        $this->assertSame(6, Country::where('iso_code', 'zm')->sole()->committees()->count());
    }

    public function test_seeding_twice_does_not_duplicate_reference_data(): void
    {
        $this->seed_reference_data();
        $this->seed_reference_data();

        $this->assertSame(14, Country::count());
        $this->assertSame(3, Sector::count());
        $this->assertSame(14, Authority::count());
        $this->assertSame(6, Committee::count());
        $this->assertSame(14 * 6, \DB::table('committee_country')->count());
    }

    public function test_deleting_a_country_removes_its_authority_and_committee_seats(): void
    {
        $this->seed_reference_data();

        Country::where('iso_code', 'zm')->sole()->delete();

        $this->assertSame(13, Country::count());
        $this->assertSame(13, Authority::count());
        $this->assertSame(13 * 6, \DB::table('committee_country')->count());
    }

    public function test_a_country_may_restructure_its_regulator_and_the_old_one_survives(): void
    {
        $country = Country::factory()->create();
        $old = Authority::factory()->for($country)->create(['acronym' => 'OLD']);
        $new = Authority::factory()->for($country)->create(['acronym' => 'NEW']);

        $this->assertSame(2, $country->authorities()->count());
        $this->assertSame($new->id, $country->authority()->first()->id);
        $this->assertDatabaseHas('authorities', ['id' => $old->id]);
    }

    public function test_factory_made_authorities_are_sample_data_by_default(): void
    {
        $this->assertTrue(Authority::factory()->create()->is_sample);
        $this->assertFalse(Authority::factory()->verified()->create()->is_sample);
    }
}
