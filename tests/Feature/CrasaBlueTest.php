<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The CRASA blue: the navy of the crasa.org buttons, the platform's
 * secondary colour since Day 3 of the workshop.
 *
 * It is defined once, as a hex, in tokens.css. The UI system, the public
 * port and the back office read it through their own aliases, and no rule
 * writes the hex. ContrastTest measures it against the tokens.css values;
 * this measures it against the values the light surfaces actually use,
 * which live in theme-light.css, k.css, ui.css and admin-tokens.css, and
 * holds the rules that decide where it appears.
 */
class CrasaBlueTest extends TestCase
{
    private const BLUE = '#052D53';

    private function css(string $sheet): string
    {
        return (string) file_get_contents(public_path('css/'.$sheet));
    }

    private function luminance(string $hex): float
    {
        $channels = [];
        foreach ([1, 3, 5] as $offset) {
            $value = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function ratio(string $a, string $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public function test_the_blue_is_defined_once_as_a_token(): void
    {
        $this->assertStringContainsString('--c-crasa-blue:'.self::BLUE.';', $this->css('tokens.css'));

        $count = 0;
        foreach (glob(public_path('css/*.css')) as $file) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($file));
            // A var() fallback repeats the value; it is not a second definition.
            $css = (string) preg_replace('/var\(--[a-z]+-crasa-blue,\s*#052D53\)/i', '', $css);
            $count += preg_match_all('/#052D53\b/i', $css);
        }
        $this->assertSame(1, $count, 'The CRASA blue hex is written outside its token.');
    }

    public function test_each_layer_reads_it_through_its_alias(): void
    {
        $this->assertStringContainsString('--u-crasa-blue: var(--c-crasa-blue', $this->css('ui.css'));
        $this->assertStringContainsString('--k-crasa-blue: var(--c-crasa-blue', $this->css('k.css'));
        $this->assertStringContainsString('--a-blue: var(--c-crasa-blue', $this->css('admin-tokens.css'));
    }

    public function test_the_public_closing_bands_and_date_blocks_take_it_and_green_keeps_the_identity(): void
    {
        $k = $this->css('k.css');
        $ui = $this->css('ui.css');

        $this->assertStringContainsString('.k-smap { background: var(--k-deep);', $k); // footer area green again (client, 30 October)
        $this->assertStringContainsString('.k-foot { background: var(--k-deep);', $k);
        $this->assertMatchesRegularExpression('/\.u-cta \{[^}]*background: var\(--u-crasa-blue\)/', $ui);
        $this->assertMatchesRegularExpression('/\.u-date \{[^}]*background: var\(--u-crasa-blue\); color: var\(--u-white\)/', $ui);
        $this->assertStringContainsString('.c-cmp-chart__bar--second { fill: var(--u-crasa-blue', $this->css('comparator.css'));

        // Public primary buttons, the hero and the menu stay green.
        $this->assertStringContainsString('.k-btn--green { background: var(--k-green);', $k);
        $this->assertMatchesRegularExpression('/\.u-btn--primary \{[^}]*background: var\(--u-green\)/', $ui);
    }

    public function test_the_signed_in_surfaces_use_it_for_buttons_and_the_current_item_without_a_box(): void
    {
        $admin = $this->css('admin.css');

        $this->assertStringContainsString('--a-btn: var(--a-blue,', $admin);
        $this->assertStringContainsString('--a-side-current-text: var(--a-blue);', $admin);
        $this->assertStringContainsString('body[data-surface="admin"] .a-side__link.is-current { color: var(--a-side-current-text); font-weight: 600;', $admin);
        $this->assertStringContainsString('body[data-surface="admin"] :is(.u-btn--primary, .c-btn--primary) { background: var(--a-btn);', $admin);

        // The last current-item rule clears the older navy box: text only.
        preg_match_all('/body\[data-surface="admin"\] \.a-side__link\.is-current \{([^}]*)\}/', $admin, $rules);
        $last = end($rules[1]);
        $this->assertStringContainsString('background: none;', $last);
        $this->assertStringContainsString('border-left-color: transparent;', $last);

        // Hover keeps the 1px underline.
        $this->assertStringContainsString('body[data-surface="admin"] .a-side__link:hover span { text-decoration: underline; text-decoration-thickness: 1px;', $admin);
    }

    /**
     * The pairs on the light surfaces, with the values those surfaces use.
     *
     * @return array<string, array{string, string, float}>
     */
    public static function pairs(): array
    {
        return [
            'white on the blue: buttons, date blocks, band headings' => ['#FFFFFF', self::BLUE, 4.5],
            'blue on white: current sidebar item' => [self::BLUE, '#FFFFFF', 4.5],
            'blue on the back office ground' => [self::BLUE, '#F4F8F6', 4.5],
            'blue on the green tint' => [self::BLUE, '#EAF4EF', 4.5],
            'blue on the blue tint' => [self::BLUE, '#EEF2FA', 4.5],
            'footer text on the blue' => ['#E3EEE8', self::BLUE, 4.5],
            'footer label on the blue' => ['#D4DDF2', self::BLUE, 4.5],
            'band text on the blue' => ['#E7EFEA', self::BLUE, 4.5],
            'band secondary button edge on the blue' => ['#B9D9C8', self::BLUE, 3.0],
            'green button edge on the blue' => ['#0F8757', self::BLUE, 3.0],
            'green series against the blue series' => ['#0F8757', self::BLUE, 3.0],
            'blue bar on a white chart' => [self::BLUE, '#FFFFFF', 3.0],
            'white on the hover navy' => ['#FFFFFF', '#0F1B2D', 4.5],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pairs')]
    public function test_light_surface_pairs_meet_aa(string $foreground, string $background, float $minimum): void
    {
        $this->assertGreaterThanOrEqual($minimum, round($this->ratio($foreground, $background), 2));
    }

    public function test_the_pairs_are_written_down(): void
    {
        $doc = base_path('docs/accessibility/contrast-crasa-blue.md');
        $this->assertFileExists($doc);
        $this->assertStringContainsString('#052D53', (string) file_get_contents($doc));
    }
}
