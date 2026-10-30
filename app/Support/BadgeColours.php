<?php

namespace App\Support;

/**
 * The colours a newsroom category badge may take.
 *
 * Each choice is a pair of existing tokens from public/css/tokens.css, a
 * ground and a text colour, never a free value: CLAUDE.md fixes the
 * palette, and a colour typed into a form is how a purple nobody approved
 * reaches the site. The five pairs the badges used before 29 October 2026
 * are here with the red notice pair; every pair is measured below and a
 * pair under 4.5:1 is not offered (tests/Feature/TaxonomyAdminTest.php).
 */
final class BadgeColours
{
    /** key => [ground token, ground hex, text token, text hex] */
    public const PAIRS = [
        'navy' => ['--c-navy', '#0F1B2D', '--c-white', '#FFFFFF'],
        'green' => ['--c-green-light', '#E8F4EC', '--c-green-dark', '#0E5536'],
        'amber' => ['--c-amber-light', '#FDF0DC', '--c-amber-dark', '#5C3404'],
        'red' => ['--c-red-light', '#FBEDED', '--c-red-dark', '#6E1A1D'],
        'purple' => ['--c-purple-light', '#EFEAF7', '--c-purple-dark', '#45306E'],
        'grey' => ['--c-grey-light', '#ECEEF1', '--c-charcoal', '#1F2937'],
    ];

    public const MINIMUM = 4.5;

    /** @return list<string> the keys whose pair clears 4.5:1 */
    public static function keys(): array
    {
        return array_values(array_filter(
            array_keys(self::PAIRS),
            fn (string $key) => self::contrast($key) >= self::MINIMUM,
        ));
    }

    public static function style(?string $key): string
    {
        [$ground, , $text] = self::PAIRS[$key] ?? self::PAIRS['navy'];

        return 'background: var('.$ground.'); color: var('.$text.')';
    }

    /** WCAG 2.1 contrast ratio of a pair's text on its ground. */
    public static function contrast(string $key): float
    {
        [, $ground, , $text] = self::PAIRS[$key];
        $a = self::luminance($ground);
        $b = self::luminance($text);

        return round((max($a, $b) + 0.05) / (min($a, $b) + 0.05), 2);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channel = function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(substr($hex, 0, 2))
            + 0.7152 * $channel(substr($hex, 2, 2))
            + 0.0722 * $channel(substr($hex, 4, 2));
    }
}
