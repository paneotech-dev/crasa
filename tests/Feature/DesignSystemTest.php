<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards on the token layer.
 *
 * Bootstrap supplies the grid and nothing else. These tests fail if an
 * upgrade or a careless edit lets the framework reach the design system's
 * palette or typefaces.
 */
class DesignSystemTest extends TestCase
{
    /*
     * One of these renders a page, and a rendered page reads the database:
     * the header asks whether the reader has a workspace and the footer
     * asks which of CRASA's accounts are configured. Without a schema the
     * layout throws and the assertion about three link tags fails for a
     * reason that has nothing to do with three link tags.
     */
    use RefreshDatabase;

    private function css(string $path): string
    {
        return file_get_contents(public_path($path));
    }

    public function test_the_vendored_bootstrap_carries_no_colour_and_no_type(): void
    {
        $grid = $this->css('css/vendor/bootstrap-grid.min.css');

        $this->assertStringNotContainsString('font-family', $grid);
        $this->assertStringNotContainsString('color:', $grid);
        $this->assertStringNotContainsString('background', $grid);
    }

    /**
     * The vendor directory is an allowlist, not a dumping ground. Bootstrap
     * is the grid build only, and Leaflet is the map widget's own stylesheet,
     * scoped to .leaflet-* and loaded only when a reader asks for the map.
     * Anything else appearing here is a second CSS system arriving by
     * accident, which CLAUDE.md forbids.
     */
    public function test_the_vendor_directory_holds_only_what_is_allowed(): void
    {
        $vendored = array_map('basename', glob(public_path('css/vendor/*.css')));
        sort($vendored);

        $this->assertSame(['bootstrap-grid.min.css', 'leaflet.css'], $vendored);
    }

    public function test_leaflet_styles_only_its_own_widget(): void
    {
        $css = file_get_contents(public_path('css/vendor/leaflet.css'));

        // Every selector is scoped to the map. Nothing reaches a page
        // element, so it cannot restyle the design system.
        $this->assertStringNotContainsString('body{', $css);
        $this->assertStringNotContainsString('body {', $css);
        $this->assertStringNotContainsString(':root{', $css);
    }

    public function test_the_grid_is_bound_to_the_spacing_and_layout_tokens(): void
    {
        $bridge = $this->css('css/grid.css');

        $this->assertStringContainsString('--bs-gutter-x: calc(var(--page-x) * 2)', $bridge);
        $this->assertStringContainsString('max-width: var(--w-page)', $bridge);
    }

    public function test_tokens_load_before_bootstrap_and_the_bridge_loads_after_it(): void
    {
        /*
         * The sign-in page rather than the homepage.
         *
         * This is a test about the order of three stylesheet links in the
         * layout, and it had a hidden dependency on the homepage record
         * existing in whatever database it happened to run against. The
         * sign-in page renders the same layout and needs no content at all,
         * so the test now measures only what it is about.
         */
        $html = $this->get('/en/login')->getContent();

        $tokens = strpos($html, 'css/tokens.css');
        $grid = strpos($html, 'css/vendor/bootstrap-grid.min.css');
        $bridge = strpos($html, 'css/grid.css');

        $this->assertNotFalse($tokens);
        $this->assertNotFalse($grid);
        $this->assertNotFalse($bridge);
        $this->assertLessThan($grid, $tokens, 'Tokens must be defined before the grid uses them.');
        $this->assertLessThan($bridge, $grid, 'The bridge must load after the grid it rebinds.');
    }

    public function test_the_promoted_colours_are_named_tokens(): void
    {
        $tokens = $this->css('css/tokens.css');

        foreach ([
            '--c-border:#908977',
            '--c-disabled:#6E7A85',
            '--c-navy-deep:#050D18',
            '--c-navy-edge:#5A6680',
            '--c-navy-soft:#6B7691',
            '--c-amber-light:#FDF0DC',
            '--c-amber-mid:#E5BE87',
            '--c-amber-dark:#5C3404',
            '--c-green-mid:#B6DCC5',
            '--c-red-mid:#E3B7B8',
            '--c-red-dark:#6E1A1D',
        ] as $token) {
            $this->assertStringContainsString($token, $tokens);
        }
    }

    public function test_the_stylesheets_carry_no_hard_coded_colour(): void
    {
        foreach (['css/base.css', 'css/chrome.css', 'css/grid.css', 'css/blocks.css', 'css/admin.css'] as $sheet) {
            $css = preg_replace('#/\*.*?\*/#s', '', $this->css($sheet));

            $this->assertDoesNotMatchRegularExpression(
                '/#[0-9A-Fa-f]{3,8}\b/',
                $this->withoutAdminGround($this->withoutBrandMarks($css)),
                $sheet.' must take every colour from tokens.css.'
            );
        }
    }

