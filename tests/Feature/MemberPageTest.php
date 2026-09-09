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

    public function test_a_state_that_is_not_a_member_is_not_a_page(): void
    {
        // Madagascar and Seychelles are SADC members without a CRASA
        // regulator. They are drawn on the map for context and they are not
        // member states, so they must not resolve as ones.
        $this->get('/en/members/mg')->assertNotFound();
        $this->get('/en/members/xx')->assertNotFound();
    }
}
