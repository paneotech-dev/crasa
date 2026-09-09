<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A block's own section must be able to span the page.
 *
 * The statement band shipped rendering as a column thirty characters wide
 * with the page ground beside it. Its markup was right, its own rules were
 * right, and its stylesheet arrived: the section carries
 * class="b b-statement", and an unrelated pull-quote component had claimed
 * .b-statement years earlier with max-width:30ch on it. The band inherited
 * the clamp from a component it has nothing to do with.
 *
 * Nothing referenced the pull quote, so it had no way of being noticed. The
 * class-rule guard could not see it either: the class had a rule, which was
 * the whole problem.
 *
 * This is the narrow, checkable version of the lesson. A class used as a
 * block's section root must not be given a width by anything, because a
 * section is a full-bleed band by definition.
 */
class BlockRootStyleTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function blockRoots(): array
    {
        $roots = [];

        foreach (glob(dirname(__DIR__, 2).'/resources/views/blocks/*.blade.php') as $view) {
            $markup = (string) file_get_contents($view);

            // The section element the block renders, and the b-* class on it.
            if (! preg_match('/<section\s+class="b\b([^"]*)"/', $markup, $m)) {
                continue;
            }

            foreach (preg_split('/\s+/', trim($m[1])) as $class) {
                // Block roots only. "b--medium" and "b--tight" are the
                // section's own padding modifiers, not a block's name.
                if ($class !== '' && str_starts_with($class, 'b-') && ! str_starts_with($class, 'b--')) {
                    $roots[basename($view, '.blade.php').' → .'.$class] = [$class, basename($view)];
                }
            }
        }

        return $roots;
    }

    #[DataProvider('blockRoots')]
    public function test_a_block_section_root_is_never_given_a_width(string $class, string $view): void
    {
        $css = (string) file_get_contents(public_path('css/blocks.css'));
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        // Every rule whose selector list contains this class on its own,
        // unqualified by a descendant or another class.
        preg_match_all('/(^|\})([^{}]*)\{([^{}]*)\}/s', $css, $rules, PREG_SET_ORDER);

        // A block root with no rule at all is its own bug, and asserting it
        // here keeps every case of this provider meaningful rather than
        // silently passing on a class the stylesheet has never heard of.
        $this->assertStringContainsString(
            '.'.$class,
            $css,
            "{$view} renders <section class=\"b {$class}\"> and blocks.css has no rule for it.",
        );

        foreach ($rules as $rule) {
            $selectors = array_map('trim', explode(',', $rule[2]));

            foreach ($selectors as $selector) {
                if (trim($selector) !== '.'.$class) {
                    continue;
                }

                foreach (['max-width', 'width'] as $property) {
                    $this->assertDoesNotMatchRegularExpression(
                        '/(^|;)\s*'.$property.'\s*:/',
                        $rule[3],
                        "{$view} renders <section class=\"b {$class}\">, and .{$class} is given a "
                        ."{$property}. A block's section is a full-bleed band; a width on it clamps "
                        .'the whole section. Check whether another component has claimed the name.',
                    );
                }
            }
        }
    }

    public function test_the_pull_quote_kept_its_width_under_its_own_name(): void
    {
        // The rename must not have thrown the component away: it is still a
        // 30ch pull quote, it simply no longer collides with a block.
        $css = (string) file_get_contents(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression(
            '/\.b-pullquote \{[^}]*max-width: 30ch/s',
            $css,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.b-statement \{[^}]*max-width/s',
            $css,
            'The statement band is clamped again.',
        );
    }
}
