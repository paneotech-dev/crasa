<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Sector;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where the map goes.
 *
 * Before this page existed, clicking a member state scrolled the page a
 * little. These tests hold the map to being navigation: fourteen shapes,
 * fourteen destinations, and the destination carries facts the platform
 * holds rather than prose written for it.
 */
class MemberPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
    }

    public function test_every_member_state_the_map_links_to_resolves(): void
    {
        foreach (Country::pluck('iso_code') as $iso) {
            $this->get('/en/members/'.$iso)->assertOk();
        }
    }

    public function test_a_member_page_carries_the_regulator_its_acronym_and_its_sectors(): void
    {
        $country = Country::where('iso_code', 'bw')->firstOrFail();
        $authority = Authority::factory()->create([
            'country_id' => $country->id,
            'name' => 'Botswana Communications Regulatory Authority',
            'acronym' => 'BOCRA',
        ]);
        $authority->sectors()->attach(Sector::where('slug', 'broadcasting')->firstOrFail());

        $this->get('/en/members/bw')
            ->assertOk()
            ->assertSee('Botswana')
            ->assertSee('Botswana Communications Regulatory Authority')
            ->assertSee('BOCRA')
            ->assertSee('Broadcasting');
    }

    public function test_committee_seats_appear_with_the_role_the_state_holds(): void
    {
        $country = Country::where('iso_code', 'zm')->firstOrFail();
        $committee = Committee::create([
            'slug' => 'spectrum-working-group',
            'name' => 'Spectrum working group',
            'mandate' => 'Coordinates spectrum assignment across the region.',
            'is_active' => true,
        ]);
        $country->committees()->attach($committee, ['role' => 'chair']);

        $this->get('/en/members/zm')
            ->assertOk()
            ->assertSee('Spectrum working group')
            ->assertSee('Chair');
    }

    /**
     * A section with nothing in it is absent, not empty. A member state
     * page that says "0 committee seats" has reported a finding it has no
     * evidence for.
     */
    public function test_a_state_with_no_filed_figures_carries_no_figures_section(): void
    {
        $this->get('/en/members/ao')
            ->assertOk()
            ->assertDontSee('Figures filed');
    }

    public function test_filed_figures_appear_with_their_period_and_source(): void
    {
        $country = Country::where('iso_code', 'na')->firstOrFail();
        $indicator = Indicator::factory()->create([
            'name' => 'Fixed broadband subscriptions',
            'data_type' => 'numeric',
            'unit' => 'subscriptions',
        ]);
        IndicatorValue::factory()->create([
            'indicator_id' => $indicator->id,
            'country_id' => $country->id,
            'period' => '2024',
            'value_numeric' => 143210,
            'source_note' => 'CRAN annual report 2024.',
        ]);

        $this->get('/en/members/na')
            ->assertOk()
            ->assertSee('Fixed broadband subscriptions')
            ->assertSee('2024')
            ->assertSee('143,210')
            ->assertSee('CRAN annual report 2024.');
    }

    /**
     * Screen 08 draws these on its member profile. Working language comes
     * from a column populated since the country seed; the website and the
     * year come from the confirmation session, so they appear as each
     * member state confirms its own record.
     */
    public function test_the_profile_carries_the_fields_screen_08_draws(): void
    {
        $country = Country::where('iso_code', 'ao')->firstOrFail();
        Authority::factory()->create([
            'country_id' => $country->id,
            'name' => 'Instituto Angolano das Comunicacoes',
            'acronym' => 'INACOM',
            'website' => 'https://www.inacom.gov.ao',
            'established_year' => 1999,
        ]);

        $this->get('/en/members/ao')
            ->assertOk()
            ->assertSee('Working language')
            // Angola works in Portuguese, from countries.official_languages.
            ->assertSee('Portuguese')
            ->assertSee('Authority established')
            ->assertSee('1999')
            ->assertSee('www.inacom.gov.ao');
    }

    /**
     * A reader of a member state entry is entitled to know whether that
     * state has vouched for what is on the page. Showing unconfirmed
     * details as though they were confirmed makes a claim on a national
     * regulator's behalf.
     */
    public function test_neither_an_unconfirmed_nor_a_confirmed_profile_carries_a_banner(): void
    {
        $country = Country::where('iso_code', 'bw')->firstOrFail();
        $authority = Authority::factory()->create(['country_id' => $country->id]);

        // Owner's instruction, 15 October 2026: the confirmation state is kept
        // (is_sample, confirmed_at) and shown in the back office, not to readers.
        $this->get('/en/members/bw')->assertOk()->assertDontSee('not yet been confirmed');
        $this->assertNull($authority->fresh()->confirmed_at);

        $authority->update([
            'is_sample' => false,
            'confirmed_at' => now(),
            'confirmed_by_name' => 'A delegate',
        ]);

        $this->get('/en/members/bw')->assertOk()->assertDontSee('not yet been confirmed');
    }

    public function test_a_state_that_is_not_a_member_is_not_a_page(): void
    {
        // Madagascar and Seychelles are SADC members without a CRASA
        // regulator. They are drawn on the map for context and they are not
        // member states, so they must not resolve as ones.
        $this->get('/en/members/mg')->assertNotFound();
        $this->get('/en/members/xx')->assertNotFound();
    }
}
