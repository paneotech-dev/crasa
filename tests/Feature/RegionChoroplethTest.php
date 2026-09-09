<?php

namespace Tests\Feature;

use App\Models\Authority;
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
 * The map as a dataset view.
 *
 * The conditions that make a choropleth honest rather than decorative:
 * every state states its figure as text, a state that has not filed is a
 * different kind of thing from a state that filed a low number, the units
 * are on the page, the whole thing is a document before a script runs, and
 * a reader who cannot separate four greens loses nothing.
 */
class RegionChoroplethTest extends TestCase
{
    use RefreshDatabase;

    private Indicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);

        $telecoms = Sector::where('slug', 'telecommunications')->firstOrFail();

        foreach (Country::all() as $country) {
            $authority = Authority::factory()->create(['country_id' => $country->id]);
            $authority->sectors()->attach($telecoms);
        }

        $this->indicator = Indicator::factory()->create([
            'slug' => 'mobile-penetration',
            'name' => 'Mobile penetration',
            'unit' => 'subscriptions per 100 inhabitants',
            'data_type' => 'percentage',
            'category' => 'market',
        ]);

        // Eleven states filed, three did not. The three are the point of the
        // view: a member state that has not filed has to be visible as such.
        $filed = Country::orderBy('name')->take(11)->get();

        foreach ($filed as $index => $country) {
            IndicatorValue::factory()->create([
                'indicator_id' => $this->indicator->id,
                'country_id' => $country->id,
                'period' => '2025',
                'value_numeric' => 10 + ($index * 15),
            ]);
        }
    }

    public function test_the_map_shades_by_an_indicator_named_in_the_query_string(): void
    {
        // The deep link is the contract. A regulator pastes this into an
        // email and the recipient has to see the same fourteen figures.
        $html = $this->get('/en/members?indicator=mobile-penetration')->assertOk()->getContent();

        $this->assertStringContainsString('c-map-region--shaded', $html);
        $this->assertStringContainsString('Mobile penetration', $html);
        $this->assertStringContainsString('subscriptions per 100 inhabitants', $html);
    }

    /**
     * SC 1.4.1. Take the colour away and the map still answers the
     * question, because every shape carries its own figure.
     */
    public function test_every_member_state_states_its_figure_as_text_not_only_as_a_shade(): void
    {
        $html = $this->get('/en/members?indicator=mobile-penetration')->assertOk()->getContent();

        // Strip every fill, class and style attribute. What is left is what
        // a reader in mono, in print, or on a washed-out projector has.
        $colourless = preg_replace('~(fill|class|style)="[^"]*"~', '', $html);

        foreach (Country::orderBy('name')->take(11)->get() as $index => $country) {
            $expected = number_format(10 + ($index * 15)).'%';

            $this->assertStringContainsString(
                $expected,
                $colourless,
                $country->name.' has a shade but no stated figure.'
            );
        }

        // And the three that did not file say so in words.
        $this->assertStringContainsString('Not reported', $colourless);
    }

    public function test_a_state_that_has_not_filed_is_hatched_and_never_the_lightest_shade(): void
    {
        $html = $this->get('/en/members?indicator=mobile-penetration')->assertOk()->getContent();

        $missing = Country::orderBy('name')->skip(11)->take(3)->get();
        $this->assertCount(3, $missing);

        foreach ($missing as $country) {
            // data-band="none", not data-band="0". "Not reported" and
            // "lowest value" are different claims about a member state.
            $this->assertMatchesRegularExpression(
                '~data-iso="'.$country->iso_code.'"\s+data-band="none"~',
                $html,
                $country->name.' filed nothing and is not marked as unreported.'
            );
        }

        // The hatch is a texture, not a fourth green.
        $this->assertStringContainsString('id="c-map-hatch"', $html);
        $this->assertStringContainsString('c-map-region__swatch--missing', $html);
    }

    public function test_the_accessible_name_of_every_state_carries_its_value(): void
    {
        $html = $this->get('/en/members?indicator=mobile-penetration')->assertOk()->getContent();

        $first = Country::orderBy('name')->first();
        $missing = Country::orderBy('name')->skip(11)->first();

        $this->assertStringContainsString('aria-label="'.$first->name.', 10%."', $html);
        $this->assertStringContainsString('aria-label="'.$missing->name.', Not reported."', $html);
    }

    public function test_the_table_equivalent_carries_all_fourteen_figures(): void
    {
        $html = $this->get('/en/members?indicator=mobile-penetration')->assertOk()->getContent();

        $this->assertStringContainsString('Read the figures as a table', $html);

        // The table is in the document, not fetched on demand. A reader who
        // opens the disclosure gets it with no request and no script.
        $table = substr($html, strpos($html, 'c-map-region__table'));

        foreach (Country::all() as $country) {
            $this->assertStringContainsString($country->name, $table, $country->name.' is missing from the table equivalent.');
        }
    }

    /**
     * The map compares like with like, and picks the basis the most member
     * states share. Two states filing 2026 early must not turn a well-filed
     * 2025 into a map of twelve hatched countries.
     */
    public function test_the_map_reads_the_period_the_most_member_states_filed_not_simply_the_latest(): void
    {
        $indicator = Indicator::factory()->create(['slug' => 'coverage', 'name' => 'Coverage', 'data_type' => 'numeric']);

        // Two states are a year ahead of the other nine.
        $countries = Country::orderBy('name')->get();

        foreach ($countries->take(9) as $country) {
            IndicatorValue::factory()->create([
                'indicator_id' => $indicator->id,
                'country_id' => $country->id,
                'period' => '2024',
                'value_numeric' => 50,
            ]);
        }

        foreach ($countries->skip(9)->take(2) as $country) {
            IndicatorValue::factory()->create([
                'indicator_id' => $indicator->id,
                'country_id' => $country->id,
                'period' => '2025',
                'value_numeric' => 60,
            ]);
        }

        $html = $this->get('/en/members?indicator=coverage')->assertOk()->getContent();

        $this->assertStringContainsString('Figures are for 2024.', $html);
        $this->assertStringContainsString('5 member states have not filed', $html);
    }

    public function test_the_legend_states_the_units_and_how_many_states_did_not_file(): void
    {
        $html = $this->get('/en/members?indicator=mobile-penetration')->assertOk()->getContent();

        $this->assertStringContainsString('subscriptions per 100 inhabitants', $html);
        $this->assertStringContainsString('Figures are for 2025.', $html);
        $this->assertStringContainsString('3 member states have not filed', $html);
    }

    /**
     * The second dataset, and the reason the map is on the page at all for
     * the Secretariat. Nothing is hatched here: filing nothing is a
     * measurement of zero, not an absence of one.
     */
    public function test_the_completeness_dataset_shades_every_state_including_those_at_zero(): void
    {
        $html = $this->get('/en/members?indicator=completeness')->assertOk()->getContent();

        $this->assertStringContainsString('Data completeness', $html);
        $this->assertStringContainsString('0%', $html);
        $this->assertStringContainsString('a member state that has filed nothing takes the palest shade', $html);
        $this->assertStringNotContainsString('c-map-region__swatch--missing', $html);
    }

    public function test_an_unknown_indicator_falls_back_to_membership_rather_than_erroring(): void
    {
        // A stale link in a two-year-old minute must still open the page.
        $html = $this->get('/en/members?indicator=not-an-indicator')->assertOk()->getContent();

        $this->assertStringContainsString('c-map-region--plain', $html);
        $this->assertStringNotContainsString('c-map-region--shaded', $html);
    }

    public function test_an_indicator_nobody_has_filed_is_not_offered_in_the_selector(): void
    {
        Indicator::factory()->create(['slug' => 'never-filed', 'name' => 'Never filed', 'category' => 'market']);

        $html = $this->get('/en/members')->assertOk()->getContent();

        $this->assertStringContainsString('value="mobile-penetration"', $html);
        $this->assertStringNotContainsString('value="never-filed"', $html);
    }

    /**
     * Units are stored as codes so they can be matched on import and
     * compared across member states. A reader must never see the code.
     * This surfaced on the map's legend and was true of the comparator, the
     * completeness grid, the import errors and the exports as well.
     */
    public function test_the_reader_sees_a_unit_in_words_and_never_the_stored_code(): void
    {
        $indicator = Indicator::factory()->create([
            'slug' => 'mobile-subs',
            'name' => 'Mobile-cellular subscriptions',
            'unit' => 'subscriptions_per_100',
            'data_type' => 'numeric',
        ]);
        IndicatorValue::factory()->create([
            'indicator_id' => $indicator->id,
            'country_id' => Country::first()->id,
            'period' => '2025',
            'value_numeric' => 84,
        ]);

        $html = $this->get('/en/members?indicator=mobile-subs')->assertOk()->getContent();

        $this->assertStringContainsString('subscriptions per 100 inhabitants', $html);
        $this->assertStringNotContainsString('subscriptions_per_100', $html);
    }

    public function test_a_unit_with_no_translation_still_reads_as_words(): void
    {
        // The Secretariat adds indicators. One with an unseen unit must
        // degrade to English words, not to a slug with underscores in it.
        $indicator = Indicator::factory()->create([
            'slug' => 'spectrum-fee',
            'name' => 'Spectrum fee',
            'unit' => 'currency_units_per_mhz',
            'data_type' => 'numeric',
        ]);

        $this->assertSame('currency units per mhz', $indicator->unitLabel());
    }

    /**
     * The floor. No script has run at this point in any of these tests,
     * because none of them execute JavaScript. Everything above is what a
     * reader with scripting off receives.
     */
    public function test_the_dataset_selector_is_a_real_form_that_works_without_a_script(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<form[^>]+method="get"[^>]+data-map-control~', $html);
        $this->assertStringContainsString('name="indicator"', $html);
        $this->assertStringContainsString('data-live-submit', $html);
    }
}
