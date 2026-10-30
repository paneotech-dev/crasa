<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Page;
use App\Support\StructuredText;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HeaderMenuSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PartnerPageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Partner with us" (30 October 2026): a page record with blocks under
 * About, in the About panel, the phone menu and the footer's About column,
 * in three languages.
 */
class PartnerPageTest extends TestCase
{
    use RefreshDatabase;

    private const H1 = [
        'en' => 'Partner with CRASA',
        'fr' => 'Devenir partenaire de la CRASA',
        'pt' => 'Seja parceiro da CRASA',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(HeaderMenuSeeder::class);
        $this->seed(PartnerPageSeeder::class);
    }

    public static function locales(): array
    {
        return ['en' => ['en'], 'fr' => ['fr'], 'pt' => ['pt']];
    }

    #[DataProvider('locales')]
    public function test_the_page_answers_with_one_h1_in_each_language(string $locale): void
    {
        $html = $this->get('/'.$locale.'/partner-with-us')->assertOk()->getContent();

        preg_match_all('#<h1\b[^>]*>(.*?)</h1>#s', $html, $h1);
        $this->assertSame([self::H1[$locale]], array_map('trim', $h1[1]));

        // The sections and the call to action to the contact page.
        foreach (['why-partner', 'ways-to-partner', 'how-it-works'] as $anchor) {
            $this->assertStringContainsString('id="'.$anchor.'"', $html);
        }
        $this->assertMatchesRegularExpression('#<section class="u-cta cp-cta".*?class="u-btn u-btn--primary" href="[^"]*/'.$locale.'/contact"#s', $html);
        $this->assertSame(3, substr_count($html, 'class="cp-partner-step"'));
        $this->assertSame(9, substr_count($html, '<li class="u-card">'));
        // No breadcrumb row (removed 30 October 2026, client request).
        $this->assertStringNotContainsString('k-crumb', $html);
    }

    #[DataProvider('locales')]
    public function test_the_about_panel_the_phone_menu_and_the_footer_link_to_it(string $locale): void
    {
        $html = $this->get('/'.$locale.'/about')->assertOk()->getContent();
        $url = '/'.$locale.'/partner-with-us';
        $label = __('d3_partner.menu', [], $locale);

        // At the end of the About panel's own links, with its line. (Menu
        // records an editor adds under About follow the configured links.)
        preg_match('#<div class="k-mg__links">(.*?)</div>#s', $html, $panel);
        preg_match_all('#<a class="k-ml" href="([^"]+)">\s*<b>([^<]+)</b>\s*(?:<small>([^<]+)</small>)?#', $panel[1], $links, PREG_SET_ORDER);
        $described = array_values(array_filter($links, fn ($l) => ($l[3] ?? '') !== ''));
        $last = end($described);
        $this->assertStringEndsWith($url, $last[1]);
        $this->assertSame($label, $last[2]);
        $this->assertSame(__('d3_partner.menu_d', [], $locale), $last[3]);

        // The phone drawer lists the panel's links.
        $this->assertMatchesRegularExpression('#<aside class="k-drawer".*?<a href="[^"]*'.preg_quote($url, '#').'">'.preg_quote($label, '#').'</a>#s', $html);

        // The footer's About column.
        $this->assertMatchesRegularExpression('#<nav class="k-smap".*?<a href="[^"]*'.preg_quote($url, '#').'"\s*>'.preg_quote(PartnerPageSeeder::MENU_LABEL[$locale], '#').'</a>#s', $html);
    }

    public function test_an_unpublished_page_leaves_the_menu_and_answers_404(): void
    {
        Page::where('slug', 'partner-with-us')->first()->forceFill(['status' => 'draft'])->save();

        $this->get('/en/partner-with-us')->assertNotFound();
        $html = $this->get('/en/about')->assertOk()->getContent();
        $this->assertStringNotContainsString('/en/partner-with-us', $html);
    }