    /**
     * The second carve-out: --a-ground.
     *
     * #F0F4F1 is the back office's content ground and it is deliberately
     * not in tokens.css. It exists only behind the shell's cards, no public
     * rule may reach it, and putting it in the shared token file would
     * offer it to every surface on the platform. CLAUDE.md records it.
     *
     * Removed by its own declaration rather than by matching the value, so
     * a second #F0F4F1 written anywhere else still fails.
     */
    private function withoutAdminGround(string $css): string
    {
        return (string) preg_replace('/--a-ground:\s*#F0F4F1;/', '', $css, 1);
    }

    /** And the carve-out is one declaration, not a licence. */
    public function test_the_admin_ground_is_declared_once_and_only_in_the_admin_sheet(): void
    {
        $admin = preg_replace('#/\*.*?\*/#s', '', $this->css('css/admin.css'));

        // F11 (29 October 2026): the light ground is the UI system's pale
        // green, --u-pale (#F4F8F6), declared once; the old #F0F4F1 is gone.
        $this->assertSame(1, substr_count($admin, '--a-ground: var(--u-pale'), '--a-ground is written more than once.');
        $this->assertSame(0, substr_count($admin, '#F0F4F1'));

        // Not offered to the public surfaces.
        foreach (['css/tokens.css', 'css/base.css', 'css/chrome.css', 'css/blocks.css'] as $sheet) {
            $this->assertStringNotContainsString('--a-ground', $this->css($sheet), $sheet.' reaches an admin-only token.');
            $this->assertStringNotContainsString('#F0F4F1', $this->css($sheet));
        }
    }

    /**
     * The one carve-out: third-party brand marks.
     *
     * X's black, LinkedIn's #0A66C2, Facebook's #1877F2 and YouTube's
     * #FF0000 are registered trademarks whose owners' guidelines specify
     * the colour, and a recoloured brand mark is a guideline breach.
     * CLAUDE.md carries the rule and scopes it.
     *
     * Removed by their own selectors rather than by matching the hex, so a
     * stray #1877F2 anywhere else in the file still fails. That is the
     * difference between a carve-out and a hole.
     */
    private function withoutBrandMarks(string $css): string
    {
        return (string) preg_replace(
            '/\.c-footer__social a\[data-network="(?:x|linkedin|facebook|youtube)"\] \{[^}]*\}/',
            '',
            $css
        );
    }

    public function test_the_brand_mark_carve_out_is_narrow(): void
    {
        // The carve-out must not become a hole. Only the four footer
        // selectors may carry a hex, and only their own colour.
        $css = preg_replace('#/\*.*?\*/#s', '', $this->css('css/blocks.css'));

        preg_match_all('/([^{}]*)\{([^{}]*#[0-9A-Fa-f]{3,8}[^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        foreach ($rules as $rule) {
            $this->assertMatchesRegularExpression(
                '/\.c-footer__social a\[data-network="(?:x|linkedin|facebook|youtube)"\]/',
                trim($rule[1]),
                'A hex colour outside the brand-mark carve-out: '.trim($rule[1])
            );
        }

        $this->assertStringContainsString(
            'One carve-out: third-party brand marks',
            file_get_contents(base_path('CLAUDE.md')),
        );
    }

    /**
     * The neutrals and the notice tints are tokens now, so no stylesheet may
     * write them as literals again.
     */
    public function test_the_named_colours_appear_only_in_the_token_file(): void
    {
        foreach (['css/base.css', 'css/chrome.css', 'css/grid.css', 'css/blocks.css', 'css/admin.css'] as $sheet) {
            // Comments are stripped first. A stylesheet that quotes the
            // approved screen's own literal in a comment, next to the token
            // it uses instead, is documenting the decision rather than
            // making it, and that is exactly what should be encouraged.
            $css = preg_replace('#/\*.*?\*/#s', '', $this->css($sheet));

            foreach ([
                '#908977', '#6E7A85', '#050D18', '#5A6680', '#6B7691',
                '#FDF0DC', '#E5BE87', '#5C3404', '#B6DCC5', '#E3B7B8', '#6E1A1D',
            ] as $literal) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $literal,
                    $css,
                    $sheet.' must use the named token, not '.$literal.'.'
                );
            }
        }
    }

    public function test_the_heading_alternate_typeface_is_gone(): void
    {
        $this->assertStringNotContainsString('--f-head-alt', $this->css('css/tokens.css'));
        $this->assertStringNotContainsString('Source Serif', $this->css('css/fonts.css'));
    }
}
