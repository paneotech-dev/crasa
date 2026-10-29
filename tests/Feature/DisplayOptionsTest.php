<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * The display options in the header: text size, high contrast, underlined
 * links. The behaviour runs in the browser; these hold the markup that
 * makes it operable by keyboard and announced to a screen reader, the
 * head snippet that applies the stored choice before first paint, and the
 * three languages.
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
        $this->assertStringNotContainsString('.c-header', $css);
        $this->assertStringContainsString('html.a11y-pause', $css);
        $this->assertStringContainsString('html.a11y-dyslexia', $css);
        $this->assertStringContainsString('html.a11y-spacing', $css);
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
}
