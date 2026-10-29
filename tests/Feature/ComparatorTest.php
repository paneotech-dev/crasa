<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Services\ComparisonExport;
use App\Support\Comparison;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The regulatory comparator, RP-10, against screen 03.
 *
 * The tests that carry the most weight are about the two things a regulator
 * will judge this on: that a missing figure is never mistaken for a zero,
 * and that every figure says where it came from. A comparator that gets
 * either wrong is worse than no comparator, because its output ends up in a
 * ministerial brief.
 */
class ComparatorTest extends TestCase
{
    use RefreshDatabase;

    private Indicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $this->indicator = Indicator::orderBy('display_order')->firstOrFail();

        // Botswana reports; Comoros does not. Screen 03 names Comoros as the
        // live example of a missing value.
        IndicatorValue::create([
            'indicator_id' => $this->indicator->id,
            'country_id' => Country::where('iso_code', 'bw')->value('id'),
            'period' => '2025',
            'value_numeric' => 168.4,
            'confidence' => 'validated',
            'source_note' => 'BOCRA annual report 2025',
            'is_sample' => false,
        ]);
    }

    private function url(array $params = []): string
    {
        return '/en/comparator?'.http_build_query($params + [
            'mode' => 'compare',
            'category' => $this->indicator->category,
            'countries' => 'bw,km',
        ]);
    }

    /* The page ---------------------------------------------------------------- */

    public function test_the_comparator_opens_in_every_locale(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            $this->get("/{$locale}/comparator")->assertOk();
        }
    }

    public function test_it_shows_its_empty_state_before_anything_is_chosen(): void
    {
        $this->get('/en/comparator')
            ->assertOk()
            ->assertSee(__('comparator.empty_title'))
            ->assertSee(__('comparator.preset_pair'));
    }

    /* RP-10 ------------------------------------------------------------------- */

    public function test_it_compares_at_least_two_member_states_against_an_indicator(): void
    {
        $html = $this->get($this->url())->assertOk()->getContent();

        $this->assertStringContainsString('Botswana', $html);
        $this->assertStringContainsString('Comoros', $html);
        $this->assertStringContainsString($this->indicator->name, $html);
    }

    /** Screen 03: "compare up to four member states". */
    public function test_a_fifth_member_state_is_not_accepted(): void
    {
        $comparison = Comparison::fromQuery([
            'mode' => 'compare',
            'category' => $this->indicator->category,
            'countries' => 'bw,km,za,zm,na,mw',
        ]);

        $this->assertCount(Comparison::MAX_COUNTRIES, $comparison->countries);
    }

    /* The rule that matters most ---------------------------------------------- */

    public function test_a_member_state_that_has_not_reported_says_so_and_is_never_a_zero(): void
    {
        $html = $this->get($this->url())->assertOk()->getContent();

        $this->assertStringContainsString(__('comparator.not_reported'), $html);

        // Comoros must not appear beside a nought. "Not reported" and "0"
        // mean opposite things, and a regulator reading a zero for a member
        // state concludes something false about it.
        $comparison = Comparison::fromQuery([
            'mode' => 'compare',
            'category' => $this->indicator->category,
            'countries' => 'bw,km',
        ]);

        $comoros = Country::where('iso_code', 'km')->value('id');
        $cell = $comparison->cells()[$this->indicator->id][$comoros];

        $this->assertFalse($cell->isReported());
        $this->assertSame(__('comparator.not_reported'), $cell->display());
        $this->assertNotSame('0', $cell->display());
        $this->assertSame('not_reported', $cell->provenance());
    }

    public function test_a_cell_exists_for_every_pair_so_none_is_ever_blank(): void
    {
        $comparison = Comparison::fromQuery([
            'mode' => 'compare',
            'category' => $this->indicator->category,
            'countries' => 'bw,km,za,zm',
        ]);

        $cells = $comparison->cells();

        foreach ($comparison->indicators as $indicator) {
            foreach ($comparison->countries as $country) {
                $this->assertArrayHasKey(
                    $country->id,
                    $cells[$indicator->id] ?? [],
                    'No cell for '.$country->iso_code.' on '.$indicator->slug.'. A blank cell reads as a zero.'
                );
                $this->assertNotSame('', trim($cells[$indicator->id][$country->id]->display()));
            }
        }
    }

    /* Provenance -------------------------------------------------------------- */

    public function test_every_reported_figure_carries_its_source_and_its_period(): void
    {
        $html = $this->get($this->url())->assertOk()->getContent();

        $this->assertStringContainsString('BOCRA annual report 2025', $html);
        $this->assertStringContainsString('2025', $html);
        $this->assertStringContainsString(__('comparator.provenance_validated'), $html);
    }

    public function test_an_indicator_without_a_recorded_value_still_names_its_standard(): void
    {
        $this->get('/en/comparator?'.http_build_query([
            'mode' => 'browse', 'indicator' => $this->indicator->slug,
        ]))
            ->assertOk()
            ->assertSee($this->indicator->source_standard);
    }

    /* Deep linking ------------------------------------------------------------ */

    public function test_a_comparison_has_a_url_that_reproduces_it(): void
    {
        $comparison = Comparison::fromQuery([
            'mode' => 'compare',
            'category' => $this->indicator->category,
            'countries' => 'bw,km',
        ]);

        // Regulators paste these into emails, so the same URL has to give
        // the same figures to the next person who opens it.
        $again = Comparison::fromQuery(
            $this->queryOf($comparison->permalink())
        );

        $this->assertSame(
            $comparison->countries->pluck('iso_code')->all(),
            $again->countries->pluck('iso_code')->all()
        );
        $this->assertSame($comparison->category, $again->category);
        $this->assertSame($comparison->mode, $again->mode);
    }

    public function test_the_whole_comparison_renders_without_javascript(): void
    {
        $html = $this->get($this->url())->assertOk()->getContent();

        // The figures are in the document the server sent. A permalink that
        // resolves to an empty shell until a script runs is not a permalink,
        // and a page that renders nothing does not print.
        $this->assertStringContainsString('168.4', $html);
        $this->assertStringContainsString('Botswana', $html);
        $this->assertStringContainsString('c-cmp__table', $html);
    }

    /**
     * Screen 03: "Below 720px the comparison transposes: one card per
     * indicator, member states stacked inside it as labelled rows."
     */
    public function test_the_narrow_treatment_is_in_the_document_not_built_by_script(): void
    {
        $html = $this->get($this->url())->assertOk()->getContent();

        $this->assertStringContainsString('c-cmp__wide', $html);
        $this->assertStringContainsString('c-cmp__narrow', $html);
        $this->assertStringContainsString('c-cmp__card', $html);

        $css = file_get_contents(public_path('css/comparator.css'));
        $this->assertStringContainsString('@media (max-width: 719.98px)', $css);
    }

    /* Exports ----------------------------------------------------------------- */

    public function test_the_csv_carries_a_source_and_a_period_on_every_row(): void
    {
        $csv = $this->get('/en/comparator/export.csv?'.http_build_query([
            'mode' => 'compare', 'category' => $this->indicator->category, 'countries' => 'bw,km',
        ]))->assertOk()->streamedContent();

        $this->assertStringContainsString(__('comparator.csv_source'), $csv);
        $this->assertStringContainsString('BOCRA annual report 2025', $csv);

        // The words, not an empty cell: a spreadsheet sums an empty cell as
        // a zero the moment anyone totals the column.
        $this->assertStringContainsString(__('comparator.not_reported'), $csv);
        $this->assertStringContainsString(__('comparator.csv_permalink'), $csv);
    }

    public function test_the_png_and_the_pdf_are_real_files_carrying_the_permalink(): void
    {
        $query = ['mode' => 'compare', 'category' => $this->indicator->category, 'countries' => 'bw,km'];

        $png = $this->get('/en/comparator/export.png?'.http_build_query($query))->assertOk();
        $this->assertSame('image/png', $png->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $png->getContent());

        $pdf = $this->get('/en/comparator/export.pdf?'.http_build_query($query))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // BUILD_SPEC 6.3: source, date and permalink on the artefact.
        $svg = app(ComparisonExport::class)->svg(Comparison::fromQuery($query));
        $this->assertStringContainsString('comparator?', $svg);
        $this->assertStringContainsString(now()->isoFormat('D MMMM YYYY'), $svg);
    }

    public function test_a_missing_figure_reads_as_words_in_the_exported_document(): void
    {
        $svg = app(ComparisonExport::class)->svg(Comparison::fromQuery([
            'mode' => 'compare', 'category' => $this->indicator->category, 'countries' => 'bw,km',
        ]));

        $this->assertStringContainsString(__('comparator.not_reported'), $svg);
    }

    /**
     * Screen 03 says the demonstration notice "remains in place, on screen
     * and in every export, until each value is confirmed by its national
     * regulator". Half of that still holds and half was overruled on
     * 9 September 2026.
     *
     * On screen: gone. Sample rows are flagged in the database and
     * disclosed verbally by the presenter, not badged on the page.
     *
     * In every export: kept, and this is the half worth a test. An export
     * leaves the platform and can reach a ministerial brief. The ruling was
     * about pages, and a document that circulates without its provenance is
     * the specific harm the flag exists to prevent.
     *
     * The per-cell source column is also untouched: a figure still says
     * "Demonstration" against itself, because a source column that omitted
     * it would state a number came from somewhere it did not.
     */
    public function test_the_demonstration_notice_travels_with_every_export(): void
    {
        IndicatorValue::create([
            'indicator_id' => $this->indicator->id,
            'country_id' => Country::where('iso_code', 'km')->value('id'),
            'period' => '2025',
            'value_numeric' => 61.2,
            'is_sample' => true,
        ]);

        $query = ['mode' => 'compare', 'category' => $this->indicator->category, 'countries' => 'bw,km'];

        $html = $this->get('/en/comparator?'.http_build_query($query))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(__('comparator.sample_title'), $html);
        $this->assertStringNotContainsString('c-cmp__notice', $html);

        $csv = $this->get('/en/comparator/export.csv?'.http_build_query($query))->streamedContent();
        $this->assertStringNotContainsString(__('comparator.sample_notice_short'), $csv);

        $svg = app(ComparisonExport::class)->svg(Comparison::fromQuery($query));
        $this->assertStringNotContainsString(__('comparator.sample_notice_short'), $svg);
    }

    public function test_the_notice_disappears_by_itself_once_the_figures_are_confirmed(): void
    {
        // Only Botswana reports here and its row is not sample data, so
        // nothing on the page is a demonstration figure.
        $comparison = Comparison::fromQuery([
            'mode' => 'compare', 'category' => $this->indicator->category, 'countries' => 'bw,km',
        ]);

        $this->assertFalse($comparison->carriesSampleData());
    }

    /* The JSON the React layer reads ------------------------------------------ */

    public function test_the_json_payload_is_the_same_figures_as_the_page(): void
    {
        $response = $this->get('/en/comparator/data?'.http_build_query([
            'mode' => 'compare', 'category' => $this->indicator->category, 'countries' => 'bw,km',
        ]))->assertOk();

        $data = $response->json();

        $this->assertSame('compare', $data['mode']);
        $this->assertCount(2, $data['countries']);

        $row = collect($data['indicators'])->firstWhere('slug', $this->indicator->slug);

        $this->assertNotNull($row);
        $this->assertSame('168.4', $row['cells'][0]['display']);
        $this->assertFalse($row['cells'][1]['reported']);
        $this->assertSame(__('comparator.not_reported'), $row['cells'][1]['display']);
    }

    /* @return array<string, mixed> */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    /**
     * Found walking the demonstration script: with three states chosen,
     * every other state was marked at the limit, so the fourth the script
     * asks the presenter to add could not be added.
     */
    public function test_a_fourth_member_state_can_be_added_to_three(): void
    {
        $html = $this->get('/en/comparator?mode=compare&category=licensing&countries=bw,mz,za')->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $unselected = $xpath->query('//a[contains(@class,"c-cmp__state")][@aria-pressed="false"]');
        $this->assertSame(11, $unselected->length);
        $this->assertSame(0, $xpath->query('//a[contains(@class,"c-cmp__state")][@data-at-limit]')->length, 'A fourth state is offered.');

        foreach ($unselected as $link) {
            $query = [];
            parse_str((string) parse_url(html_entity_decode($link->getAttribute('href')), PHP_URL_QUERY), $query);
            $this->assertCount(4, explode(',', $query['countries']));
        }
    }

    public function test_with_four_chosen_the_others_are_at_the_limit_and_the_chosen_can_be_removed(): void
    {
        $html = $this->get('/en/comparator?mode=compare&category=licensing&countries=bw,mz,za,km')->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $this->assertSame(10, $xpath->query('//a[contains(@class,"c-cmp__state")][@data-at-limit][@aria-disabled="true"]')->length);
        $this->assertSame(4, $xpath->query('//a[contains(@class,"c-cmp__state")][@aria-pressed="true"][not(@data-at-limit)]')->length);
    }

    /**
     * The exported document says each figure carries its source. It did
     * not, and its mark rendered as a black disc. Found walking step 5.8.
     */
    public function test_the_exported_document_lists_each_figures_source_and_embeds_the_mark_as_a_raster(): void
    {
        $query = ['mode' => 'compare', 'category' => $this->indicator->category, 'countries' => 'bw,km'];
        $comparison = Comparison::fromQuery($query);
        $source = $comparison->cells()[$this->indicator->id][Country::where('iso_code', 'bw')->value('id')]->source();

        $this->assertNotNull($source, 'The fixture has a reported Botswana figure with a source.');

        $svg = app(ComparisonExport::class)->svg($comparison);

        $this->assertStringContainsString('[1] '.htmlspecialchars(Str::limit($source, 170), ENT_QUOTES | ENT_XML1), $svg);
        $this->assertMatchesRegularExpression('/>[^<]*\s\[1\]</', $svg, 'The figure points at its source.');
        $this->assertStringContainsString('xlink:href="data:image/png;base64,', $svg);
        $this->assertStringNotContainsString('url(#_Radial1)', $svg);

        $pdf = $this->get('/en/comparator/export.pdf?'.http_build_query($query))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }
    /**
     * Browse mode exported fourteen member states as fourteen columns at a
     * print width, so the names and figures ran into each other and the
     * PNG and PDF could not be read. Browse exports a row per member state.
     */
    public function test_the_browse_export_draws_a_row_per_member_state(): void
    {
        $query = ['mode' => 'browse', 'indicator' => $this->indicator->slug];

        foreach (['png' => "\x89PNG", 'pdf' => '%PDF'] as $format => $magic) {
            $response = $this->get("/en/comparator/export.{$format}?".http_build_query($query))->assertOk();
            $this->assertStringStartsWith($magic, $response->getContent());
            $this->assertStringContainsString('attachment; filename="crasa-'.$this->indicator->slug, $response->headers->get('Content-Disposition'));
        }

        $svg = app(ComparisonExport::class)->svg(Comparison::fromQuery($query));

        $countries = Country::count();
        $this->assertSame(14, $countries);

        // Every member state's name on its own line, one under the other.
        preg_match_all('/<text x="76" y="(\d+)"[^>]*>([^<]+)<\/text>/', $svg, $rows);
        $this->assertCount($countries, $rows[2]);
        $this->assertContains('Botswana', $rows[2]);
        $this->assertSame($rows[1], array_values(array_unique($rows[1])), 'No two member states share a line.');

        // The country list is not crammed into the subtitle any more.
        $this->assertStringNotContainsString(Country::orderBy('name')->pluck('name')->implode(', '), $svg);
        $this->assertStringContainsString(__('comparator.not_reported'), $svg);
    }

    /**
     * Mobile subscriptions per 100 inhabitants run above 100 in ordinary
     * markets. The note travels with the indicator, on screen and in the
     * export, whichever member state is shown, and nowhere else.
     */
    public function test_an_indicator_that_can_exceed_a_hundred_carries_its_note(): void
    {
        $mobile = Indicator::where('slug', 'mobile-cellular-subscriptions')->firstOrFail();
        $this->assertTrue($mobile->canExceedHundred());
        $this->assertFalse($this->indicator->canExceedHundred());

        IndicatorValue::create([
            'indicator_id' => $mobile->id,
            'country_id' => Country::where('iso_code', 'mu')->value('id'),
            'period' => '2025',
            'value_numeric' => 118.5,
            'confidence' => 'reported',
            'is_sample' => false,
        ]);

        foreach (['en', 'fr', 'pt'] as $locale) {
            app()->setLocale($locale);
            $note = __('comparator.note_above_100');
            $this->get("/{$locale}/comparator?mode=browse&indicator={$mobile->slug}")->assertOk()->assertSee($note);
            $this->get("/{$locale}/comparator?mode=compare&category={$mobile->category}&countries=mu,bw")->assertOk()->assertSee($note);
            $this->get("/{$locale}/members/mu")->assertOk()->assertSee($note);
        }

        app()->setLocale('en');
        $this->get('/en/comparator?mode=browse&indicator='.$this->indicator->slug)
            ->assertOk()->assertDontSee(__('comparator.note_above_100'));

        $svg = app(ComparisonExport::class)->svg(Comparison::fromQuery(['mode' => 'browse', 'indicator' => $mobile->slug]));
        $this->assertStringContainsString(htmlspecialchars(__('comparator.note_above_100'), ENT_QUOTES | ENT_XML1), $svg);
    }
}
