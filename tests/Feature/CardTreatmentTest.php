<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * What a card is made of, against screen 01 line 754 and screen 02 line 341.
 *
 * Both draw the same thing: a white ground, a one-pixel sand border,
 * --r-md, --e-1 and --s-5 of padding. The build had --r-lg and --s-4, which
 * is a rounder, tighter card than either screen draws.
 */
class CardTreatmentTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(public_path('css/blocks.css'));
    }

    public function test_a_card_is_white_on_a_sand_border(): void
    {
        // Screen 02 line 341: "background:var(--c-white);border:1px solid
        // var(--c-sand)". A cream card on a cream ground has no visible
        // edge, which is a usability failure and not a style choice.
        $this->assertMatchesRegularExpression(
            '/\.b-card \{[^}]*background: var\(--c-white\)/',
            $this->css(),
        );
        $this->assertMatchesRegularExpression(
            '/\.b-card \{[^}]*border: 1px solid var\(--c-sand\)/',
            $this->css(),
        );
    }

    public function test_a_card_takes_the_panel_radius_not_the_modal_radius(): void
    {
        // Screen 01 gives --r-lg to the modal at line 745 and --r-md to
        // every card at 754. Screen 02 uses --r-lg in none of its 125
        // radius declarations.
        $this->assertMatchesRegularExpression(
            '/\.b-card \{[^}]*border-radius: var\(--r-md\)/',
            $this->css(),
        );
    }

    public function test_no_public_surface_uses_the_modal_radius(): void
    {
        foreach (['blocks.css', 'base.css', 'chrome.css'] as $file) {
            $css = file_get_contents(public_path('css/'.$file));

            // Comments name the token when explaining the decision.
            $css = preg_replace('#/\*.*?\*/#s', '', $css);

            $this->assertStringNotContainsString(
                'var(--r-lg)',
                $css,
                "{$file} applies --r-lg. The screens reserve it for modals, and "
                .'the public site has none.',
            );
        }
    }

    public function test_a_card_rests_at_the_first_elevation(): void
    {
        // --e-1 on every card and --e-2 or --e-3 on none of them is the
        // "same soft grey shadow under every card" failure. The elevations
        // encode hierarchy: screen 02 uses --e-1 nine times and --e-2 once.
        $this->assertMatchesRegularExpression(
            '/\.b-card \{[^}]*box-shadow: var\(--e-1\)/',
            $this->css(),
        );
    }

    public function test_a_card_is_padded_as_screen_02_draws_it(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.b-card__body \{[^}]*padding: var\(--s-5\)/',
            $this->css(),
        );
    }

    public function test_a_card_on_navy_keeps_its_white_ground(): void
    {
        // Only the border and the shadow change. A card that went
        // transparent on the dark band would stop being a card.
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.b\[data-ground="navy"\] \.b-card,[^{]*\{[^}]*border-color: var\(--c-navy-line\)/',
            $css,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.b\[data-ground="navy"\] \.b-card[^{]*\{[^}]*background:/',
            $css,
            'A card on navy must not repaint its ground.',
        );
    }
}
