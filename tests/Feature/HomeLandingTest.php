<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Page;
use App\Models\PageBlock;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The landing page, 29 October 2026: one comparison block that answers in
 * place and still works without a script, the "What we do" band as four
 * lines of work with their figures, and the newsroom's own cards.
 */
class HomeLandingTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_10_29_021000_home_one_comparison_and_statements_in_figures.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SectorSeeder::class);
        $this->seed(CountrySeeder::class);
        $this->seed(HomepageSeeder::class);

        $first = Indicator::factory()->create(['slug' => 'mobile-subs', 'name' => 'Mobile-cellular subscriptions', 'data_type' => 'numeric']);
        $second = Indicator::factory()->create(['slug' => 'fixed-subs', 'name' => 'Fixed-telephone subscriptions', 'data_type' => 'numeric']);

        foreach (Country::all() as $index => $country) {
            foreach ([$first, $second] as $indicator) {
                IndicatorValue::factory()->create([
                    'indicator_id' => $indicator->id,
                    'country_id' => $country->id,
                    'period' => '2024',
                    'value_numeric' => ($index + 1) * 7,
                ]);
            }
        }
    }

    private function migration(): object
    {
        return require database_path('migrations/'.self::MIGRATION);
    }

    /* The comparison fragment ------------------------------------------------ */

    public function test_the_fragment_returns_the_table_alone_for_the_asked_selection(): void
    {
        $response = $this->get('/en/home/comparison?cmp_indicator=fixed-subs&cmp_a=na&cmp_b=zw')->assertOk();
        $html = $response->getContent();

        // A fragment, not a page.
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('<header', $html);
        $this->assertStringContainsString('data-cmp-result', $html);

        // The selection the server resolved travels with it, so the
        // selectors and the announcement follow the table.
        $this->assertStringContainsString('data-indicator="fixed-subs"', $html);
        $this->assertStringContainsString('data-a="na"', $html);
        $this->assertStringContainsString('data-b="zw"', $html);
        $this->assertStringContainsString('data-status="Fixed-telephone subscriptions, Namibia and Zimbabwe."', $html);

        // Value next to the country, the year in its own column.
        $this->assertMatchesRegularExpression('~Namibia</a>\s*</th>\s*<td class="b-compare__value"~', $html);
        $this->assertMatchesRegularExpression('~<td class="b-compare__year">2024</td>~', $html);

        $response->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_the_fragment_answers_in_the_readers_language(): void
    {
        $html = $this->get('/fr/home/comparison?cmp_a=na&cmp_b=zw')->assertOk()->getContent();

        $this->assertStringContainsString(__('blocks.member_state', [], 'fr'), $html);
        $this->assertStringContainsString('/fr/', $html);
    }

    public function test_the_fragment_resolves_bad_or_repeated_parameters_to_two_real_states(): void
    {
        $html = $this->get('/en/home/comparison?cmp_indicator=not-real&cmp_a=xx&cmp_b=xx')->assertOk()->getContent();
        preg_match('~data-a="([a-z]*)"~', $html, $a);
        preg_match('~data-b="([a-z]*)"~', $html, $b);

        $this->assertNotSame('', $a[1]);
        $this->assertNotSame('', $b[1]);
        $this->assertNotSame($a[1], $b[1]);

        // The same state twice is one state and a fallback, never a
        // table of two identical rows.
        $html = $this->get('/en/home/comparison?cmp_a=na&cmp_b=na')->assertOk()->getContent();
        $this->assertStringContainsString('data-a="na"', $html);
        $this->assertStringNotContainsString('data-b="na"', $html);
    }

    public function test_the_fragment_is_not_found_when_the_homepage_has_no_comparison(): void
    {
        PageBlock::where('block_type', BlockType::FeaturedComparison->value)->update(['is_visible' => false]);

        $this->get('/en/home/comparison')->assertNotFound();
    }

    /* Without a script --------------------------------------------------------- */

    public function test_without_a_script_the_form_reloads_the_homepage_at_the_block(): void
    {
        // Since the Concept 3 home (29 October 2026) the comparison is the
        // console partial (partials/home/comparator): the same GET form idea,
        // back to this page at #home-compare.
        $html = $this->get('/en?cmp_indicator=fixed-subs&cmp_a=na&cmp_b=zw')->assertOk()->getContent();

        $section = substr($html, strpos($html, 'data-home-compare'));
        $section = substr($section, 0, strpos($section, '</section>'));

        $this->assertMatchesRegularExpression('~<form class="c-hcmp__form" method="get" action="http://localhost/en#home-compare">~', $section);
        $this->assertMatchesRegularExpression('~<button[^>]*type="submit"[^>]*data-cmp-submit~', $section);

        // The page renders what the query asked for, selectors included.
        $this->assertMatchesRegularExpression('~id="hcmp-indicator".*?<option value="fixed-subs"\s+selected~s', $section);
        $this->assertMatchesRegularExpression('~id="hcmp-a".*?<option value="na"\s+selected~s', $section);
        $this->assertMatchesRegularExpression('~id="hcmp-b".*?<option value="zw"\s+selected~s', $section);

        // The script is told where the fragment lives, and a change is
        // announced in a live region outside the swapped parts.
        $this->assertStringContainsString('data-fragment="http://localhost/en/home/comparison?view=console"', $section);
        $this->assertMatchesRegularExpression('~role="status" aria-live="polite" data-hcmp-status~', $section);
        $this->assertStringContainsString('js/home-compare.js', $html);
    }

    public function test_the_page_and_the_fragment_render_the_same_table(): void
    {
        $page = $this->get('/en?cmp_indicator=fixed-subs&cmp_a=na&cmp_b=zw')->getContent();
        $fragment = $this->get('/en/home/comparison?view=console&cmp_indicator=fixed-subs&cmp_a=na&cmp_b=zw')->getContent();

        $table = function (string $html) {
            $start = strpos($html, '<table class="u-table">');
            $this->assertNotFalse($start, 'No comparison table.');

            return preg_replace('~\s+~', ' ', substr($html, $start, strpos($html, '</table>', $start) - $start));
        };

        $this->assertSame($table($fragment), $table($page));
    }

    /* The content migration --------------------------------------------------- */


    public function test_the_migration_is_idempotent_and_reversible(): void
    {
        $figures = PageBlock::where('block_type', 'key_figures')->firstOrFail();
        $count = fn () => DB::table('translations')->where('translatable_type', PageBlock::class)->where('translatable_id', $figures->id)->count();
        $before = $count();

        $this->migration()->up();
        $after = $count();
        $this->migration()->up();

        $this->assertSame($after, $count());
        $this->assertGreaterThan($before, $after);

        $this->migration()->down();

        $this->assertSame($before, $count());
        $this->assertTrue(PageBlock::where('block_type', 'statement_band')->firstOrFail()->is_visible);
        $this->assertTrue(PageBlock::where('block_type', 'pillars')->firstOrFail()->is_visible);
    }

    /* Latest updates ------------------------------------------------------------ */

}
