<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Services\MapExport;
use App\Support\CompletenessMatrix;
use App\Support\MapView;
use App\Support\RegionGeometry;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four gaps in the map, from the report at the end of the last run.
 *
 * Change over time and composite categories stay out of scope and are
 * logged in docs/OPEN_ITEMS.md.
 */
class MapGapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);

        $indicator = Indicator::factory()->create(['slug' => 'reach', 'name' => 'Reach', 'data_type' => 'numeric']);

        foreach (Country::all() as $index => $country) {
            Authority::factory()->create(['country_id' => $country->id]);

            IndicatorValue::factory()->create([
                'indicator_id' => $indicator->id,
                'country_id' => $country->id,
                // Staggered periods, so freshness has something to report.
                'period' => (string) (2021 + ($index % 5)),
                'value_numeric' => ($index + 1) * 7,
            ]);
        }
    }

    /* a. The dropdown alternative ------------------------------------------- */

    /**
     * Screen 08 says "Select a member state" beside the map. A map is not a
     * control everyone can use: a reader who knows they want Zambia should
     * not have to find Zambia on a map, and a reader who cannot see the map
     * has no route through it at all.
     */
    public function test_the_map_offers_a_dropdown_as_well(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        $this->assertStringContainsString('name="iso"', $html);
        $this->assertStringContainsString('id="map-state"', $html);

        // A real form with a real action. No script involved.
        $this->assertMatchesRegularExpression('~<form[^>]+action="[^"]*/en/member"~', $html);

        foreach (Country::pluck('iso_code') as $iso) {
            $this->assertStringContainsString('<option value="'.$iso.'"', $html, $iso.' is not offered in the dropdown.');
        }
    }

    public function test_the_dropdown_lands_on_the_same_url_the_map_links_to(): void
    {
        $this->get('/en/member?iso=zm')->assertRedirect('http://localhost/en/members/zm');

        // A code we do not recognise goes back to the membership page. The
        // reader chose from a list we rendered, so an unknown value is our
        // problem and not theirs.
        $this->get('/en/member?iso=qq')->assertRedirect('http://localhost/en/members');
        $this->get('/en/member')->assertRedirect('http://localhost/en/members');
    }

    /* b. Freshness ----------------------------------------------------------- */

    /**
     * A shaded map with no freshness signal invites a reader to treat a 2019
     * return as current. The completeness grid has carried this since
     * section 19 and the map had no equivalent.
     */
    public function test_the_map_states_how_recent_each_member_states_filing_is(): void
    {
        $html = $this->get('/en/members?indicator=reach')->assertOk()->getContent();
        $table = substr($html, strpos($html, 'c-map-region__table'));

        $this->assertStringContainsString('c-pill--validated', $table, 'No current filing is marked as current.');
        $this->assertStringContainsString('c-pill--error', $table, 'No stale filing is marked as stale.');
    }

    public function test_freshness_reads_the_same_scale_the_completeness_grid_uses(): void
    {
        $view = MapView::resolve(Country::with('authority')->get(), 'reach');

        foreach ($view->entries as $entry) {
            $this->assertContains($entry->freshness(), ['current', 'ageing', 'stale', 'none']);
            $this->assertSame(
                CompletenessMatrix::freshnessOf($entry->latestPeriod),
                $entry->freshness()
            );
        }
    }

    /* c. The smallest member states ------------------------------------------ */

    /**
     * At 375px Lesotho and Eswatini are a few pixels across: too small to
     * read a figure off and too small to aim a thumb at. They get the panel
     * Comoros and Mauritius have, rather than a zoom control the reader has
     * to discover.
     */
    public function test_the_smallest_member_states_are_shown_at_larger_scale(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        foreach (RegionGeometry::ENLARGED as $iso) {
            $this->assertMatchesRegularExpression(
                '~c-map-region__inset"[^>]*data-iso="'.$iso.'"~',
                $html,
                strtoupper($iso).' is not shown at larger scale.'
            );
        }

        // Five since 9 September 2026: Malawi joined when the map started
        // labelling by name and its shape proved too narrow to carry one.
        $this->assertCount(5, RegionGeometry::ENLARGED);
    }

    /**
     * The enlargement is the map's own geometry reframed. Nothing invents a
     * coordinate, so an enlarged Lesotho is the same Lesotho.
     */
    public function test_an_enlargement_frames_the_real_shape(): void
    {
        $map = config('region-map');
        $box = RegionGeometry::viewBoxFor($map['members']['ls']['d']);

        $this->assertNotNull($box);
        [$x, $y, $w, $h] = array_map('floatval', explode(' ', $box));

        // Lesotho sits inside South Africa, well down and right of the
        // origin, and is a small country. A viewBox that framed the whole
        // map would mean the parser had failed.
        $this->assertGreaterThan(300, $x);
        $this->assertGreaterThan(400, $y);
        $this->assertLessThan(100, $w);
        $this->assertLessThan(100, $h);
    }

    public function test_a_path_with_no_coordinates_frames_nothing_rather_than_dividing_by_zero(): void
    {
        $this->assertNull(RegionGeometry::viewBoxFor(''));
        $this->assertNull(RegionGeometry::viewBoxFor('M'));
    }

    /* d. Export --------------------------------------------------------------- */

    public function test_the_shaded_map_exports_as_png_and_pdf(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick is not available.');
        }

        $png = $this->get('/en/members/export.png?indicator=reach')->assertOk();
        $this->assertSame('image/png', $png->headers->get('content-type'));
        $this->assertStringStartsWith("\x89PNG", $png->getContent());

        $pdf = $this->get('/en/members/export.pdf?indicator=reach')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    /**
     * The exported document has to carry all fourteen. A reader counting
     * shapes on a printed map and finding twelve concludes CRASA has twelve
     * members, which is the one thing a membership map must never suggest.
     */
    public function test_the_exported_map_carries_every_member_state(): void
    {
        $view = MapView::resolve(Country::with('authority')->get(), 'reach');
        $svg = app(MapExport::class)->svg($view, 'https://example.test/en/members?indicator=reach');

        foreach (Country::all() as $country) {
            $this->assertStringContainsString(
                $country->name,
                $svg,
                $country->name.' is missing from the exported map.'
            );
        }

        // Every figure as text, the freshness column, and the permalink.
        $this->assertStringContainsString('url(#hatch)', $svg);
        $this->assertStringContainsString('https://example.test/en/members?indicator=reach', $svg);
    }

    /**
     * Brief D1, 29 October 2026: the exported map carries the islands as
     * the page does. Comoros and Mauritius as dots where they lie,
     * Madagascar and Seychelles named in outline as non-members, and the
     * Seychelles card beside the smallest members.
     */
    public function test_the_exported_map_carries_the_islands_and_the_non_members(): void
    {
        $view = MapView::resolve(Country::with('authority')->get(), null);
        $svg = app(MapExport::class)->svg($view, 'https://example.test/en/members');
        $map = config('region-map');

        foreach (['km', 'mu'] as $iso) {
            $this->assertStringContainsString('<circle cx="'.$map['members'][$iso]['dot'][0].'" cy="'.$map['members'][$iso]['dot'][1].'" r="7"', $svg);
        }

        $this->assertStringContainsString('>Madagascar</text>', $svg);
        $this->assertStringContainsString('>Seychelles</text>', $svg);
        $this->assertStringContainsString('SADC member state, not a CRASA member', $svg);
        $this->assertStringNotContainsString('fill="#E5E0D5" stroke="#908977"', $svg, 'A non-member is filled.');
    }

    public function test_an_unknown_export_format_is_not_a_route(): void
    {
        $this->get('/en/members/export.svg')->assertNotFound();
        $this->get('/en/members/export.docx')->assertNotFound();
    }
}
