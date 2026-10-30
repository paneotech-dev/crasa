<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Translation;
use Database\Seeders\LegalPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Item 1h: the privacy notice in three languages, with the retention the
 * code defines, the services that receive data and the cookie choice; a
 * draft for CRASA's legal review in the back office.
 */
class PrivacyNoticeTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = '2026_10_30_080000_rewrite_privacy_notice_for_cookie_choice.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalPagesSeeder::class);
    }

    public function test_the_notice_reads_in_three_languages(): void
    {
        $expect = [
            'en' => ['Who is responsible', 'crasa@crasa.org', 'Gaborone', 'Database backups are kept for 14 days', 'recycle bin for 30 days', 'to be confirmed by CRASA', 'Brevo', 'Cloudflare', 'Cookie settings', 'Your rights', 'member area'],
            'fr' => ['Responsable', 'crasa@crasa.org', 'conservées 14 jours', '30 jours dans la corbeille', 'à confirmer par la CRASA', 'Brevo', 'Cloudflare', 'Paramètres des cookies', 'Vos droits', 'espace membres'],
            'pt' => ['Responsável', 'crasa@crasa.org', 'guardadas 14 dias', '30 dias na reciclagem', 'a confirmar pela CRASA', 'Brevo', 'Cloudflare', 'Definições de cookies', 'Os seus direitos', 'área de membros'],
        ];

        foreach ($expect as $locale => $phrases) {
            $html = $this->get('/'.$locale.'/privacy')->assertOk()->getContent();
            foreach ($phrases as $phrase) {
                $this->assertStringContainsString(e($phrase), $html, $locale.': '.$phrase);
            }
            // The old wording for the member area is gone from the text, and
            // drafting notes stay out of the reader's page.
            preg_match('~<div class="cp-legal__body">.*?</section>\s*</div>~s', $html, $body);
            $this->assertStringNotContainsString('extranet', $body[0], $locale);
            $this->assertStringNotContainsString('[CRASA', $html, $locale);
            $this->assertStringNotContainsString('[La CRASA', $html, $locale);
            $this->assertStringNotContainsString('[A CRASA', $html, $locale);
        }
    }

    public function test_the_notice_is_a_draft_for_legal_review_in_the_back_office(): void
    {
        $page = Page::where('slug', 'privacy')->sole();

        $this->assertTrue($page->awaitsClientReview());
        $items = implode(' ', $page->outstandingClientItems());
        foreach (['retention periods', 'legal basis', 'hosting provider', 'data protection officer', 'supervisory authority'] as $blank) {
            $this->assertStringContainsStringIgnoringCase($blank, $items);
        }
    }

    public function test_the_banner_and_the_footer_link_to_the_notice(): void
    {
        $html = $this->get('/en/privacy')->getContent();

        preg_match('~<section class="cc".*?</section>~s', $html, $banner);
        $this->assertStringContainsString('href="'.url('en/privacy').'">Read the privacy notice</a>', $banner[0]);
        preg_match('~<div class="k-fbar">.*?</div>~s', $html, $bar);
        $this->assertStringContainsString('href="'.url('en/privacy').'">'.e(__('footer.privacy')).'</a>', $bar[0]);
    }

    /**
     * The migration replaces a language only while it still holds the text
     * seeded in September, leaves an edited one alone, runs twice without
     * effect, and rolls back.
     */
    public function test_the_data_migration_replaces_only_the_seeded_text(): void
    {
        $old = require database_path('seeders/data/privacy-notice-2026-09.php');
        $new = require database_path('seeders/data/privacy-notice.php');
        $block = PageBlock::where('page_id', Page::where('slug', 'privacy')->value('id'))->sole();

        // As stored on live: typed through a form, with CRLF line endings.
        $block->setTranslations('en', ['body' => str_replace("\n", "\r\n", $old['en'])]);
        $block->setTranslations('fr', ['body' => $old['fr']]);
        $block->setTranslations('pt', ['body' => 'Texto editado pelo Secretariado.']);

        $migration = require database_path('migrations/'.self::MIGRATION);
        $migration->up();
        $migration->up();

        $body = fn (string $locale) => Translation::where('translatable_type', PageBlock::class)
            ->where('translatable_id', $block->id)->where('field', 'body')->where('locale', $locale)->value('value');

        $this->assertSame($new['en'], $body('en'));
        $this->assertSame($new['fr'], $body('fr'));
        $this->assertSame('Texto editado pelo Secretariado.', $body('pt'));

        $migration->down();
        $this->assertSame($old['en'], $body('en'));
        $this->assertSame('Texto editado pelo Secretariado.', $body('pt'));
    }
}
