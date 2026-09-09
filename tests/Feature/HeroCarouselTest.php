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

        // The boardroom photograph, the comparison table and the latest
        // publication. Three of the same thing would be a carousel that
        // exists to move.
        //
        // Slide one carried the figures panel until batch two supplied a
        // photograph on 9 September 2026. Screen 02 draws two kinds of
        // slide and a photo slide runs its copy the full width, so the
        // panel went with the change. The other two keep theirs.
        $this->assertStringContainsString('hero-delegates-boardroom-01', $html);
        $this->assertStringContainsString('b-hero__table', $html);
        $this->assertStringContainsString('b-hero__latest-title', $html);
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

    public function test_the_controls_are_a_bar_across_the_band(): void
    {
        // "margin-top:auto;background:rgba(11,21,34,.86);border-top:1px
        //  solid #2C3A55" -- screen 02 line 152. It was a floating cluster
        // with no ground, sitting on the slide rather than under it.
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression(
            '/\.b-hero__controls \{[^}]*background: rgba\(11, 21, 34, \.86\)/',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.b-hero__controls \{[^}]*border-block-start: 1px solid var\(--c-navy-line\)/',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.b-hero__controls \{[^}]*justify-self: stretch/',
            $css,
        );
    }

    public function test_the_bar_carries_a_counter_but_no_state_note(): void
    {
        // Screen 02 closes the bar with a counter and then a note on what
        // the carousel is doing. The counter stays: the dots say which
        // slide is current by shape, the counter says it in figures.
        //
        // The state note is removed on a ruling of 9 September 2026 and is
        // a disclosed variation. It narrated the control's behaviour back
        // to a reader who could see the control.
        $html = $this->home();

        $this->assertMatchesRegularExpression('/data-hero-counter[^>]*>1 \/ 3</', $html);
        $this->assertStringNotContainsString('data-hero-state', $html);
        $this->assertStringNotContainsString('b-hero__state', $html);

        // And the dots are numbered, as the screen loops "{{ d.num }}".
        foreach ([1, 2, 3] as $n) {
            $this->assertMatchesRegularExpression(
                '/class="b-hero__dot"[^>]*data-hero-dot="'.($n - 1).'".*?>'.$n.'<\/button>/s',
                $html,
                "Dot {$n} is not numbered.",
            );
        }
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
