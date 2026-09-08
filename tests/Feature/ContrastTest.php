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
            '/(--c-[a-z-]+)\s*:\s*(#[0-9A-Fa-f]{6})/',
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
        ];
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
