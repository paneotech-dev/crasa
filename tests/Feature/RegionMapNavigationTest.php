<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Support\RegionGeometry;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The map as a navigation surface.
 *
 * Two things here are decisions rather than defaults and would be
 * reasonable-looking to undo, so they are held by test with the reason
 * attached.
 */
class RegionMapNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
    }

    /**
     * Keyboard order is alphabetical by country name, and it is alphabetical
     * because tab order follows DOM order here — no positive tabindex, which
     * would put focus order and visual order into conflict under SC 2.4.3.
     *
     * Alphabetical rather than geographic: it is the order the list under
     * the map reads in, the order the comparator's country selector reads
     * in, and the order CRASA's own documents list member states in. A
     * geographic order asks the reader to hold a map of Southern Africa,
     * which is precisely what a reader who cannot see the map does not have.
     */
    public function test_the_map_is_reachable_by_keyboard_in_alphabetical_order(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        preg_match_all('~<a\s+class="c-map-region__member"[^>]*data-iso="([a-z]{2})"~', $html, $matches);

        $onMap = $matches[1];
        $this->assertCount(12, $onMap, 'The twelve mainland member states should be the twelve focusable shapes.');

        $expected = Country::whereIn('iso_code', $onMap)->orderBy('name')->pluck('iso_code')->all();

        $this->assertSame(
            $expected,
            $onMap,
            'The shapes are written in the order they are tabbed. That order is alphabetical by country name.'
        );
    }

    /**
     * Four member states get their own enlarged panel, and they are in the
     * same alphabetical-by-name order as everything else on this map.
     *
     * Comoros and Mauritius because at region scale each island is a few
     * pixels across. Lesotho and Eswatini because at 375px they are too
     * small to read a figure off or aim a thumb at, even though they are on
     * the map and correctly placed. That is the answer to the smallest
     * states rather than zoom, which would put the map behind a control the
     * reader has to discover.
     */
    public function test_the_enlarged_panel_carries_the_four_smallest_in_alphabetical_order(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        preg_match_all('~<a\s+class="c-map-region__inset"[^>]*data-iso="([a-z]{2})"~s', $html, $matches);

        // Comoros, Eswatini, Lesotho, Mauritius. Not the order the geometry
        // file lists them and not alphabetical by ISO code.
        $this->assertSame(['km', 'sz', 'ls', 'mu'], $matches[1]);
    }

    /**
     * The mainland pair keep their place on the map as well. An enlargement
     * is an additional way to reach a member state, not a removal from the
     * map, and Lesotho is not an island.
     */
    public function test_the_enlarged_mainland_states_are_still_drawn_on_the_map(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        foreach (['ls', 'sz'] as $iso) {
            $this->assertMatchesRegularExpression(
                '~c-map-region__member"[^>]*data-iso="'.$iso.'"~',
                $html,
                strtoupper($iso).' was moved into the panel instead of being added to it.'
            );
        }
    }

    /**
     * The enlargement is the map's own geometry reframed, not a second
     * drawing of a country. Nothing here invents a coordinate.
     */
    public function test_an_enlarged_shape_is_the_same_path_the_map_draws(): void
    {
        $map = config('region-map');
        $html = $this->get('/en/members')->assertOk()->getContent();

        foreach (['ls', 'sz'] as $iso) {
            $this->assertStringContainsString(
                $map['members'][$iso]['d'],
                $html,
                strtoupper($iso).' is enlarged from a path the map does not draw.'
            );

            $box = RegionGeometry::viewBoxFor($map['members'][$iso]['d']);
            $this->assertNotNull($box);
            $this->assertStringContainsString('viewBox="'.$box.'"', $html);
        }
    }

    /**
     * "An inset is a rendering decision, not a second class of member."
     *
     * Every rule the mainland shapes take, the chips take, and they take it
     * through the identical attribute so a change to one cannot miss the
     * other: data-iso drives the hover detail, data-band drives the fill.
     */
    public function test_an_inset_chip_carries_the_same_attributes_and_the_same_destination_as_a_shape(): void
    {
        $indicator = Indicator::factory()->create(['slug' => 'reach', 'name' => 'Reach', 'data_type' => 'numeric']);

        foreach (Country::all() as $index => $country) {
            Authority::factory()->create(['country_id' => $country->id]);
            IndicatorValue::factory()->create([
                'indicator_id' => $indicator->id,
                'country_id' => $country->id,
                'period' => '2025',
                'value_numeric' => ($index + 1) * 10,
            ]);
        }

        $html = $this->get('/en/members?indicator=reach')->assertOk()->getContent();

        foreach (['km', 'mu'] as $iso) {
            // The same destination a mainland shape has.
            $this->assertStringContainsString('href="http://localhost/en/members/'.$iso.'"', $html);

            // The same band attribute, so the same fill rule applies.
            $this->assertMatchesRegularExpression(
                '~c-map-region__inset"\s+href="[^"]+"\s+data-iso="'.$iso.'"\s+data-band="[0-3]"~',
                $html,
                strtoupper($iso).' is shaded by a different mechanism from the mainland twelve.'
            );

            // The same hover-detail hook.
            $this->assertStringContainsString('data-for="'.$iso.'"', $html);
        }
    }

    /**
     * The hover and focus detail is CSS, keyed off the same data-iso the
     * link carries. If the fourteen selectors and the fourteen cards ever
     * fall out of step the panel silently stops working, and nothing else
     * on the page would show it.
     */
    public function test_every_member_state_has_both_a_detail_card_and_the_rule_that_reveals_it(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();
        $css = file_get_contents(public_path('css/blocks.css'));

        foreach (Country::pluck('iso_code') as $iso) {
            $this->assertStringContainsString('data-for="'.$iso.'"', $html, $iso.' has no detail card.');
            $this->assertStringContainsString(
                '.c-map-region__stage:has([data-iso="'.$iso.'"]:is(:hover,:focus-visible)) .c-map-region__card[data-for="'.$iso.'"]',
                $css,
                $iso.' has a detail card that nothing reveals.'
            );
        }
    }

    public function test_the_detail_panel_is_hidden_from_assistive_technology_because_the_link_already_says_it(): void
    {
        $country = Country::where('iso_code', 'bw')->firstOrFail();
        Authority::factory()->create([
            'country_id' => $country->id,
            'name' => 'Botswana Communications Regulatory Authority',
            'acronym' => 'BOCRA',
        ]);

        $html = $this->get('/en/members')->assertOk()->getContent();

        // Fourteen stacked cards read out in sequence would be fourteen
        // repetitions of the page, so the panel is aria-hidden and the fact
        // lives in the link's accessible name instead.
        $this->assertMatchesRegularExpression('~c-map-region__detail"\s+aria-hidden="true"~', $html);
        $this->assertStringContainsString(
            'aria-label="Botswana. Botswana Communications Regulatory Authority."',
            $html
        );
    }
}