    public function test_the_seeder_inserts_only_once_and_keeps_edits(): void
    {
        $page = Page::where('slug', 'partner-with-us')->first();
        $page->setTranslations('en', ['heading' => 'Edited heading']);

        $this->seed(PartnerPageSeeder::class);

        $this->assertSame(1, Page::withTrashed()->where('slug', 'partner-with-us')->count());
        $this->assertSame(4, $page->blocks()->count());
        $this->assertSame(1, MenuItem::where('destination', 'partner-with-us')->count());
        $this->assertSame('Edited heading', $page->fresh()->translate('heading', 'en')->value);
        $this->assertTrue($page->fresh()->needs_client_review);
    }

    public function test_every_block_is_complete_in_three_languages_and_reads_as_cards(): void
    {
        $page = Page::where('slug', 'partner-with-us')->with('blocks.translations', 'translations')->first();
        $counts = ['why-partner' => 4, 'ways-to-partner' => 5, 'how-it-works' => 3];

        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach (['title', 'heading', 'standfirst', 'meta_description'] as $field) {
                $this->assertFalse($page->translate($field, $locale)->isFallback(), "$locale $field");
            }
            foreach ($page->blocks as $block) {
                foreach ($block->translatableFields() as $field) {
                    if ($block->translations->where('field', $field)->isEmpty()) {
                        continue;
                    }
                    $this->assertNotNull($block->translations->where('field', $field)->firstWhere('locale', $locale), "$locale {$block->anchor} $field");
                }
                if (isset($counts[$block->anchor])) {
                    $this->assertCount($counts[$block->anchor], StructuredText::sections($block->translate('body', $locale)->value)['sections'], "$locale {$block->anchor}");
                }
            }
        }
    }

    public function test_a_theme_from_a_thematic_page_is_named_near_the_top(): void
    {
        $this->get('/en/partner-with-us?theme=spectrum-management')->assertOk()
            ->assertSee('You are interested in: Spectrum management');
        $this->get('/fr/partner-with-us?theme=spectrum-management')->assertOk()
            ->assertSee('Thème qui vous intéresse : '.__('d3_themes.themes.spectrum-management.title', [], 'fr'), false);
        $this->get('/pt/partner-with-us?theme=spectrum-management')->assertOk()
            ->assertSee('Tema do seu interesse: '.__('d3_themes.themes.spectrum-management.title', [], 'pt'), false);

        // The title of a published page with that slug, where there is one.
        $this->get('/en/partner-with-us?theme=about')->assertOk()->assertSee('You are interested in: About CRASA');

        // Anything that is not a slug is ignored, never printed.
        foreach (['<script>alert(1)</script>', 'a b', ''] as $bad) {
            $html = $this->get('/en/partner-with-us?theme='.urlencode($bad))->assertOk()->getContent();
            $this->assertStringNotContainsString('You are interested in', $html);
            $this->assertStringNotContainsString('<script>alert(1)', $html);
        }
        $this->get('/en/partner-with-us?theme[]=x')->assertOk()->assertDontSee('You are interested in');
    }

    public function test_the_theme_is_carried_into_the_contact_subject(): void
    {
        $html = $this->get('/en/partner-with-us?theme=spectrum-management')->assertOk()->getContent();
        $this->assertStringContainsString('subject=Partnership%3A+Spectrum+management', $html);

        $this->get('/en/contact?subject='.urlencode('Partnership: Spectrum management'))
            ->assertOk()
            ->assertSee('value="Partnership: Spectrum management"', false);
        $this->get('/en/contact?subject='.urlencode('<script>x</script>'))
            ->assertOk()
            ->assertDontSee('<script>x</script>', false);
    }

    public function test_a_thematic_area_is_named_by_its_own_title_in_each_language(): void
    {
        $this->get('/en/partner-with-us?theme=numbering-roaming')->assertOk()->assertSee('Numbering and roaming');
        $fr = __('d3_themes.themes.broadband-universal-access.title', [], 'fr');
        $pt = __('d3_themes.themes.cybersecurity.title', [], 'pt');
        $this->get('/fr/partner-with-us?theme=broadband-universal-access')->assertOk()->assertSee($fr)->assertDontSee('Broadband universal access');
        $this->get('/pt/partner-with-us?theme=cybersecurity')->assertOk()->assertSee($pt);
    }
}
