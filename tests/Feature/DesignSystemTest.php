<?php

namespace Tests\Feature;

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
        $html = $this->get('/en')->getContent();

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
            '--c-border:#C9C2B2',
            '--c-border-input:#B9B2A2',
            '--c-disabled:#6E7A85',
            '--c-navy-deep:#050D18',
            '--c-navy-edge:#4A5670',
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
        foreach (['css/base.css', 'css/chrome.css', 'css/grid.css'] as $sheet) {
            $this->assertDoesNotMatchRegularExpression(
                '/#[0-9A-Fa-f]{3,8}\b/',
                preg_replace('#/\*.*?\*/#s', '', $this->css($sheet)),
                $sheet.' must take every colour from tokens.css.'
            );
        }
    }

    /**
     * The neutrals and the notice tints are tokens now, so no stylesheet may
     * write them as literals again.
     */
    public function test_the_named_colours_appear_only_in_the_token_file(): void
    {
        foreach (['css/base.css', 'css/chrome.css', 'css/grid.css'] as $sheet) {
            $css = $this->css($sheet);

            foreach ([
                '#C9C2B2', '#B9B2A2', '#6E7A85', '#050D18', '#4A5670', '#6B7691',
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
