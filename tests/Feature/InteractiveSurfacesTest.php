<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Page;
use App\Models\User;
use App\Support\BlockLink;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * What the reader can drive, and what happens when the script does not run.
 *
 * None of these tests execute JavaScript, which is the point. Every
 * assertion here describes what a reader receives with scripting off,
 * blocked by a corporate proxy, or failed on a slow connection. That is the
 * floor the enhancement sits on, and it is the state a regulator on a
 * ministry network is most likely to be in.
 */
class InteractiveSurfacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
    }

    /* Counts that go somewhere ---------------------------------------------- */

    /**
     * A figure with no destination must not look like one with a
     * destination. Anything that is not interactive must not appear to be.
     */
    public function test_a_figure_without_a_destination_is_not_dressed_as_a_link(): void
    {
        $page = Page::factory()->create(['slug' => 'figures-test', 'status' => 'published']);
        $block = $page->blocks()->create([
            'block_type' => BlockType::KeyFigures,
            'position' => 0,
            'settings' => ['items' => [['value' => '42']]],
        ]);
        $block->translations()->create(['locale' => 'en', 'field' => 'item_0_label', 'value' => 'Something uncountable']);

        $html = $this->get('/en/figures-test')->assertOk()->getContent();

        $this->assertStringContainsString('Something uncountable', $html);
        $this->assertStringNotContainsString('b-figure--link', $html);
    }

    /**
     * A destination that is not on the list resolves to nothing rather than
     * to a broken href. A content editor cannot produce a 404 from here.
     */
    public function test_an_unknown_destination_renders_as_text_not_as_a_broken_link(): void
    {
        $this->assertNull(BlockLink::resolve('somewhere-else'));
        $this->assertNull(BlockLink::resolve(null));
        $this->assertNull(BlockLink::resolve(''));
        $this->assertSame('http://localhost/en/members', BlockLink::resolve('members'));
    }

    public function test_a_directory_row_opens_the_member_it_names(): void
    {
        // The members page renders screen 08's directory now rather than a
        // card grid. The rule is unchanged: a row that looks like a way in
        // has to be one.
        $html = $this->get('/en/members')->assertOk()->getContent();

        foreach (Country::pluck('iso_code') as $iso) {
            $this->assertStringContainsString(
                'class="b-directory__link" href="http://localhost/en/members/'.$iso.'"',
                $html,
                strtoupper($iso).' has a row that looks like a link and is not one.'
            );
        }
    }

    public function test_the_completeness_grid_names_member_states_that_go_to_them(): void
    {
        // Signed in: BUILD_SPEC 6.1 puts the matrix behind an account.
        $this->actingAs(User::factory()->create())
            ->get('/en/completeness')
            ->assertOk()
            ->assertSee('/en/members/bw', false);
    }

    /**
     * A card that reads as one thing a reader can open must open all over,
     * not only on the eighteen pixels of its title. The stretched hit area
     * is a pseudo-element on the link, so the link keeps its own accessible
     * name and the summary and date beside it stay text.
     */
    public function test_a_card_that_looks_openable_is_openable_all_over(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        foreach (['.b-card__title a::after', '.b-member__name::after'] as $rule) {
            $this->assertStringContainsString(
                $rule.' { content: ""; position: absolute; inset: 0;',
                $css,
                $rule.' is missing, so only the title of that card is clickable.'
            );
        }
    }

    /**
     * And the reverse. An events card with no destination must not green
     * its border on hover, because it has nothing to open. The rule keys
     * off the link, so a card without one is inert and looks inert with no
     * second rule to keep in step.
     */
    public function test_a_card_with_no_destination_gains_no_hover_response(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertStringContainsString('.b-card:has(.b-card__title a:hover)', $css);
        $this->assertStringNotContainsString('.b-card:hover {', $css);
        $this->assertStringNotContainsString('.b-member:hover {', $css);
    }

    /**
     * Every class a public template uses has styles behind it.
     *
     * This is the "looks interactive but isn't" test's blunter sibling, and
     * it found a real one: the events page shipped .c-events, .c-event and
     * three modifiers that no stylesheet defined, so a list of CRASA
     * meetings rendered as browser-default bullets. It had also invented
     * .c-empty rather than using the platform's own empty state, which is
     * the second CSS methodology CLAUDE.md rules out.
     *
     * Structural hooks with nothing to style are allowed and named here, so
     * the exception is a decision rather than a hole in the check.
     */
    public function test_no_public_template_uses_a_class_that_has_no_styles(): void
    {
        $css = '';

        foreach (glob(public_path('css/*.css')) as $sheet) {
            $css .= file_get_contents($sheet);
        }

        preg_match_all('~\.([a-z][a-z0-9_-]*)~i', $css, $matches);
        $defined = array_flip($matches[1]);

        // Grid children and table modifiers positioned entirely by their
        // parent. They carry no rules of their own and need none.
        $structural = ['c-completeness', 'c-enrol__manual'];

        $orphans = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/admin')) {
                continue;
            }

            /*
             * Blade comments first. The published paginator explains why
             * Laravel's own view could not be used by quoting the Tailwind
             * markup that caused the bug, and a guard that trips on its
             * own explanation is a guard nobody keeps.
             */
            $source = preg_replace('~\{\{--.*?--\}\}~s', '', $file->getContents());

            preg_match_all('~class="([^"{}@]*)"~', $source, $found);

            foreach ($found[1] as $list) {
                foreach (preg_split('~\s+~', trim($list)) as $class) {
                    if ($class === '' || isset($defined[$class]) || in_array($class, $structural, true)) {
                        continue;
                    }

                    $orphans[$class] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame(
            [],
            $orphans,
            'Classes used with no styles behind them: '.json_encode($orphans)
        );
    }

    /* The floor -------------------------------------------------------------- */

    /**
     * Every filtered surface is links and forms before it is anything else.
     * These four are the pages a reader filters on, and the assertion is
     * that no script has to run for any of them to filter.
     */
    public function test_every_filtered_surface_filters_without_a_script(): void
    {
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);

        foreach ([
            // Screen 07's sidebar: a GET form of checkboxes with a real
            // submit button, which filters.js removes only once its own
            // listeners are attached.
            '/en/publications' => 'c-facets',
            '/en/newsroom' => 'c-facets',
            '/en/members' => 'c-map-region__control',
        ] as $url => $marker) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString($marker, $html, $url.' has no filter controls at all.');
            $this->assertStringContainsString('data-live-filters', $html, $url.' is not wired for live filtering.');
        }
    }

    public function test_a_filtered_url_returns_the_filtered_page_on_its_own(): void
    {
        $this->seed(PublicationSeeder::class);

        // The state is in the URL, so a reader pastes it into an email and
        // the recipient sees the same page. Nothing is held in a script.
        $this->get('/en/publications?type=guideline')->assertOk();
        $this->get('/en/members?indicator=completeness')
            ->assertOk()
            ->assertSee('Data completeness');
    }

    /**
     * The submit buttons beside the selects are removed by the script that
     * takes over the change event, and only after it has. They must be in
     * the served HTML.
     */
    public function test_every_live_submit_is_a_real_submit_in_a_real_form(): void
    {
        // Stated on the button rather than on the page. It used to assert
        // that the homepage carried a live submit somewhere, which was true
        // only because the members block rendered the interactive map
        // there; the homepage now carries screen 02's summary with a static
        // map. And "every form in a live region" is too broad -- the
        // members page's first form navigates to a member profile rather
        // than swapping a region, and correctly has no live submit.
        //
        // What has to hold is this: a button the script will take over must
        // already be a working submit inside a real form, so the page does
        // the same thing with the script removed.
        $this->seed(HomepageSeeder::class);
        $this->seed(IndicatorSeeder::class);
        $this->seed(IndicatorValueSeeder::class);

        $found = 0;

        foreach (['/en', '/en/members', '/en/publications', '/en/newsroom'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            foreach (preg_split('#(?=<form\b)#', $html) as $chunk) {
                if (! str_contains($chunk, 'data-live-submit')) {
                    continue;
                }

                $form = substr($chunk, 0, strpos($chunk, '</form>') ?: strlen($chunk));

                if (! str_contains($form, 'data-live-submit')) {
                    continue;
                }

                $found++;

                $this->assertMatchesRegularExpression(
                    '/<form[^>]*method="[a-z]+"/i',
                    $form,
                    "A live submit on {$url} is not inside a form with a method."
                );
                $this->assertMatchesRegularExpression(
                    '/<button[^>]*type="submit"[^>]*data-live-submit|<button[^>]*data-live-submit[^>]*type="submit"/',
                    $form,
                    "A live submit on {$url} is not a submit button."
                );
            }
        }

        $this->assertGreaterThan(0, $found, 'No live submit found anywhere, so this proves nothing.');
    }

    /* The homepage comparison ------------------------------------------------ */

    public function test_the_homepage_comparison_is_driven_from_the_query_string(): void
    {
        $this->seedComparableFigures();

        $html = $this->get('/en')->assertOk()->getContent();

        // Selectors, present, on the page that describes the comparator.
        $this->assertStringContainsString('name="cmp_indicator"', $html);
        $this->assertStringContainsString('name="cmp_a"', $html);
        $this->assertStringContainsString('name="cmp_b"', $html);
    }

    public function test_the_homepage_comparison_shows_the_two_states_it_is_asked_for(): void
    {
        $this->seedComparableFigures();

        $html = $this->get('/en?cmp_a=na&cmp_b=zw')->assertOk()->getContent();

        $section = substr($html, strpos($html, 'home-comparison-status'));

        $this->assertStringContainsString('Namibia', $section);
        $this->assertStringContainsString('Zimbabwe', $section);
    }

    /**
     * A stale link in a two-year-old email must open the page, not break it.
     */
    public function test_the_homepage_comparison_ignores_a_state_that_does_not_exist(): void
    {
        $this->seedComparableFigures();

        $this->get('/en?cmp_a=xx&cmp_b=yy&cmp_indicator=not-real')->assertOk();
    }

    /** A homepage with something real to compare on it. */
    private function seedComparableFigures(): void
    {
        $this->seed(HomepageSeeder::class);

        $indicator = Indicator::factory()->create([
            'slug' => 'mobile-subs',
            'name' => 'Mobile-cellular subscriptions',
            'data_type' => 'numeric',
        ]);

        foreach (Country::all() as $index => $country) {
            IndicatorValue::factory()->create([
                'indicator_id' => $indicator->id,
                'country_id' => $country->id,
                'period' => '2025',
                'value_numeric' => ($index + 1) * 7,
            ]);
        }
    }
}
