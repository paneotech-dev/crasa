<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UX-04 and UX-18 in the back office stylesheet: the sidebar's hover,
 * pressed and current rows look different from each other, and the search
 * placeholders are drawn at full strength in a colour that clears 4.5:1.
 */
class AdminChromeStatesTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(public_path('css/admin.css'));
    }

    public function test_sidebar_hover_pressed_and_current_are_distinct(): void
    {
        $css = $this->css();
        preg_match('/\.a-side__link:hover \{([^}]*)\}/', $css, $hover);
        preg_match('/\.a-side__link:active \{([^}]*)\}/', $css, $active);
        preg_match('/\.a-side__link\.is-current \{([^}]*)\}/', $css, $current);

        $this->assertNotEmpty($hover);
        $this->assertNotEmpty($active);
        $this->assertNotEmpty($current);
        preg_match('/background:\s*([^;]+);/', $hover[1], $h);
        preg_match('/background:\s*([^;]+);/', $active[1], $a);
        preg_match('/background:\s*([^;]+);/', $current[1], $c);
        $this->assertCount(3, array_unique([$h[1], $a[1], $c[1]]), 'Two of the sidebar states share a ground.');
        $this->assertStringContainsString('border-left-color', $current[1]);
        $this->assertMatchesRegularExpression('/\.a-side__link:focus-visible \{[^}]*outline/', $css);
    }

    public function test_search_placeholders_clear_four_and_a_half_to_one(): void
    {
        $this->assertMatchesRegularExpression('/\.a-top__field::placeholder,\s*\.a-search__field::placeholder \{ color: var\(--a-ink-quiet\); opacity: 1; \}/', $this->css());

        $tokens = file_get_contents(public_path('css/tokens.css'));
        preg_match('/--c-slate:\s*(#[0-9A-Fa-f]{6})/', $tokens, $slate);
        preg_match('/--c-white:\s*(#[0-9A-Fa-f]{6})/', $tokens, $white);
        $this->assertGreaterThanOrEqual(4.5, $this->contrast($slate[1], $white[1]));
    }

    private function contrast(string $a, string $b): float
    {
        $l = function (string $hex): float {
            $c = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
            $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$l($a), $l($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }
}
