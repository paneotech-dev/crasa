<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Services\MapExport;
use App\Support\MapView;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The map's integrity, at the approved box.
 *
 * The whole continent sat behind this map for a few hours on 9 September
 * 2026 and was reverted: it cost every member state 28 per cent of its size
 * for context nobody had asked for. What survived the revert is everything
 * that had nothing to do with the continent -- the colours and sizes as
 * attributes rather than only as CSS, the labels drawn after every shape,
 * the legend in both modes -- because those were fixing real faults that a
 * stale stylesheet had merely exposed.
 *
 * This holds the approved box and the things that make it survive a
 * stylesheet that does not arrive.
 */
class MapIntegrityTest extends TestCase
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

    public function test_the_box_is_the_one_the_screen_draws(): void
    {
        // RegionMap.dc.html line 12: viewBox="0 0 820 660".
        $map = config('region-map');

        $this->assertSame(0, $map['min_x']);
        $this->assertSame(0, $map['min_y']);
        $this->assertSame(820, $map['width']);
        $this->assertSame(660, $map['height']);

        $this->assertArrayNotHasKey(
            'continent',
            $map,
            'The continent is back. It was reverted on 9 September 2026.',
        );

        $this->assertStringContainsString('viewBox="0 0 820 660"', $this->map());
    }

    public function test_nothing_is_drawn_outside_the_box(): void
    {
        $map = config('region-map');

        $x0 = $map['min_x'];
        $y0 = $map['min_y'];
        $x1 = $x0 + $map['width'];
        $y1 = $y0 + $map['height'];

        $paths = [];

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

    /**
     * The shapes stay where they are, whatever they are drawn from.
     *
     * Every label on this map is a fixed coordinate pair, so a member state
     * that shifted would take its own label off it and the export would
     * stop matching the page. The box has opened and closed, and the source
     * has gone from 1:110m to 1:50m; through all of it each shape has to
     * land in the same place.
     *
     * Checked as a bounding box rather than a literal path. The path text
     * changes with the source by definition -- that is what a finer
     * resolution is -- and pinning it would only ever assert which file the
     * geometry came from.
     */
    public function test_every_member_shape_lands_where_its_label_expects(): void
    {
        $map = config('region-map');

        // Measured from the 1:110m build, which the labels were placed
        // against. Four pixels of tolerance: 1:50m traces a coastline more
        // closely, so an extent may gain a pixel or two of real headland.
        $expected = [
            'ao' => [151, 329, 154, 354], 'bw' => [269, 406, 349, 492],
            'cd' => [158, 433, 13, 286], 'sz' => [424, 445, 473, 500],
            'ls' => [372, 406, 520, 554], 'mw' => [453, 500, 224, 340],
            'mz' => [417, 571, 240, 492], 'na' => [151, 346, 338, 528],
            'za' => [218, 456, 416, 626], 'tz' => [405, 566, 104, 260],
            'zm' => [298, 468, 208, 354], 'zw' => [346, 458, 316, 422],
        ];

        foreach ($expected as $iso => [$x0, $x1, $y0, $y1]) {
            preg_match_all('/-?\d+(?:\.\d+)?/', $map['members'][$iso]['d'], $numbers);

            $values = array_map('floatval', $numbers[0]);
            $xs = [];
            $ys = [];

            foreach ($values as $index => $value) {
                $index % 2 === 0 ? $xs[] = $value : $ys[] = $value;
            }

            foreach ([
                ['left', min($xs), $x0], ['right', max($xs), $x1],
                ['top', min($ys), $y0], ['bottom', max($ys), $y1],
            ] as [$edge, $actual, $want]) {
                $this->assertEqualsWithDelta(
                    $want,
                    $actual,
                    4.0,
                    strtoupper($iso)." has moved: its {$edge} edge is at {$actual} and the labels "
                    ."were placed against {$want}.",
                );
            }
        }

        // And the labels themselves are untouched by any of it.
        $this->assertSame([366, 556], $map['members']['ls']['label']);
        $this->assertSame([462, 492], $map['members']['sz']['label']);
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

    public function test_the_geometry_is_recorded_in_the_licence_register(): void
    {
        $rows = array_map('str_getcsv', file(base_path('docs/LICENCES.csv')));
        $header = array_shift($rows);

        $sources = [];

        foreach ($rows as $row) {
            $sources[] = array_combine($header, $row);
        }

        $geometry = array_filter($sources, fn ($r) => str_contains($r['where used'], 'region-map.php'));

        $this->assertNotEmpty($geometry, 'The map geometry is not in docs/LICENCES.csv.');

        $row = reset($geometry);
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

        // The export labels by name now, matching the page from 768 up,
        // except for the three states whose shapes cannot hold a name.
        foreach (Country::all() as $country) {
            $expected = in_array($country->iso_code, ['ls', 'sz', 'mw'], true)
                ? strtoupper($country->iso_code)
                : $country->name;

            $this->assertStringContainsString(
                '>'.$expected.'</text>',
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

    /* Survives a stylesheet that does not arrive ---------------------------- */

    public function test_every_shape_carries_its_colour_as_an_attribute(): void
    {
        // An SVG path with no fill from any source renders black. The
        // colours lived only in the stylesheet, so the day the stylesheet
        // was stale the map was a continent of solid black polygons.
        // RegionMap.dc.html sets them as attributes for this reason.
        $html = $this->map();

        preg_match('#<svg[^>]*class="c-map-region__svg".*?</svg>#s', $html, $svg);
        $this->assertNotEmpty($svg);

        preg_match_all('/<path\b[^>]*>/', $svg[0], $paths);

        foreach ($paths[0] as $path) {
            $this->assertMatchesRegularExpression(
                '/\bfill="(#[0-9A-Fa-f]{6}|none)"/',
                $path,
                'A path in the map has no fill attribute, so it renders black '
                .'if the stylesheet does not arrive.',
            );
        }

        $this->assertSame(12, substr_count($svg[0], 'fill="#E8F4EC"'), 'The twelve mainland members.');
        // Madagascar, Seychelles and its marker are outline only since
        // 29 October 2026: no fill, so nothing about them reads as a member.
        $this->assertSame(0, substr_count($svg[0], 'fill="#E5E0D5"'), 'A non-member is filled.');
        $this->assertSame(2, preg_match_all('/<path class="c-map-region__context"[^>]*fill="none"/', $svg[0]), 'Madagascar and Seychelles in outline.');
        $this->assertSame(1, preg_match_all('/<circle class="c-map-region__marker"[^>]*fill="none"/', $svg[0]), 'The Seychelles ring in outline.');
        $this->assertSame(0, substr_count($svg[0], 'fill="#F8F6F1"'), 'The continent is back.');
    }

    public function test_the_island_insets_carry_their_size_as_attributes(): void
    {
        // RegionMap.dc.html line 49: width="66" height="53". These were
        // sized in CSS alone, and an svg with only a viewBox defaults to
        // 100% by 100%: the two island states rendered as full-width blocks
        // thousands of pixels tall.
        $html = $this->map();

        // Five, not the screen's two. RegionGeometry::ENLARGED adds
        // Lesotho, Eswatini and Malawi to Comoros and Mauritius: the first
        // two are too small to aim at, and all three are too narrow to
        // carry their own name once the map started labelling by name.
        // Six since 29 October 2026: Seychelles, the smallest SADC state,
        // joins as an outlined non-member card (brief D1).
        $this->assertSame(
            6,
            preg_match_all('/<svg[^>]*width="66"[^>]*height="53"/', $html),
            'An inset does not carry the size the screen draws.',
        );
    }

    public function test_the_map_itself_carries_a_width(): void
    {
        $this->assertMatchesRegularExpression(
            '/class="c-map-region__svg"[^>]*width="100%"/s',
            $this->map(),
        );
    }

    public function test_the_context_states_are_named_as_non_members(): void
    {
        // Brief D1, 29 October 2026: Madagascar and Seychelles are named in
        // muted ink and say what they are, focusable but never a link. The
        // label class is muted, not the members' green.
        preg_match('#<svg[^>]*class="c-map-region__svg".*?</svg>#s', $this->map(), $svg);

        $this->assertStringContainsString('>Seychelles</text>', $svg[0]);
        $this->assertStringContainsString('>Madagascar</text>', $svg[0]);
        $this->assertSame(2, substr_count($svg[0], 'class="c-map-region__context-label"'));
        $this->assertSame(2, preg_match_all('~<g class="c-map-region__nonmember" tabindex="0" role="img"~', $svg[0]));
        $this->assertStringContainsString('SADC member state, not a CRASA member', $svg[0]);
        $this->assertDoesNotMatchRegularExpression('~href="[^"]*/members/(mg|sc)"~', $svg[0]);
    }

    public function test_comoros_and_mauritius_are_labelled_dots_inside_the_box(): void
    {
        preg_match('#<svg[^>]*class="c-map-region__svg".*?</svg>#s', $this->map(), $svg);
        $map = config('region-map');

        foreach (['km' => 'Comoros', 'mu' => 'Mauritius'] as $iso => $name) {
            [$x, $y] = $map['members'][$iso]['dot'];
            $this->assertGreaterThan($map['min_x'], $x);
            $this->assertLessThan($map['min_x'] + $map['width'], $x);
            $this->assertGreaterThan($map['min_y'], $y);
            $this->assertLessThan($map['min_y'] + $map['height'], $y);

            $this->assertMatchesRegularExpression(
                '~<a\s+class="c-map-region__member c-map-region__member--dot"\s+href="[^"]*/en/members/'.$iso.'"\s+data-iso="'.$iso.'".*?<circle class="c-map-region__dot"[^>]*cx="'.$x.'"[^>]*cy="'.$y.'".*?>'.$name.'</text>.*?</a>~s',
                $svg[0],
                $name.' is not a labelled, linked dot on the map.',
            );

            // A label ending at the right edge must not run off it.
            [$lx, , $anchor] = $map['members'][$iso]['dot_label'];
            $right = $anchor === 'end' ? $lx : $lx + mb_strlen($name) * 8.2 / ($anchor === 'middle' ? 2 : 1);
            $this->assertLessThanOrEqual($map['min_x'] + $map['width'], $right, $name.' label runs off the box.');
        }
    }

    public function test_the_legend_keys_both_tiers_without_a_choropleth(): void
    {
        // RegionMap.dc.html draws its two key entries outside the sc-if
        // that gates the four-step scale, so they show in both modes. Ours
        // rendered nothing at all unless a choropleth was showing, which
        // left the default view carrying two tiers of colour and no key to
        // either. A third entry existed while the continent did.
        $html = $this->map();

        $this->assertSame(2, substr_count($html, 'c-map-region__key'));
        $this->assertStringContainsString('CRASA member regulator.', $html);
        $this->assertStringContainsString('SADC member without a CRASA regulator.', $html);
        $this->assertStringNotContainsString('The rest of Africa', $html);

        // The scale stays conditional; there is nothing to scale here.
        $this->assertStringNotContainsString('c-map-region__scale', $html);
    }

    /* Legibility, not only correctness ------------------------------------ */

    public function test_the_map_sits_in_the_white_card_screen_08_draws(): void
    {
        // "background:var(--c-white);border:1px solid var(--c-line);
        //  border-radius:var(--r-md);padding:var(--s-5)" -- screen 08 line
        // 108, headed "The region" with "Select a member state" at right.
        // It was floating on the bare section ground.
        $html = $this->map();

        $this->assertStringContainsString('b-members__layout', $html);
        $this->assertStringContainsString('b-members__map', $html);
        $this->assertStringContainsString('The region', $html);
        $this->assertStringContainsString('Select a member state', $html);

        $css = file_get_contents(public_path('css/blocks.css'));
        $this->assertMatchesRegularExpression(
            '/\.b-members__map \{[^}]*background: var\(--c-white\)/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.b-members__map \{[^}]*border-radius: var\(--r-md\)/s',
            $css,
        );
    }

    public function test_the_directory_lists_all_fourteen_beside_the_map(): void
    {
        // Screen 08 puts a directory next to the map: two ways into the same
        // fourteen, so a reader who would rather read a name than aim at
        // Lesotho has one.
        $html = $this->map();

        $this->assertSame(14, substr_count($html, 'b-directory__row'));
        $this->assertStringContainsString('Directory', $html);
        $this->assertStringNotContainsString('class="b-members"', $html);
    }

    public function test_the_name_shows_at_desktop_and_the_code_below_it(): void
    {
        $html = $this->map();
        $css = file_get_contents(public_path('css/blocks.css'));

        // Both labels are served; the media query decides which is seen.
        preg_match_all('/class="c-map-region__name"[^>]*>([^<]*)</', $html, $names);
        preg_match_all('/class="c-map-region__code"[^>]*>([^<]*)</', $html, $codes);

        $this->assertContains('DR Congo', $names[1], 'The map still says CD to a delegate.');
        $this->assertContains('South Africa', $names[1]);
        $this->assertCount(12, $codes[1], 'Every mainland member keeps a code for narrow widths.');

        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 768px\) \{\s*\.c-map-region__name \{ display: block; \}\s*'
            .'\.c-map-region__code:not\(\[data-keep="true"\]\) \{ display: none; \}\s*\}/s',
            $css,
            'The name and code do not swap at 768.',
        );
        $this->assertMatchesRegularExpression(
            '/\.c-map-region__name \{ display: none; \}/',
            $css,
            'The name label is not hidden below the breakpoint.',
        );

        // Lesotho and Eswatini keep the code at every width: their labels
        // already sit outside their shapes and a name there would run
        // across their neighbours. The inset chips carry the full name.
        $this->assertMatchesRegularExpression('/data-keep="true"[^>]*>LS</s', $html);
        $this->assertMatchesRegularExpression('/data-keep="true"[^>]*>SZ</s', $html);
        $this->assertNotContains('Lesotho', $names[1]);
        $this->assertNotContains('Eswatini', $names[1]);
    }

    public function test_no_label_is_drawn_outside_the_box(): void
    {
        // Measured against the real advance widths of the label font rather
        // than guessed: a centred label at 13px runs half its width either
        // side of its anchor.
        $map = config('region-map');
        $html = $this->map();

        preg_match_all(
            '/class="c-map-region__(?:name|code)"[^>]*x="([\d.]+)"[^>]*>([^<]*)</',
            $html,
            $labels,
            PREG_SET_ORDER,
        );

        $this->assertNotEmpty($labels);

        foreach ($labels as [$ignored, $x, $text]) {
            // 7.2px per character at 13px Inter is above the real mean and
            // above every capital in these names, so this over-estimates.
            $half = (mb_strlen(trim($text)) * 7.2) / 2;

            $this->assertGreaterThanOrEqual(
                $map['min_x'],
                (float) $x - $half,
                "The label \"{$text}\" runs off the left of the box.",
            );
            $this->assertLessThanOrEqual(
                $map['min_x'] + $map['width'],
                (float) $x + $half,
                "The label \"{$text}\" runs off the right of the box.",
            );
        }
    }

    public function test_seychelles_has_something_under_its_name(): void
    {
        // 115 islands totalling 450 square kilometres is three pixels at
        // this projection. A word alone in the ocean reads as an error.
        $html = $this->map();

        $this->assertMatchesRegularExpression(
            '/<circle class="c-map-region__marker"[^>]*cx="782"[^>]*cy="157"[^>]*fill="none"/',
            $html,
        );

        $map = config('region-map');
        $seychelles = collect($map['context'])->firstWhere('code', 'SC');
        $this->assertNotNull($seychelles['marker']);
    }

    public function test_the_context_stroke_matches_the_members(): void
    {
        // Context is quieter by colour, not by line weight.
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression(
            '/\.c-map-region__context \{[^}]*stroke-width: \.9/',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.c-map-region__member path \{[^}]*stroke-width: 1;/s',
            $css,
        );
    }

    public function test_a_member_state_responds_to_the_pointer(): void
    {
        // RegionMap.dc.html draws no hover at all, so fourteen identical
        // pale shapes gave no signal that any of them was a control.
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression(
            '/\.c-map-region--plain \.c-map-region__member:hover path,\s*'
            .'\.c-map-region--plain \.c-map-region__member:focus-visible path \{'
            .'[^}]*fill: var\(--c-green-step-1\)/s',
            $css,
        );
    }

    public function test_the_controls_are_one_row_with_labels_that_do_not_overlap(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        // The label was width:100% with a negative bottom margin, so it
        // took its own line and sat on the control it labelled.
        $this->assertDoesNotMatchRegularExpression(
            '/\.c-map-region__control-label \{[^}]*width: 100%/s',
            $css,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.c-map-region__control-label \{[^}]*margin-bottom: calc\(var\(--s-1\) \* -1\)/s',
            $css,
        );

        $this->assertMatchesRegularExpression(
            '/\.c-map-region__field \{[^}]*flex-direction: column/s',
            $css,
        );

        // And every select is still the component screen 01 draws.
        $html = $this->map();
        preg_match_all('/<select[^>]*class="([^"]*)"/', $html, $selects);

        foreach ($selects[1] as $classes) {
            $this->assertStringContainsString('c-select', $classes);
        }
    }

    public function test_the_static_map_gets_its_whole_container(): void
    {
        // .c-map-region__stage takes a second 260px column for the detail
        // panel at 900px of VIEWPORT, not of container. The homepage's
        // summary map is 600px wide inside that viewport, and static mode
        // renders no detail panel, so the stage reserved 260px for nothing
        // and left the map 44px. That is the map that rendered at roughly
        // 50 pixels.
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression(
            '/\.c-map-region--static \.c-map-region__stage \{ grid-template-columns: minmax\(0, 1fr\); \}/',
            $css,
            'The static map still reserves a column for a panel it does not render.',
        );

        $this->seed(HomepageSeeder::class);

        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('c-map-region--static', $html);

        // And static means static: no controls, no detail, no legend, no
        // chips, no exports on the homepage.
        foreach ([
            'c-map-region__control',
            'c-map-region__detail',
            'c-map-region__legend',
            'c-map-region__exports',
        ] as $chrome) {
            $this->assertStringNotContainsString($chrome, $html, "The homepage map still renders {$chrome}.");
        }

        // The inset cards are the exception since 29 October 2026 (brief
        // D1): the smallest states are shown larger under the home map too.
        $this->assertStringContainsString('c-map-region__insets', $html);
    }
}
