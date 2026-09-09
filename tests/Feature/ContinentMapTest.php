<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Services\MapExport;
use App\Support\MapView;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The map, once the continent arrived behind it.
 *
 * RegionMap.dc.html fits the box to the fourteen member states and fills the
 * emptiness around them with a grid of sand rules. That is what a map draws
 * when it has no geography. This replaces the rules with the real outlines
 * at the same projection, which is a disclosed departure from the approved
 * screen and is recorded in docs/QUESTIONS.md.
 */
class ContinentMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);

        $indicator = Indicator::factory()->create([
            'slug' => 'reach', 'name' => 'Reach', 'data_type' => 'numeric',
        ]);

        foreach (Country::all() as $index => $country) {
            Authority::factory()->create(['country_id' => $country->id]);
            IndicatorValue::factory()->create([
                'indicator_id' => $indicator->id,
                'country_id' => $country->id,
                'period' => '2024',
                'value_numeric' => ($index + 1) * 7,
            ]);
        }
    }

    private function map(): string
    {
        return $this->get('/en/members')->assertOk()->getContent();
    }

    public function test_exactly_fourteen_shapes_are_interactive(): void
    {
        $html = $this->map();

        // Twelve mainland shapes on the map itself. Comoros and Mauritius
        // are too small to hit at any scale and reach the reader as the
        // enlarged chips beside it, so the count is split but the total is
        // the membership.
        preg_match_all('#/en/members/([a-z]{2})\b#', $html, $matches);
        $reachable = array_unique($matches[1]);
        sort($reachable);

        $this->assertCount(14, $reachable, 'Every member state must be reachable and no one else.');
        $this->assertSame(
            ['ao', 'bw', 'cd', 'km', 'ls', 'mu', 'mw', 'mz', 'na', 'sz', 'tz', 'za', 'zm', 'zw'],
            $reachable,
        );
    }

    public function test_the_continent_is_inert(): void
    {
        $html = $this->map();

        preg_match('#<g class="c-map-region__continent"[^>]*>(.*?)</g>#s', $html, $group);

        $this->assertNotEmpty($group, 'The continent group is not rendered.');
        $this->assertStringContainsString('aria-hidden="true"', $group[0]);
        $this->assertStringNotContainsString('<a ', $group[1], 'A non-member outline must not be a link.');
        $this->assertStringNotContainsString('href', $group[1]);
        $this->assertStringNotContainsString('<text', $group[1], 'A non-member outline must not be labelled.');

        $this->assertSame(
            38,
            substr_count($group[1], '<path'),
            'The continent is 38 outlines: Africa less the fourteen members, less Madagascar and Seychelles.',
        );

        $css = file_get_contents(public_path('css/blocks.css'));
        $this->assertMatchesRegularExpression(
            '/\.c-map-region__continent path \{[^}]*pointer-events: none/',
            $css,
            'The continent must not swallow a click aimed at a member state.',
        );
    }

    public function test_nothing_is_drawn_outside_the_box(): void
    {
        $map = config('region-map');

        $x0 = $map['min_x'];
        $y0 = $map['min_y'];
        $x1 = $x0 + $map['width'];
        $y1 = $y0 + $map['height'];

        $paths = array_values($map['continent']);

        foreach ($map['members'] as $iso => $shape) {
            if ($shape['d'] !== null) {
                $paths[] = $shape['d'];
            }
        }

        foreach ($map['context'] as $context) {
            $paths[] = $context['d'];
        }

        foreach ($paths as $path) {
            preg_match_all('/-?\d+(?:\.\d+)?/', $path, $numbers);
            $values = array_map('floatval', $numbers[0]);

            foreach ($values as $index => $value) {
                if ($index % 2 === 0) {
                    $this->assertGreaterThanOrEqual($x0, $value, 'A shape runs off the left of the box.');
                    $this->assertLessThanOrEqual($x1, $value, 'A shape runs off the right of the box.');
                } else {
                    $this->assertGreaterThanOrEqual($y0, $value, 'A shape runs off the top of the box.');
                    $this->assertLessThanOrEqual($y1, $value, 'A shape runs off the bottom of the box.');
                }
            }
        }
    }

    public function test_no_member_path_moved_when_the_box_grew(): void
    {
        // The box opened around the members. If a member coordinate had
        // shifted, every label placed at a fixed point would be wrong and
        // the export would not match the page.
        $map = config('region-map');

        $this->assertStringStartsWith('M400,526.25', $map['members']['ls']['d'], 'Lesotho moved.');
        $this->assertSame([366, 556], $map['members']['ls']['label']);
        $this->assertStringStartsWith('M444.5,490.25', $map['members']['sz']['d'], 'Eswatini moved.');
    }

    public function test_every_value_survives_with_the_fills_stripped(): void
    {
        // SC 1.4.1: colour is not the only means of conveying information.
        // Strip every fill and the figure must still be readable off the
        // map, because the value is text on the shape and not a shade.
        $html = $this->map();

        $stripped = preg_replace('/fill="[^"]*"/', '', $html);
        $stripped = preg_replace('/\.c-map-region[^{]*\{[^}]*\}/', '', $stripped);

        foreach (Country::all() as $country) {
            $this->assertStringContainsString(
                strtoupper($country->iso_code),
                $stripped,
                "{$country->name} disappears when the fills are stripped.",
            );
        }
    }

    public function test_the_continent_is_recorded_in_the_licence_register(): void
    {
        $rows = array_map('str_getcsv', file(base_path('docs/LICENCES.csv')));
        $header = array_shift($rows);

        $sources = [];

        foreach ($rows as $row) {
            $sources[] = array_combine($header, $row);
        }

        $continent = array_filter($sources, fn ($r) => str_contains($r['where used'], 'continent'));

        $this->assertNotEmpty($continent, 'The continent geometry is not in docs/LICENCES.csv.');

        $row = reset($continent);
        $this->assertStringContainsString('Natural Earth', $row['source']);
        $this->assertStringContainsString('Public domain', $row['licence']);
    }

    public function test_the_export_names_every_member_state(): void
    {
        // $ordered is alphabetical, so Lesotho was drawn before South
        // Africa. Lesotho's label sits outside its own border because the
        // country cannot hold it, and South Africa's fill landed on top:
        // the printed map showed thirteen named states and one blank hole.
        // Eswatini lost the front of "ESCCOM" to Mozambique the same way.
        $view = MapView::resolve(Country::with('authority')->get(), 'reach');
        $svg = app(MapExport::class)->svg($view, 'https://example.test/en/members');

        foreach (Country::all() as $country) {
            $this->assertStringContainsString(
                '>'.strtoupper($country->iso_code).'</text>',
                $svg,
                "{$country->name} has no label on the exported map.",
            );
        }
    }

    public function test_the_export_draws_every_shape_before_any_label(): void
    {
        $view = MapView::resolve(Country::with('authority')->get(), 'reach');
        $svg = app(MapExport::class)->svg($view, 'https://example.test/en/members');

        // The member group runs from the first green fill to the closing
        // tag. Inside it, no path may follow a label.
        preg_match('#<g id="region".*?</g>#s', $svg, $group);
        $this->assertNotEmpty($group);

        $firstLabel = strpos($group[0], '<text');
        $lastMemberPath = strrpos($group[0], 'stroke="#0E5536"');

        $this->assertNotFalse($firstLabel);
        $this->assertNotFalse($lastMemberPath);
        $this->assertLessThan(
            $firstLabel,
            $lastMemberPath,
            'A member state is filled after a label is written, so it paints over it.',
        );
    }
}
