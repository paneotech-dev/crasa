<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * WCAG 2.1 AA contrast, measured against the actual token values.
 *
 * Written after a computed audit found three failures that had been in the
 * build since the foundation layer and that nothing was looking at. Assumed
 * contrast is not contrast.
 *
 * The pairs below are the ones the stylesheets actually use. Adding a pair
 * to a stylesheet without adding it here is the gap this cannot close; the
 * list is maintained by hand for that reason.
 */
class ContrastTest extends TestCase
{
    /** @var array<string, string> */
    private array $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        preg_match_all(
            '/(--[cd]-[a-z0-9-]+)\s*:\s*(#[0-9A-Fa-f]{6})/',
            file_get_contents(public_path('css/tokens.css')),
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $this->tokens[$match[1]] = $match[2];
        }
    }

    private function luminance(string $hex): float
    {
        $channels = [];

        foreach ([1, 3, 5] as $offset) {
            $value = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function ratio(string $foreground, string $background): float
    {
        $this->assertArrayHasKey($foreground, $this->tokens, "Unknown token {$foreground}");
        $this->assertArrayHasKey($background, $this->tokens, "Unknown token {$background}");

        $a = $this->luminance($this->tokens[$foreground]);
        $b = $this->luminance($this->tokens[$background]);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Text pairs. 4.5:1 for body copy under SC 1.4.3.
     *
     * @return array<string, array{string, string}>
     */
    public static function textPairs(): array
    {
        return [
            'body on cream' => ['--c-charcoal', '--c-cream'],
            'body on white' => ['--c-charcoal', '--c-white'],
            'heading on cream' => ['--c-navy', '--c-cream'],
            'meta on cream' => ['--c-slate', '--c-cream'],
            'meta on white' => ['--c-slate', '--c-white'],
            'link on cream' => ['--c-green-dark', '--c-cream'],
            'link on white' => ['--c-green-dark', '--c-white'],
            'hero body on navy' => ['--c-sand', '--c-navy'],
            'hero heading on navy' => ['--c-white', '--c-navy'],
            'footer email on navy' => ['--c-green-light', '--c-navy'],
            'drawer link on navy' => ['--c-cream', '--c-navy'],
            'primary button' => ['--c-white', '--c-green-dark'],
            'navy button' => ['--c-white', '--c-navy'],
            'pending pill' => ['--c-amber-dark', '--c-amber-light'],
            'validated pill' => ['--c-green-dark', '--c-green-light'],
            'error pill' => ['--c-red', '--c-red-light'],
            'error text on white' => ['--c-red', '--c-white'],
            'error notice body' => ['--c-red-dark', '--c-red-light'],
            'archived pill' => ['--c-slate', '--c-white'],

            // The dark surface set. Screen 09 states its own figures: body
            // ink at 12.4:1 on the page ground, dimmed ink at 6.8:1, and the
            // green raised to clear AA against the dark surface. Measured
            // here rather than taken on trust.
            'dark body ink on ground' => ['--d-ink', '--d-bg'],
            'dark body ink on surface' => ['--d-ink', '--d-surface'],
            'dark dimmed ink on surface' => ['--d-ink-dim', '--d-surface'],
            'dark link on surface' => ['--d-green', '--d-surface'],
            'dark current section' => ['--c-white', '--d-current'],

            // The hero's data panel, screen 02's slide with no photograph.
            // A navy-mid panel inside a navy band is two dark grounds
            // stacked, which is exactly where a text colour that cleared
            // the outer one can fail the inner one.
            'panel title on navy-mid' => ['--c-green-light', '--c-navy-mid'],
            'panel note on navy-mid' => ['--c-sand', '--c-navy-mid'],
            'panel bar name on navy-mid' => ['--c-cream', '--c-navy-mid'],
            'panel bar value on navy-mid' => ['--c-white', '--c-navy-mid'],
            'panel count label on navy-mid' => ['--c-sand', '--c-navy-mid'],
            'panel notice body on navy-mid' => ['--c-amber-light', '--c-navy-mid'],

            // The back office's dark theme, after the rebuild audit of
            // 16 September 2026 measured the rendered pages in both themes.
            'admin heading on dark ground' => ['--c-white', '--d-bg'],
            'admin heading on dark surface' => ['--c-white', '--d-surface'],
            'dark primary button' => ['--d-bg', '--d-green'],
            'dark primary button hover' => ['--d-bg', '--c-green-step-1'],
            'dark danger ink on surface' => ['--c-red-mid', '--d-surface'],
            'dark danger ink on ground' => ['--c-red-mid', '--d-bg'],
            'dark warning ink on surface' => ['--c-amber-mid', '--d-surface'],
            'dark current item, inverse' => ['--d-surface', '--c-white'],
            // Tinted status boxes keep their tint in both themes, and their
            // ink with it.
            'incomplete row value' => ['--c-charcoal', '--c-amber-light'],
            'incomplete row meta' => ['--c-slate', '--c-amber-light'],
            'incomplete row action' => ['--c-green-dark', '--c-amber-light'],
            'incomplete row delete' => ['--c-red', '--c-amber-light'],
            'untranslated field text' => ['--c-charcoal', '--c-red-light'],
        ];
    }

    /**
     * Non-text pairs. 3:1 for control boundaries and focus indicators under
     * SC 1.4.11.
     *
     * @return array<string, array{string, string}>
     */
    public static function nonTextPairs(): array
    {
        return [
            'focus ring on cream' => ['--c-amber', '--c-cream'],
            'focus ring on white' => ['--c-amber', '--c-white'],
            'focus ring on navy' => ['--c-amber', '--c-navy'],
            'control border on white' => ['--c-border', '--c-white'],
            'control border on cream' => ['--c-border', '--c-cream'],
            'switcher border on navy' => ['--c-navy-edge', '--c-navy'],

            // The back office. Controls sit on white and on the admin
            // ground; the panel rule is decorative and is not measured.
            'control border on admin ground' => ['--c-border', '--c-admin-bg'],
            'focus ring on admin ground' => ['--c-amber', '--c-admin-bg'],
            // The panel rule is decorative and is not measured, in dark
            // exactly as in light. What is measured is the boundary of a
            // control on the dark surface.
            'control border on dark surface' => ['--c-border', '--d-surface'],

            // The hero panel's bar against its own track. A bar is a
            // non-text graphic carrying information, so 3:1 under
            // SC 1.4.11. The figure is printed beside it as well, so the
            // bar is never the only carrier.
            'hero bar fill on track' => ['--d-green', '--d-bg'],

            // The dark primary button's fill against what it sits on. The
            // green-dark fill it replaced measured 1.88:1 on the dark card.
            'dark primary fill on surface' => ['--d-green', '--d-surface'],
            'dark primary fill on ground' => ['--d-green', '--d-bg'],
            'dark primary hover fill on surface' => ['--c-green-step-1', '--d-surface'],
            'light primary fill on white' => ['--c-green-dark', '--c-white'],
        ];
    }

    /**
     * The section band's photograph overlay.
     *
     * A band may carry a photograph under a heavy navy overlay, so the type
     * sits on a colour nobody wrote down: it is the overlay composited over
     * whatever the image happens to be underneath.
     *
     * The worst case is a pure-white image, which specular highlights make
     * real rather than theoretical, so that is what is measured. If every
     * token clears AA against navy-over-white, it clears AA against any
     * photograph.
     */
    public function test_the_band_overlay_clears_aa_over_the_brightest_possible_photograph(): void
    {
        $overlay = 0.82;
        $worstCase = $this->composite($this->tokens['--c-navy'], '#FFFFFF', $overlay);

        foreach ([
            'band title' => '--c-white',
            'band eyebrow' => '--c-green-light',
            'band standfirst' => '--c-sand',
            'band count label' => '--c-sand',
        ] as $role => $token) {
            $ratio = $this->ratioOf($this->tokens[$token], $worstCase);

            $this->assertGreaterThanOrEqual(
                4.5,
                $ratio,
                sprintf('%s is %.2f:1 over a white photograph at %.0f%% overlay.', $role, $ratio, $overlay * 100)
            );
        }

        // And the stylesheet has to be using the value that was measured.
        $this->assertStringContainsString(
            'rgba(15, 27, 45, .82)',
            file_get_contents(public_path('css/blocks.css')),
            'The band overlay is not the value this test measures.'
        );
    }

    /**
     * The statement band's photograph, added 9 September 2026.
     *
     * The instruction was to give it the hero's photo-slide treatment and
     * then measure. The measurement is why it does not have it: the hero's
     * scrim runs .94 navy at the left to .34 at the right, because its copy
     * is one column on the left and the right third is deliberately texture.
     * This band has the statement left and fourteen figure cells right, and
     * at .34 over a white pixel the cells measure 2.14:1.
     *
     * So it takes the flat .82 the section bands already carry as the
     * measured floor. Every colour on the band is checked here, including
     * the ones the section-band test does not use.
     */
    public function test_the_statement_band_clears_aa_over_its_photograph(): void
    {
        $worstCase = $this->composite($this->tokens['--c-navy'], '#FFFFFF', 0.82);

        foreach ([
            'statement quote' => '--c-white',
            'statement eyebrow' => '--c-green-light',
            'statement action' => '--c-white',
            'grid label over the cells' => '--c-green-light',
            'grid cell value' => '--c-white',
            'grid cell label' => '--c-sand',
            'grid note' => '--c-sand',
        ] as $role => $token) {
            $ratio = $this->ratioOf($this->tokens[$token], $worstCase);

            $this->assertGreaterThanOrEqual(
                4.5,
                $ratio,
                sprintf('%s is %.2f:1 over a white pixel in the photograph.', $role, $ratio)
            );
        }

        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression(
            '/\.b-statement__scrim \{[^}]*rgba\(15, 27, 45, \.82\)/s',
            $css,
            'The statement band is not using the overlay this test measures.'
        );

        // And it must not quietly go back to the hero's gradient, which is
        // what the measurement rejected.
        $this->assertStringNotContainsString(
            'b-hero__scrim b-statement__layer',
            file_get_contents(base_path('resources/views/blocks/statement-band.blade.php')),
            'The band has the hero gradient again; its right column fails at .34.'
        );
    }

    private function composite(string $foreground, string $background, float $alpha): string
    {
        $out = '#';

        foreach ([1, 3, 5] as $offset) {
            $f = hexdec(substr($foreground, $offset, 2));
            $b = hexdec(substr($background, $offset, 2));
            $out .= str_pad(dechex((int) round($alpha * $f + (1 - $alpha) * $b)), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    private function ratioOf(string $foreground, string $background): float
    {
        $a = $this->luminance($foreground);
        $b = $this->luminance($background);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    #[DataProvider('textPairs')]
    public function test_text_pairs_meet_four_and_a_half_to_one(string $foreground, string $background): void
    {
        $ratio = $this->ratio($foreground, $background);

        $this->assertGreaterThanOrEqual(
            4.5,
            $ratio,
            sprintf(
                '%s on %s is %.2f:1, below the 4.5:1 required by SC 1.4.3.',
                $this->tokens[$foreground], $this->tokens[$background], $ratio
            )
        );
    }

    #[DataProvider('nonTextPairs')]
    public function test_non_text_pairs_meet_three_to_one(string $foreground, string $background): void
    {
        $ratio = $this->ratio($foreground, $background);

        $this->assertGreaterThanOrEqual(
            3.0,
            $ratio,
            sprintf(
                '%s on %s is %.2f:1, below the 3:1 required by SC 1.4.11.',
                $this->tokens[$foreground], $this->tokens[$background], $ratio
            )
        );
    }

    /**
     * The two border neutrals resolved to the same value once both had to
     * clear 3:1, so there is one. A reintroduced second one is a token that
     * cannot be told apart from the first.
     */
    public function test_there_is_only_one_border_neutral(): void
    {
        $this->assertArrayNotHasKey('--c-border-input', $this->tokens);

        foreach (['base.css', 'chrome.css', 'blocks.css'] as $sheet) {
            $this->assertStringNotContainsString(
                '--c-border-input',
                file_get_contents(public_path('css/'.$sheet)),
                $sheet.' still references the deleted token.'
            );
        }
    }

    /**
     * Disabled text sits below 4.5:1 and that is allowed: SC 1.4.3 exempts
     * text that is part of an inactive component. Pinned so nobody
     * re-discovers it and "fixes" a non-failure.
     */
    public function test_disabled_text_is_exempt_and_deliberately_not_raised(): void
    {
        $this->assertLessThan(4.5, $this->ratio('--c-disabled', '--c-cream'));
        $this->assertSame('#6E7A85', $this->tokens['--c-disabled']);
    }
}
