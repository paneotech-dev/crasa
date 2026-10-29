<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use App\Models\Translation;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * French and Portuguese pages tell the reader the text was translated by
 * machine and is under review, once, in their own language, without ever
 * covering content. An article whose text is still a machine draft says so
 * in one line.
 */
class TranslationNoticeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SectorSeeder::class);
        $this->seedComposedPages();
    }

    public function test_french_and_portuguese_pages_carry_the_notice_in_their_language(): void
    {
        $this->get('/fr')->assertOk()
            ->assertSee('Cette page a été traduite automatiquement')
            ->assertSee('data-translation-notice', false);

        $this->get('/pt')->assertOk()
            ->assertSee('Esta página foi traduzida automaticamente');
    }

    public function test_english_pages_carry_no_notice(): void
    {
        $this->get('/en')->assertOk()->assertDontSee('data-translation-notice', false);
    }

    public function test_the_notice_is_an_open_details_element_inside_main_and_not_a_dialog(): void
    {
        $html = $this->get('/fr')->getContent();
        $main = substr($html, strpos($html, '<main id="content"'));

        $this->assertMatchesRegularExpression('/<details class="c-translation__box" open>\s*<summary class="c-translation__close">/', $main);
        $this->assertStringContainsString('Fermer cet avis', $main);
        $this->assertStringNotContainsString('role="dialog"', substr($main, 0, strpos($main, '</details>')));
    }

    public function test_a_closed_notice_is_hidden_before_first_paint(): void
    {
        $html = $this->get('/fr')->getContent();
        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringContainsString("localStorage.getItem('crasa-translation-notice')", $head);
        $this->assertStringContainsString('translation-notice-closed', $head);
    }

    public function test_an_article_in_machine_draft_says_so_in_one_line(): void
    {
        $item = NewsItem::factory()->create(['slug' => 'draft-translation']);
        $item->setTranslations('en', ['title' => 'Workshop', 'body' => '<p>Text</p>']);
        $item->setTranslations('fr', ['title' => 'Atelier', 'body' => '<p>Texte</p>']);
        Translation::where('translatable_id', $item->id)->where('locale', 'fr')->update(['is_machine_draft' => true]);

        $this->get('/fr/newsroom/draft-translation')->assertOk()
            ->assertSee('Traduction automatique, en cours de relecture par la CRASA.');

        // The English is the original, so it carries no note.
        $this->get('/en/newsroom/draft-translation')->assertOk()
            ->assertDontSee('c-machine-note', false);
    }

    public function test_a_reviewed_translation_carries_no_note(): void
    {
        $item = NewsItem::factory()->create(['slug' => 'reviewed']);
        $item->setTranslations('en', ['title' => 'Workshop', 'body' => '<p>Text</p>']);
        $item->setTranslations('pt', ['title' => 'Oficina', 'body' => '<p>Texto</p>']);

        $this->get('/pt/newsroom/reviewed')->assertOk()->assertDontSee('c-machine-note', false);
    }

    public function test_a_machine_draft_in_another_language_does_not_mark_the_fallback(): void
    {
        $item = NewsItem::factory()->create(['slug' => 'fallback']);
        $item->setTranslations('en', ['title' => 'Workshop', 'body' => '<p>Text</p>']);
        $item->setTranslations('fr', ['title' => 'Atelier', 'body' => '<p>Texte</p>']);
        Translation::where('translatable_id', $item->id)->where('locale', 'fr')->update(['is_machine_draft' => true]);

        // Portuguese is missing, so the reader sees the English original.
        $this->assertFalse($item->fresh()->showsMachineDraft(['title', 'body'], 'pt'));
        $this->assertTrue($item->fresh()->showsMachineDraft(['title', 'body'], 'fr'));
    }
}
