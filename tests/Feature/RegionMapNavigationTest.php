<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
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

    public function test_the_island_chips_are_alphabetical_among_themselves(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        preg_match_all('~<a\s+class="c-map-region__inset"[^>]*data-iso="([a-z]{2})"~s', $html, $matches);

        // Comoros then Mauritius. Not the order the geometry file lists them.
        $this->assertSame(['km', 'mu'], $matches[1]);
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
