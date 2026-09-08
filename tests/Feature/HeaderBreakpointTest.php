<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * Screen 01's canonical chrome, at the breakpoints it names.
 *
 * These read the stylesheet rather than a rendered viewport, because the
 * collapse is now pure CSS. That is the point: three attempts at measuring
 * the header at runtime failed three different ways, each time collapsing a
 * navigation that fitted comfortably. A media query cannot be wrong about
 * the viewport width, and what a test can hold is that the rules exist and
 * say what screen 01 says.
 */
class HeaderBreakpointTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedComposedPages();
    }

    private function chrome(): string
    {
        return file_get_contents(public_path('css/chrome.css'));
    }

    /** Exactly the rule block for a media query, brace-matched. */
    private function rulesFor(string $query): string
    {
        $css = $this->chrome();
        $start = strpos($css, $query);

        $this->assertNotFalse($start, "No rules for {$query}");

        $depth = 0;
        for ($i = $start; $i < strlen($css); $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($css, $start, $i - $start + 1);
                }
            }
        }

        return substr($css, $start);
    }

    public function test_the_runtime_measurement_is_gone(): void
    {
        $js = file_get_contents(public_path('js/chrome.js'));

        // Nothing measures the header any more.
        $this->assertStringNotContainsString('getBoundingClientRect', $js);
        $this->assertStringNotContainsString('contentWidth', $js);
        $this->assertStringNotContainsString("setAttribute('data-nav'", $js);
    }

    /** Screen 01: "1440px. One row, all nine items, full identity lockup." */
    public function test_above_1024_nothing_is_hidden_and_there_is_no_hamburger(): void
    {
        $css = $this->chrome();

        // The trigger and the drawer are hidden by default, which is the
        // 1024-and-up state. They are only revealed inside a max-width query.
        $this->assertStringContainsString('.c-header__toggle { display: none; }', $css);
        $this->assertStringContainsString('.c-drawer { display: none; }', $css);

        // No rule hides the nav or the utilities outside a max-width query.
        $this->assertStringNotContainsString('.c-nav { display: none; }', $css);
    }

    /**
     * Screen 01: "768px. Nav wraps to a second row ... The switcher and the
     * extranet action stay on the first row."
     */
    public function test_between_768_and_1023_the_nav_wraps_and_the_utilities_stay(): void
    {
        $rules = $this->rulesFor('@media (max-width: 1023.98px) and (min-width: 768px)');

        $this->assertStringContainsString('.c-nav', $rules);
        $this->assertStringContainsString('flex-basis: 100%', $rules);
        // The utilities are not touched in this range, so they stay put.
        $this->assertStringNotContainsString('.c-header__utilities', $rules);
    }

    /**
     * Screen 01: "375px. Identity, drawer trigger. Nav, switcher and extranet
     * action move into the drawer."
     */
    public function test_below_768_everything_moves_into_the_drawer(): void
    {
        $rules = $this->rulesFor('@media (max-width: 767.98px)');

        $this->assertStringContainsString('.c-nav,', $rules);
        $this->assertStringContainsString('.c-header__utilities { display: none; }', $rules);
        $this->assertStringContainsString('.c-header__toggle { display: inline-flex;', $rules);
    }

    /** Without JavaScript there is no drawer to open, so nothing is hidden. */
    public function test_without_javascript_the_nav_stays_visible_at_every_width(): void
    {
        $css = $this->chrome();

        $this->assertStringContainsString('html:not(.has-js) .c-nav', $css);
        $this->assertStringContainsString('html:not(.has-js) .c-header__utilities { display: flex; }', $css);
    }

    public function test_the_header_ships_all_nine_items_and_the_utilities_at_every_width(): void
    {
        // The markup is width-independent; only CSS decides what shows.
        $html = $this->get('/en')->getContent();

        $this->assertSame(9, substr_count($html, 'class="c-nav__link"'));
        $this->assertStringContainsString('c-header__utilities', $html);
        $this->assertStringContainsString('c-lang', $html);
    }
}
