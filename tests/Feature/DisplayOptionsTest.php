<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * The display options in the header: text size, high contrast, underlined
 * links. The behaviour runs in the browser; these hold the markup that
 * makes it operable by keyboard and announced to a screen reader, the
 * head snippet that applies the stored choice before first paint, and the
 * three languages. The signed-in shell (back office and member area)
 * carries the same control and the same head snippet, so a choice made on
 * one surface holds on the other.
 */
class DisplayOptionsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedComposedPages();
    }

    public function test_every_public_page_carries_the_disclosure_button_and_its_panel(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*aria-expanded="false"[^>]*aria-controls="a11y-panel"[^>]*data-a11y-toggle/s', $html);
        $this->assertMatchesRegularExpression('/id="a11y-panel" role="group" aria-labelledby="a11y-title" hidden/', $html);
        $this->assertSame(1, substr_count($html, 'data-a11y-toggle'));
    }

    public function test_sizes_and_toggles_are_pressed_buttons_with_a_live_region(): void
    {
        $html = $this->get('/en')->getContent();

        foreach (['smaller', 'normal', 'larger', 'largest'] as $size) {
            $this->assertMatchesRegularExpression('/aria-pressed="(true|false)"\s+data-a11y-size="'.$size.'"/', $html);
        }
        foreach (['spacing', 'dyslexia', 'contrast', 'underline', 'pause'] as $flag) {
            $this->assertStringContainsString('aria-pressed="false" data-a11y-flag="'.$flag.'"', $html);
            // Each toggle names itself and is described by its one-line hint.
            $this->assertStringContainsString('aria-describedby="a11y-hint-'.$flag.'"', $html);
            $this->assertStringContainsString('id="a11y-hint-'.$flag.'"', $html);
        }
        $this->assertStringContainsString('role="status" aria-live="polite" data-a11y-status', $html);
        $this->assertStringContainsString('role="group" aria-labelledby="a11y-size-label"', $html);
    }

    public function test_the_stored_choice_is_applied_in_the_head_and_the_files_are_linked(): void
    {
        $html = $this->get('/en')->getContent();
        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringContainsString("localStorage.getItem('crasa-a11y')", $head);
        $this->assertStringContainsString('try{', $head);
        $this->assertStringContainsString('css/a11y.css', $head);
        $this->assertMatchesRegularExpression('#<script src="[^"]*js/a11y\.js[^"]*" defer></script>#', $html);
    }

    public function test_the_newer_choices_are_applied_while_the_header_is_parsed(): void
    {
        $html = $this->get('/en')->getContent();
        $toolbar = substr($html, strpos($html, '<div class="c-a11y"') - 400, 400);

        $this->assertStringContainsString("['spacing','dyslexia','contrast','underline','pause']", $toolbar);
        $this->assertStringContainsString('try{', $toolbar);
    }

    public function test_the_button_has_a_visible_name_and_the_pause_event_is_sent(): void
    {
        $html = $this->get('/en')->getContent();
        $this->assertStringContainsString('<span class="c-a11y__text">Display options</span>', $html);

        $js = file_get_contents(public_path('js/a11y.js'));
        $this->assertStringContainsString("'crasa:a11y-pause'", $js);
        $this->assertStringContainsString("['spacing', 'dyslexia', 'contrast', 'underline', 'pause']", $js);

        // The phone row is keyed on the control, not on header classes.
        $css = file_get_contents(public_path('css/a11y.css'));
        // Only the fallback for the 28 October header names header classes.
        $this->assertSame(1, substr_count($css, '.c-header--layered .c-header__bar:has(.c-a11y) { display: block; }'));
        $this->assertStringContainsString('html.a11y-pause', $css);
        $this->assertStringContainsString('html.a11y-dyslexia', $css);
        $this->assertStringContainsString('html.a11y-spacing', $css);
        // High contrast reaches the UI system tokens as well.
        $this->assertStringContainsString('--u-muted:#000000', $css);
        $this->assertStringContainsString('--u-tint-green:#FFFFFF', $css);
    }

    public function test_the_skip_link_leads_to_the_main_region(): void
    {
        $html = $this->get('/en')->getContent();

        $this->assertStringContainsString('<a class="c-skip" href="#content">', $html);
        $this->assertStringContainsString('<main id="content" tabindex="-1">', $html);
    }

    public function test_the_panel_speaks_the_page_language(): void
    {
        $this->get('/fr')->assertSee('Taille du texte')->assertSee('Contraste élevé');
        $this->get('/pt')->assertSee('Tamanho do texto')->assertSee('Alto contraste');
        $this->get('/fr')->assertSee('Espacement de lecture')->assertSee('Arrêter les animations');
        $this->get('/pt')->assertSee('Espaçamento de leitura')->assertSee('Parar as animações');
    }

    public function test_the_three_language_files_carry_the_same_keys(): void
    {
        $en = array_keys(require lang_path('en/a11y.php'));

        foreach (['fr', 'pt'] as $locale) {
            $this->assertSame($en, array_keys(require lang_path($locale.'/a11y.php')), $locale);
        }
    }

    public function test_storage_access_in_the_script_is_guarded(): void
    {
        $js = file_get_contents(public_path('js/a11y.js'));

        $this->assertSame(substr_count($js, 'localStorage.'), substr_count($js, 'try {'));
    }

    /** @return array<string, array{string}> */
    public static function shellSurfaces(): array
    {
        return [
            'back office' => ['admin'],
            'member area' => ['member'],
        ];
    }

    private function shellPage(string $surface, string $locale = 'en'): string
    {
        $this->seed(CommitteeSeeder::class);

        if ($surface === 'admin') {
            return $this->actingAs(User::factory()->administrator()->create())
                ->get('/'.$locale.'/admin')->assertOk()->getContent();
        }

        $rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();

        return $this->actingAs($rep)->get('/'.$locale.'/extranet/dashboard')->assertOk()->getContent();
    }

    private function headScript(string $html): string
    {
        $head = substr($html, 0, strpos($html, '</head>'));
        $this->assertSame(1, preg_match("#<script>try\\{var a=JSON\\.parse\\(localStorage\\.getItem\\('crasa-a11y'\\).*?</script>#", $head, $match));

        return $match[0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shellSurfaces')]
    public function test_the_signed_in_shell_carries_the_floating_control_with_labelled_buttons(string $surface): void
    {
        $html = $this->shellPage($surface);

        $this->assertStringContainsString('<div class="c-float" role="group" aria-label="Page controls" data-float>', $html);
        $this->assertStringContainsString('aria-label="Back to top"', $html);
        $this->assertSame(1, substr_count($html, 'data-a11y-toggle'));
        $this->assertMatchesRegularExpression('/<button[^>]*aria-expanded="false"[^>]*aria-controls="a11y-panel"[^>]*data-a11y-toggle/s', $html);
        $this->assertStringContainsString('<span class="c-a11y__text">Display options</span>', $html);
        $this->assertMatchesRegularExpression('/id="a11y-panel" role="group" aria-labelledby="a11y-title" hidden/', $html);
        $this->assertStringContainsString('aria-label="Close display options"', $html);

        foreach (['smaller' => 'Smaller', 'normal' => 'Normal', 'larger' => 'Larger', 'largest' => 'Largest'] as $size => $label) {
            $this->assertMatchesRegularExpression('/aria-pressed="(true|false)"\s+data-a11y-size="'.$size.'">'.$label.'<\/button>/', $html);
        }
        foreach (['contrast' => 'High contrast', 'pause' => 'Pause animations'] as $flag => $label) {
            $this->assertStringContainsString('aria-pressed="false" data-a11y-flag="'.$flag.'"', $html);
            $this->assertStringContainsString('aria-describedby="a11y-hint-'.$flag.'"', $html);
            $this->assertStringContainsString('<span class="c-a11y__switch-name">'.$label.'</span>', $html);
        }
        $this->assertStringContainsString('role="status" aria-live="polite" data-a11y-status', $html);

        // The control comes after the top bar and before the content.
        $this->assertGreaterThan(strpos($html, 'class="a-top'), strpos($html, 'class="c-float"'));
        $this->assertLessThan(strpos($html, '<main id="content"'), strpos($html, 'class="c-float"'));

        $this->assertMatchesRegularExpression('#<script src="[^"]*js/a11y\.js[^"]*" defer></script>#', $html);
        $this->assertMatchesRegularExpression('#<script src="[^"]*js/float\.js[^"]*" defer></script>#', $html);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('shellSurfaces')]
    public function test_the_shell_head_applies_the_public_sites_stored_choice_before_paint(string $surface): void
    {
        $shell = $this->shellPage($surface);
        $head = substr($shell, 0, strpos($shell, '</head>'));

        // The same snippet, so the same key: a choice carries both ways.
        $this->assertSame($this->headScript($this->get('/en')->getContent()), $this->headScript($shell));
        $this->assertLessThan(strpos($head, '<link rel="stylesheet"'), strpos($head, "localStorage.getItem('crasa-a11y')"));

        // The public sheet first, then the shell's additions.
        $this->assertMatchesRegularExpression('#css/a11y\.css.*css/a11y-shell\.css#s', $head);
        $this->assertGreaterThan(strpos($head, 'css/admin.css'), strpos($head, 'css/a11y.css'));
    }

    public function test_the_shell_panel_speaks_the_page_language(): void
    {
        $this->assertStringContainsString('Taille du texte', $this->shellPage('admin', 'fr'));
        $this->assertStringContainsString('Tamanho do texto', $this->shellPage('member', 'pt'));
    }

    public function test_the_shell_sheet_reaches_the_sidebar_the_content_and_the_admin_tokens(): void
    {
        $css = file_get_contents(public_path('css/a11y-shell.css'));

        // Text size zooms the content and the sidebar's parts.
        $this->assertStringContainsString('body[data-surface="admin"] .a-main {'."\n".'  zoom: var(--a11y-zoom);', $css);
        $this->assertStringContainsString('body[data-surface="admin"] .a-side > * {'."\n".'  zoom: var(--a11y-zoom);', $css);
        // High contrast re-points the admin set, over the dark set as well.
        $this->assertStringContainsString('html.a11y-contrast body[data-surface="admin"] {', $css);
        foreach (['--a-ink: #000000', '--a-surface: #FFFFFF', '--a-btn: #006B3B', '--a-side-bg: #FFFFFF', '--a-rule: #000000'] as $token) {
            $this->assertStringContainsString($token, $css);
        }
        // On a zoomed phone the list filters' search shrinks to its card.
        $this->assertStringContainsString('body[data-surface="admin"] :is(.a-search, .a-search input, .a-search__field) {'."\n".'  min-width: 0;', $css);
        // The dark set asks for dark native controls; high contrast is light.
        $this->assertMatchesRegularExpression('/html\\.a11y-contrast body\\[data-surface="admin"\\] \\{[^}]*color-scheme: light;/', $css);
        // Clear of the drawer, and of the consent banner through --cc-h.
        $this->assertStringContainsString('html.has-drawer-open .c-float { visibility: hidden; }', $css);
        $this->assertStringContainsString('var(--cc-h, 0px)', file_get_contents(public_path('css/a11y.css')));
    }
}
