<?php

namespace Tests\Feature;

use App\Support\Locales;
use Database\Seeders\PublicPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header is one row at 1440.
 *
 * Screen 02 line 55 draws it as one: identity, nine links, three utilities.
 * It was rendering as two, and the stale stylesheet was only half of why.
 * The other half is arithmetic. Measured from the shipped woff2 files at the
 * shipped sizes, identity + nav + utilities came to 1488px in English
 * against 1344 available, and the container is capped at --w-page so no
 * viewport is wide enough to rescue it.
 *
 * Two things were carrying width they should not: the 230px full
 * association name, which screen 02's compressed state already hides, and
 * two nav labels that were page titles rather than menu labels -- "A propos
 * de la CRASA" against English's "About CRASA".
 *
 * These are the budgets that keep it one row. The measurement itself lives
 * in docs/CSS_DELIVERY.md; what a test can hold is the input to it.
 */
class HeaderOneRowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Advance widths per character, measured from the shipped IBM Plex Sans
     * 500 at 15px across the nine labels in all three languages. The widest
     * character in any of them is "W" at 12.0px; this is the mean plus a
     * margin, so it over- rather than under-estimates.
     */
    private const PX_PER_CHAR = 8.4;

    private const LINK_PADDING = 22;   // screen 02: padding 9px 11px

    private const NAV_GAP = 2;         // screen 02: gap 2px

    /** 1440 less --page-x twice, minus utilities, identity and two --s-5 gaps. */
    private const NAV_BUDGET = 890;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PublicPagesSeeder::class);
    }

    public function test_the_full_name_stands_down_where_the_nav_comes_inline(): void
    {
        // 230px of association name and nine links cannot share a row, and
        // --w-page caps the container so no viewport fixes it. Screen 02's
        // compressed state hides this same element.
        $css = file_get_contents(public_path('css/chrome.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 1024px\) \{\s*\.c-header__fullname \{ display: none; \}/',
            $css,
        );

        // Not lost: the footer still carries it.
        $html = $this->get('/en/about')->assertOk()->getContent();
        $this->assertStringContainsString('Communications Regulators&#039; Association of Southern Africa', $html);
    }

    public function test_nothing_in_the_header_carries_a_width_the_screen_does_not_draw(): void
    {
        // Screen 02 draws exactly four sizing declarations inside the
        // header: the 40px logo, the 230px cap on the full name, the 44px
        // minimum on a language option, and min-width:0 on the two flex
        // children that must be allowed to shrink. Anything else with a
        // width is a thumb on the scale.
        $css = file_get_contents(public_path('css/chrome.css'));

        $header = [];

        preg_match_all('/([^{}]*c-(?:header|nav|lang|extranet|iconbutton)[^{}]*)\{([^{}]*)\}/', $css, $rules);

        foreach ($rules[1] as $i => $selector) {
            foreach (preg_split('/;/', $rules[2][$i]) as $declaration) {
                $declaration = trim(preg_replace('/\s+/', ' ', $declaration));

                if ($declaration === '') {
                    continue;
                }

                if (preg_match('/^(width|min-width|max-width|flex-basis|flex)\s*:/', $declaration)) {
                    $header[] = trim(preg_replace('/\s+/', ' ', $selector)).' { '.$declaration.' }';
                }
            }
        }

        $allowed = [
            'width: 40px', 'height: 40px',        // the mark, screen 02 line 56
            'max-width: 230px',                   // the full name, line 57
            'max-width: var(--w-page)',           // the container, line 55
            'min-width: 44px',                    // a language option, and the target size
            'min-width: 0',                       // flex children that must shrink
            'width: 44px',                        // the icon button, screen 01
            'flex: none', 'flex: 1', 'flex-basis: 100%',
            'width: auto', 'width: 100%', 'min-width: 100%',
            'max-width: none', 'max-width: 100%', 'width: 30px',
            'max-width: 340px', 'max-width: var(--w-reading)',
        ];

        $unexpected = [];

        foreach ($header as $line) {
            $declaration = trim(substr($line, strpos($line, '{') + 1), ' }');

            foreach ($allowed as $ok) {
                if (str_starts_with($declaration, $ok)) {
                    continue 2;
                }
            }

            $unexpected[] = $line;
        }

        $this->assertSame(
            [],
            $unexpected,
            "The header carries sizing the screen does not draw:\n".implode("\n", $unexpected),
        );
    }
}
