<?php

namespace Tests\Feature;

use App\Models\IndicatorValue;
use App\Models\Publication;
use App\Support\Locales;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\PublicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * The hero carousel, screen 02 line 93.
 *
 * The rotation was built long before it had anything to rotate. Screen 02
 * draws three slides and the only way to fill them was a photograph, which
 * CRASA has not supplied; repeating one image three times to manufacture a
 * slider would have been worse than a static banner. These three carry
 * content instead, so they need no photograph and they differ because what
 * they say differs.
 */
class HeroCarouselTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedComposedPages();

        // Slides two and three drop themselves when the register cannot
        // support them, which is the right behaviour and is asserted
        // separately. Here they need something to show.
        $this->seed(IndicatorSeeder::class);
        $this->seed(IndicatorValueSeeder::class);
        $this->seed(PublicationSeeder::class);
    }

    private function home(): string
    {
        return $this->get('/en')->assertOk()->getContent();
    }

    public function test_three_slides_each_saying_something_different(): void
    {
        $html = $this->home();

        $this->assertSame(3, substr_count($html, 'data-hero-slide'));

        // All three are photo slides now, so what differentiates them is
        // the photograph and the copy rather than the panel beside it.
        // Screen 02 runs a photo slide's copy the full width and gives it
        // no second column; the figures live on the navy-mid band below.
        //
        // Three distinct photographs. The same one three times would be a
        // carousel that exists to move.
        foreach ([
            'hero-delegates-boardroom-01',
            'data-team-reviewing-charts-01',
            'institutional-two-person-meeting-01',
        ] as $photograph) {
            $this->assertStringContainsString($photograph, $html);
        }

        // And three distinct headlines, each with its own action.
        preg_match_all('/class="b-hero__headline"[^>]*>([^<]+)</', $html, $headlines);
        $this->assertCount(3, array_unique(array_map('trim', $headlines[1])));

        // No panel on any of them.
        $this->assertStringNotContainsString('b-hero__panel"', $html);
    }

    public function test_every_slide_is_named_not_just_numbered(): void
    {
        // "Slide 2 of 3" tells a reader how far along they are and nothing
        // about where the control would take them.
        $html = $this->home();

        foreach ([
            'Slide 1 of 3: what the register holds',
            'Slide 2 of 3: featured comparison',
            'Slide 3 of 3: latest publication',
        ] as $label) {
            // Once on the slide group, once on its dot.
            $this->assertSame(2, substr_count($html, $label), "\"{$label}\" is not on both the slide and its dot.");
        }
    }

    public function test_the_carousel_carries_its_controls_and_a_polite_live_region(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('data-hero-prev', $html);
        $this->assertStringContainsString('data-hero-next', $html);
        $this->assertSame(3, substr_count($html, 'data-hero-dot'));

        // SC 2.2.2: an auto-advancing region needs an explicit pause.
        $this->assertStringContainsString('data-hero-pause', $html);
        $this->assertMatchesRegularExpression('/aria-live="polite"[^>]*data-hero-live/', $html);
        $this->assertStringContainsString('aria-roledescription="carousel"', $html);
    }

    public function test_it_stacks_to_the_first_slide_with_no_script(): void
    {
        // The server-rendered floor. Slides two and three are hidden, the
        // first is not, and no control is required to read it.
        $html = $this->home();

        preg_match_all('/<div\s+class="b-hero__slide"(.*?)>/s', $html, $slides);

        $this->assertCount(3, $slides[1]);
        $this->assertStringNotContainsString('hidden', $slides[1][0], 'The first slide is served hidden.');
        $this->assertStringContainsString('hidden', $slides[1][1]);
        $this->assertStringContainsString('hidden', $slides[1][2]);
    }

    /* The behaviours that live in the script ------------------------------ */

    private function script(): string
    {
        return file_get_contents(public_path('js/hero.js'));
    }

    private function css(): string
    {
        return file_get_contents(public_path('css/blocks.css'));
    }

    public function test_it_advances_on_an_eight_second_interval(): void
    {
        $this->assertMatchesRegularExpression('/INTERVAL\s*=\s*8000/', $this->script());
    }

    public function test_it_holds_still_on_hover_and_on_focus(): void
    {
        $js = $this->script();

        foreach (['mouseenter', 'mouseleave', 'focusin', 'focusout'] as $event) {
            $this->assertStringContainsString("addEventListener('{$event}'", $js);
        }

        // Focus matters more than hover: a keyboard reader tabbing the
        // controls must not have the content move underneath them.
        $this->assertMatchesRegularExpression('/focused\s*=\s*true;\s*stop\(\)/', $js);
    }

    public function test_the_arrow_keys_move_between_slides(): void
    {
        $js = $this->script();

        $this->assertStringContainsString('ArrowLeft', $js);
        $this->assertStringContainsString('ArrowRight', $js);
    }

    public function test_a_swipe_moves_a_slide_and_a_scroll_does_not(): void
    {
        $js = $this->script();

        $this->assertStringContainsString("addEventListener('touchstart'", $js);
        $this->assertStringContainsString("addEventListener('touchend'", $js);

        // Passive, so the page never waits on us to decide whether to scroll.
        $this->assertMatchesRegularExpression('/touchstart.*\{\s*passive:\s*true\s*\}/s', $js);

        // A gesture that travelled further down than across was a scroll.
        $this->assertMatchesRegularExpression(
            '/Math\.abs\(dx\)\s*<\s*SWIPE\s*\|\|\s*Math\.abs\(dx\)\s*<=\s*Math\.abs\(dy\)/',
            $js,
            'A vertical flick would change slide.',
        );
    }

    public function test_the_change_is_a_crossfade_and_nothing_travels(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression(
            '/\.b-hero__slide\[data-state\]\s*\{\s*transition: opacity 400ms var\(--ease-in\)/',
            $css,
        );

        // A slide-in would imply a direction the content does not have.
        $this->assertDoesNotMatchRegularExpression(
            '/\.b-hero__slide\[data-state[^\]]*\]\s*\{[^}]*transform:/',
            $css,
            'The slides move rather than fade.',
        );

        $this->assertMatchesRegularExpression('/FADE\s*=\s*400/', $this->script());
    }

    public function test_reduced_motion_stops_the_rotation_rather_than_slowing_it(): void
    {
        $js = $this->script();
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertStringContainsString('prefers-reduced-motion: reduce', $js);
        $this->assertMatchesRegularExpression(
            '/if \(prefersReduced\(\) \|\| pausedByUser \|\| hovered \|\| focused\) return;/',
            $js,
            'The timer starts under a reduced-motion preference.',
        );

        $this->assertMatchesRegularExpression(
            '/@media \(prefers-reduced-motion: reduce\) \{[^}]*\.b-hero__slide\[data-state\] \{ transition: none/s',
            $css,
        );
    }

    public function test_a_slide_with_nothing_to_show_drops_rather_than_renders_empty(): void
    {
        // A stripped database is the state this platform ships in before
        // CRASA confirms its figures. Three slides where two are empty
        // boxes is worse than one slide and a banner.
        IndicatorValue::query()->forceDelete();
        Publication::query()->forceDelete();

        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, 'data-hero-slide'), 'A carousel of one is a banner.');
        $this->assertStringNotContainsString('data-hero-dot', $html);
        $this->assertStringNotContainsString('aria-roledescription="carousel"', $html);

        // The first slide is still there. Its copy is the content.
        $this->assertStringContainsString('b-hero__headline', $html);
    }

    public function test_the_controls_survive_a_reduced_motion_preference(): void
    {
        // They used to be removed here. That was right while every slide
        // said the same kind of thing and wrong the moment they carried
        // different content: it made the featured comparison and the latest
        // publication unreachable for anyone whose system asks for less
        // motion. Reduced motion means do not move things by yourself.
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertDoesNotMatchRegularExpression(
            '/@media \(prefers-reduced-motion: reduce\) \{[^}]*\.b-hero__controls \{ display: none/s',
            $css,
            'A reduced-motion reader cannot reach slides two and three.',
        );

        // The pause button goes instead: nothing is running to pause.
        $this->assertMatchesRegularExpression(
            '/@media \(prefers-reduced-motion: reduce\) \{.*?\.b-hero__pause \{ display: none; \}/s',
            $css,
        );
    }

    /**
     * SCREEN 02 DRAWS A STRIP AND IT IS OVERRULED.
     *
     * Line 152 draws the controls as a full-width bar across the bottom of
     * the band, rgba(11,21,34,.86) with a --c-navy-line top border. Ruled
     * out on 9 September 2026: it put a fourth horizontal band in front of
     * the reader before any content began, and it cut the hero off from the
     * figures band the hero belongs with. Logged as a disclosed variation.
     *
     * This test holds the replacement rather than the drawing: no strip, a
     * pill, and every control the screen drew still on the page.
     */
    public function test_the_controls_are_an_overlay_and_not_a_strip(): void
    {
        $css = $this->css();

        preg_match('/\.b-hero__controls \{([^}]*)\}/', $css, $controls);
        $this->assertNotEmpty($controls, '.b-hero__controls has no rule.');

        // The strip's two signatures are gone from the wrapper.
        $this->assertStringNotContainsString('rgba(11, 21, 34, .86)', $controls[1]);
        $this->assertStringNotContainsString('border-block-start', $controls[1]);
        $this->assertStringNotContainsString('justify-self: stretch', $controls[1]);

        // Bottom left, on the content column, --s-6 above the band's edge.
        $this->assertStringContainsString('align-self: end', $controls[1]);
        $this->assertStringContainsString('max-width: var(--w-page)', $controls[1]);
        $this->assertStringContainsString('padding-inline: var(--page-x)', $controls[1]);
        $this->assertStringContainsString('margin: 0 0 var(--s-6)', $controls[1]);

        // The wrapper spans the column, so it must not swallow clicks meant
        // for the slide behind it.
        $this->assertStringContainsString('pointer-events: none', $controls[1]);
    }

    /** The pill, at the values the ruling specified. */
    public function test_the_pill_carries_the_group(): void
    {
        preg_match('/\.b-hero__pill \{([^}]*)\}/', $this->css(), $pill);

        $this->assertNotEmpty($pill, '.b-hero__pill has no rule.');
        $this->assertStringContainsString('background: rgba(11, 21, 34, .55)', $pill[1]);
        $this->assertStringContainsString('border-radius: var(--r-pill)', $pill[1]);
        $this->assertStringContainsString('padding: 6px 10px', $pill[1]);
        $this->assertStringContainsString('pointer-events: auto', $pill[1]);
    }

    /**
     * The cream controls against the pill, measured rather than assumed.
     *
     * The group sits at the content column's left edge, where the hero
     * scrim is rgba(15,27,45,.94). The worst ground the controls can ever
     * sit on is that scrim over a pure white photograph, with the pill over
     * it. Everything below is computed from that stack.
     */
    public function test_the_controls_clear_their_thresholds_on_the_pill(): void
    {
        $over = fn (array $fg, float $alpha, array $bg) => [
            $alpha * $fg[0] + (1 - $alpha) * $bg[0],
            $alpha * $fg[1] + (1 - $alpha) * $bg[1],
            $alpha * $fg[2] + (1 - $alpha) * $bg[2],
        ];

        $luminance = function (array $rgb): float {
            $channel = function (float $c): float {
                $c /= 255;

                return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            };

            return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);
        };

        $ratio = function (array $a, array $b) use ($luminance): float {
            $la = $luminance($a);
            $lb = $luminance($b);

            return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
        };

        // Brightest possible photograph, then the scrim, then the pill.
        $scrim = $over([15, 27, 45], 0.94, [255, 255, 255]);
        $ground = $over([11, 21, 34], 0.55, $scrim);

        $cream = [248, 246, 241];
        $navySoft = [107, 118, 145];   // --c-navy-soft, the control border

        // SC 1.4.3: text and the dots' fill.
        $this->assertGreaterThanOrEqual(
            4.5,
            $ratio($cream, $ground),
            'Cream on the pill measures '.round($ratio($cream, $ground), 2).':1'
        );

        // SC 1.4.11: the 1.5px boundary of the arrow and pause buttons.
        $this->assertGreaterThanOrEqual(
            3.0,
            $ratio($navySoft, $ground),
            'The control border on the pill measures '.round($ratio($navySoft, $ground), 2).':1'
        );

        // And the CSS actually uses the value that was measured.
        preg_match('/\.b-hero__ctl \{([^}]*)\}/', $this->css(), $ctl);
        $this->assertStringContainsString('border: 1.5px solid var(--c-navy-soft)', $ctl[1]);
    }

    /** 32x32 arrows and pause, 10px dots 12px apart. */
    public function test_the_group_is_drawn_at_the_sizes_the_ruling_gave(): void
    {
        $css = $this->css();

        preg_match('/\.b-hero__ctl \{([^}]*)\}/', $css, $ctl);
        $this->assertStringContainsString('width: 32px; height: 32px', $ctl[1]);
        $this->assertStringContainsString('border-radius: var(--r-sm)', $ctl[1]);

        preg_match('/\.b-hero__dots \{([^}]*)\}/', $css, $dots);
        $this->assertStringContainsString('gap: 12px', $dots[1]);

        preg_match('/\.b-hero__dot \{([^}]*)\}/', $css, $dot);
        $this->assertStringContainsString('width: 10px; height: 10px', $dot[1]);
        $this->assertStringContainsString('border: 1.5px solid var(--c-cream)', $dot[1]);

        // Current filled cream, the others outlined.
        $this->assertMatchesRegularExpression(
            '/\.b-hero__dot\[aria-selected="true"\] \{ background: var\(--c-cream\); \}/',
            $css
        );

        // The 22px hit area, the largest that fits without two targets
        // overlapping at a 12px gap.
        $this->assertMatchesRegularExpression('/\.b-hero__dot::after \{[^}]*inset: -6px/', $css);
    }

    /**
     * No counter, and no visible numbers on the dots.
     *
     * Screen 02 closes its bar with a counter and numbers each dot. Both go
     * with the bar: three dots already say which of three slides is
     * current, and a figure beside them says it twice. The position a
     * reader cannot see is in each dot's accessible name.
     */
    public function test_the_dots_carry_the_position_without_numbers(): void
    {
        $html = $this->home();

        $this->assertStringNotContainsString('data-hero-counter', $html);
        $this->assertStringNotContainsString('b-hero__counter', $html);
        $this->assertStringNotContainsString('data-hero-state', $html);
        $this->assertStringNotContainsString('b-hero__state', $html);

        foreach ([1, 2, 3] as $n) {
            // Empty button, and a name that says where it lands.
            $this->assertMatchesRegularExpression(
                '/data-hero-dot="'.($n - 1).'"[^>]*aria-label="Slide '.$n.' of 3: [^"]+"\s*><\/button>/s',
                $html,
                "Dot {$n} is numbered on screen or has no accessible name.",
            );
        }
    }

    /**
     * The copy column clears the group rather than hoping to stop short of
     * it. 44px is the pill's height, --s-6 its offset from the bottom edge,
     * --s-4 the gap the call to action keeps above it.
     */
    public function test_the_copy_column_clears_the_control_group(): void
    {
        $css = $this->css();

        $this->assertStringContainsString(
            '.b-hero[data-hero] .b-hero__inner { padding-bottom: calc(var(--s-8) + 44px + var(--s-4)); }',
            $css
        );

        // And at 375, where the band is shorter and the collision is most
        // likely.
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 767\.98px\) \{\s*\.b-hero\[data-hero\] \.b-hero__inner \{ padding-bottom: calc\(var\(--s-7\) \+ 44px \+ var\(--s-4\)\); \}/s',
            $css
        );
    }

    /** The pause icon follows aria-pressed in CSS, so they cannot drift. */
    public function test_the_pause_button_is_an_icon_that_follows_its_state(): void
    {
        $html = $this->home();
        $css = $this->css();

        $this->assertStringContainsString('b-hero__icon--pause', $html);
        $this->assertStringContainsString('b-hero__icon--play', $html);
        $this->assertStringNotContainsString('data-hero-pause-label', $html);

        $this->assertStringContainsString('.b-hero__pause .b-hero__icon--play { display: none; }', $css);
        $this->assertStringContainsString('.b-hero__pause[aria-pressed="true"] .b-hero__icon--pause { display: none; }', $css);
    }
    public function test_the_pause_button_says_what_it_pauses(): void
    {
        // With the state note gone, "Pause" alone would tell a screen
        // reader nothing about what stops. Both names move together when
        // the button is pressed, and each accessible name contains its
        // visible label, which SC 2.5.3 requires.
        $html = $this->home();

        $this->assertMatchesRegularExpression(
            '/data-hero-pause[^>]*aria-label="Pause rotation"/s',
            $html,
        );
        $this->assertStringContainsString('data-aria-play="Resume rotation"', $html);

        $js = $this->script();
        $this->assertStringContainsString("setAttribute('aria-label', spoken)", $js);
    }

    public function test_every_locale_keeps_its_visible_label_inside_its_accessible_name(): void
    {
        // SC 2.5.3, Label in Name. A speech-input user says what they see.
        // The first attempt at this failed in French and Portuguese:
        // "Interrompre la rotation" does not contain "Pause".
        foreach (Locales::all() as $locale => $ignored) {
            $strings = require base_path("lang/{$locale}/blocks.php");

            foreach ([['hero_pause', 'hero_pause_action'], ['hero_play', 'hero_play_action']] as [$visible, $spoken]) {
                $this->assertNotFalse(
                    mb_stripos($strings[$spoken], $strings[$visible]),
                    "{$locale}: the accessible name \"{$strings[$spoken]}\" does not contain "
                    ."the visible label \"{$strings[$visible]}\".",
                );
            }
        }
    }
}
