<?php

namespace Tests\Feature;

use App\Support\Locales;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Native form controls on a dark ground.
 *
 * The comparator's selects on the homepage navy band rendered their open
 * menu as charcoal text on navy: only the row the operating system
 * highlighted could be read. The dark-ground override set a colour on the
 * option and no background, so the option inherited the select's navy.
 *
 * The lesson is not "add a background to that rule". It is that an option
 * inherits from its select, so colour and background have to be named
 * together, on the component, once — otherwise the fix covers the one
 * ground somebody remembered and misses the next.
 */
class DarkGroundControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(IndicatorSeeder::class);
        $this->seed(IndicatorValueSeeder::class);
    }

    public function test_the_option_list_names_both_colour_and_background(): void
    {
        $css = (string) file_get_contents(public_path('css/base.css'));

        preg_match('/\.c-select option,\s*\.c-select optgroup \{([^}]*)\}/s', $css, $rule);

        $this->assertNotEmpty($rule, 'The select component sets nothing on its options.');
        $this->assertMatchesRegularExpression('/color: var\(--c-charcoal\)/', $rule[1]);
        $this->assertMatchesRegularExpression('/background: var\(--c-white\)/', $rule[1]);
    }

    public function test_the_admin_option_list_names_both_from_one_surface_set(): void
    {
        // The back office swaps its surface set under prefers-color-scheme,
        // so its options must take both values from that set rather than
        // being pinned to white.
        $css = (string) file_get_contents(public_path('css/admin.css'));

        preg_match('/\.a-select option,\s*\.a-select optgroup \{([^}]*)\}/s', $css, $rule);

        $this->assertNotEmpty($rule);
        $this->assertMatchesRegularExpression('/color: var\(--a-ink\)/', $rule[1]);
        $this->assertMatchesRegularExpression('/background: var\(--a-surface\)/', $rule[1]);
    }

    public function test_no_dark_ground_rule_sets_an_option_colour_without_a_background(): void
    {
        // The exact shape of the original defect, wherever it recurs.
        foreach (['blocks.css', 'base.css', 'chrome.css', 'comparator.css', 'admin.css'] as $file) {
            $css = (string) file_get_contents(public_path('css/'.$file));
            $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

            preg_match_all('/([^{}]*\boption\b[^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

            foreach ($rules as $rule) {
                $body = $rule[2];

                if (! preg_match('/(^|;)\s*color\s*:/', $body)) {
                    continue;
                }

                // A pseudo-class refines a base rule that has already set
                // the ground: "option:disabled { color: … }" is correct and
                // is not the defect. Only a plain option selector claiming a
                // colour with no ground of its own is.
                if (preg_match('/option\s*:[a-z-]+/', $rule[1])) {
                    continue;
                }

                // A colour-only option rule is the bug: the option keeps
                // whatever ground its select has, which on navy is navy.
                $this->assertMatchesRegularExpression(
                    '/(^|;)\s*background\s*:/',
                    $body,
                    trim($rule[1])." in {$file} gives an option a colour and no background. "
                    .'An option inherits its select\'s ground, so on a dark control this is '
                    .'dark text on dark.',
                );
            }
        }
    }

    public function test_the_homepage_has_no_dark_section_and_its_selects_carry_the_component(): void
    {
        // Day 2: the light theme leaves no navy ground on the homepage, so
        // the comparison's selects sit on a light ground. They still carry
        // .c-select, which is what styles them and their options.
        foreach (array_keys(Locales::all()) as $locale) {
            $html = $this->get("/{$locale}")->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression(
                '/data-ground="navy(?:-mid)?"/',
                $html,
                "A homepage section in {$locale} is still on a navy ground.",
            );

            preg_match_all('/<select[^>]*name="cmp_[^"]*"[^>]*>/', $html, $selects);

            $this->assertNotEmpty($selects[0], "No comparison select found in {$locale} to check.");

            // .c-select (blocks) or .u-select (the UI system since the
            // Concept 3 home): either component styles the select and its options.
            foreach ($selects[0] as $select) {
                $this->assertMatchesRegularExpression('/class="[^"]*\\b[cu]-select\\b/', $select, "A comparison select in {$locale} carries no select component.");
            }
        }
    }

    public function test_no_input_or_textarea_sits_on_a_dark_ground_unstyled(): void
    {
        // There are none today. If one arrives, it needs the same treatment
        // and this says so at the moment it appears rather than later.
        $html = $this->get('/en')->assertOk()->getContent();

        preg_match_all('/data-ground="(navy(?:-mid)?)"/', $html, $grounds, PREG_OFFSET_CAPTURE);

        foreach ($grounds[0] as [$ignored, $offset]) {
            $end = strpos($html, '</section>', $offset);
            $section = substr($html, $offset, $end - $offset);

            foreach (['input', 'textarea'] as $tag) {
                preg_match_all('/<'.$tag.'\b[^>]*>/', $section, $found);

                foreach ($found[0] as $control) {
                    if (str_contains($control, 'type="hidden"')) {
                        continue;
                    }

                    $this->assertMatchesRegularExpression(
                        '/class="[^"]*c-(input|field|search)/',
                        $control,
                        "A <{$tag}> sits on a dark ground with no component class. Its text, "
                        .'placeholder and focus ring all inherit, and none of them was designed '
                        .'against navy.',
                    );
                }
            }
        }
    }
}
