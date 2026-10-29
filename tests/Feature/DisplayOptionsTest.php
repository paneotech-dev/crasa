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
        $this->assertStringContainsString('aria-pressed="false" data-a11y-flag="contrast"', $html);
        $this->assertStringContainsString('aria-pressed="false" data-a11y-flag="underline"', $html);
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
