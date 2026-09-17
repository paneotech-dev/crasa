<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The back office reads in both themes.
 *
 * The rebuild audit of 16 September 2026 rendered every admin and extranet page
 * in the light and dark themes and measured every text node against its
 * composited background. It found one class of fault in many places: a fixed
 * palette value on a surface that changes with the theme, or a theme value on a
 * surface that does not. Headings were navy on navy (1.08:1), the primary
 * button was green on green in BOTH themes (1.00:1) because the generic link
 * rule outranked it, and the current page in the pager was white on white.
 *
 * ContrastTest measures the token pairs. This holds the rules that decide
 * which pair a surface gets, so the class of fault cannot return by a rule
 * being edited back.
 */
class AdminThemeContrastTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();
        $this->css = File::get(public_path('css/admin.css'));
    }

    public function test_headings_take_the_theme_ink_at_every_level(): void
    {
        $this->assertStringContainsString(
            ':where(body[data-surface="admin"]) :is(h1, h2, h3, h4) { color: var(--a-ink-strong); }',
            $this->css,
            'Admin h1 to h4 must take --a-ink-strong at element specificity; base.css leaves them navy, which is 1.08:1 on the dark ground, and a stronger rule turns the sidebar group labels navy on navy.'
        );

        foreach (['h1', 'h2', 'h3'] as $level) {
            $this->assertStringContainsString('body[data-surface="admin"] '.$level.',', $this->css);
        }
    }

    public function test_the_generic_link_colour_cannot_outrank_a_component(): void
    {
        $this->assertStringContainsString(':where(body[data-surface="admin"]) a {', $this->css);
        $this->assertDoesNotMatchRegularExpression(
            '/^body\[data-surface="admin"\] a\s*\{[^}]*color/m',
            $this->css,
            'At (0,1,2) the link rule beats every single-class component rule, and an anchor styled as a button turns green on green.'
        );
    }

    public function test_no_generic_anchor_rule_outranks_a_component(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/^body\[data-surface="admin"\] a:(hover|focus)/m',
            $this->css,
            'A hover colour at (0,2,2) turned the primary button\'s label navy on navy.'
        );
    }

    public function test_status_inks_never_style_the_page_inline(): void
    {
        $inline = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            if (preg_match('/style="color:\s*var\(--c-/', $view->getContents())) {
                $inline[] = $view->getRelativePathname();
            }
        }

        $this->assertSame([], $inline, 'An inline palette colour cannot follow the theme.');
    }

    public function test_the_primary_button_is_drawn_from_the_admin_button_tokens(): void
    {
        $this->assertMatchesRegularExpression('/\.a-btn--primary\s*\{[^}]*color:\s*var\(--a-btn-ink\)[^}]*background:\s*var\(--a-btn\)/s', $this->css);
        $this->assertMatchesRegularExpression('/\.a-btn--primary:hover\s*\{[^}]*background:\s*var\(--a-btn-hover\)[^}]*color:\s*var\(--a-btn-hover-ink\)/s', $this->css);

        // Defined for the light theme, the dark setting and the dark system
        // preference: three places, or one theme silently inherits another.
        foreach (['--a-btn:', '--a-btn-ink:', '--a-btn-hover:', '--a-btn-hover-ink:'] as $token) {
            $this->assertSame(3, substr_count($this->css, $token), "{$token} must be defined once per theme block.");
        }

        $this->assertStringContainsString('--a-btn-ink: var(--d-bg);', $this->css, 'White on --d-green is 3.05:1; the dark button carries dark ink.');
    }

    public function test_no_inverse_fill_carries_fixed_white_text(): void
    {
        preg_match_all('/\{[^}]*\}/s', $this->css, $blocks);

        $offending = array_filter($blocks[0], fn (string $block) => str_contains($block, 'background: var(--a-ink-strong)')
            && str_contains($block, 'color: var(--c-white)'));

        $this->assertSame([], array_values($offending), '--a-ink-strong is white in the dark theme, so white text on it vanishes.');
    }

    public function test_no_hover_paints_cream_under_theme_ink(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/:hover\s*\{[^}]*background:\s*var\(--c-cream\)/s',
            $this->css,
            'Cream under the dark theme\'s light ink measures 1.1:1. Use --a-head-ground, which is cream only in the light theme.'
        );
    }

    public function test_the_top_bar_follows_the_theme(): void
    {
        $this->assertMatchesRegularExpression('/\.a-top__field\s*\{[^}]*background:\s*var\(--a-surface\)/s', $this->css);
        $this->assertMatchesRegularExpression('/\.a-top__field\s*\{[^}]*border:\s*1\.5px solid var\(--c-border\)/s', $this->css);
    }

    public function test_every_back_office_button_names_its_variant(): void
    {
        $bare = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            if (preg_match('/class="a-btn"/', $view->getContents())) {
                $bare[] = $view->getRelativePathname();
            }
        }

        $this->assertSame([], $bare, 'A bare .a-btn takes the browser\'s default grey fill, which is 1.04:1 on the admin ground.');
    }

    /**
     * Found by the rendered audit once the media library held imported
     * photographs without alt text: red on the dark card measured 2.11:1,
     * the dark-red hint 1.45:1, and light theme ink in the red-tinted
     * field 1.03:1.
     */
    public function test_the_missing_alt_states_follow_the_dark_theme(): void
    {
        foreach (['body[data-surface="admin"][data-theme="dark"]', '  body[data-surface="admin"][data-theme="system"]'] as $scope) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($scope, '/').' :where\([^)]*\.a-asset__warning, \.a-asset__usage\[data-unused\]\) \{ color: var\(--c-red-mid\); \}/',
                $this->css
            );
            $this->assertStringContainsString($scope.' .a-field[data-state="missing"] .a-hint { color: var(--c-red-mid); }', $this->css);
        }

        $this->assertMatchesRegularExpression('/\.a-field\[data-state="missing"\] \.a-input \{[^}]*background: var\(--c-red-light\);[^}]*color: var\(--c-charcoal\)/', $this->css);
        $this->assertStringContainsString('.a-notice a { color: var(--c-green-dark); }', $this->css);
    }
}
