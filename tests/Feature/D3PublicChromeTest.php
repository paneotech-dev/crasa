<?php

namespace Tests\Feature;

use Database\Seeders\CountrySeeder;
use Database\Seeders\HeaderMenuSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client review, 30 October 2026 (items 1c and 1d): the footer is four
 * columns (About, Publications, Newsroom, Members) with one Contact link,
 * the logo and address once and a legal line of three links; "Back to top"
 * and the display options float in the bottom corner of every public page.
 */
class D3PublicChromeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(HeaderMenuSeeder::class);
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    /** @return list<string> */
    private function texts(DOMXPath $x, string $query): array
    {
        return array_map(fn ($n) => trim(preg_replace('/\s+/', ' ', $n->textContent)), iterator_to_array($x->query($query)));
    }

    private function assertFooter(string $locale, string $path): void
    {
        app()->setLocale($locale);
        $html = $this->get($path)->assertOk()->getContent();
        $x = $this->xpath($html);

        $this->assertSame(
            [__('footer.about'), __('footer.publications'), __('footer.newsroom'), __('chome.sm_services')],
            $this->texts($x, '//nav[contains(@class, "k-smap")]//h2'),
            $path
        );
        $this->assertSame(0, $x->query('//*[@data-k-areas]')->length, $path);
        $this->assertStringNotContainsString(e(__('evening.areas_heading')), $html, $path);

        // One Contact link across the band and the footer, in the Members column.
        $contact = $x->query('//nav[contains(@class, "k-smap")]//a[contains(@href, "/'.$locale.'/contact")] | //footer[contains(@class, "k-foot")]//a[contains(@href, "/'.$locale.'/contact")]');
        $this->assertSame(1, $contact->length, $path);
        $this->assertSame(__('chome.sm_services'), trim($contact->item(0)->parentNode->getElementsByTagName('h2')->item(0)->textContent));

        // Each destination once in the band.
        $hrefs = array_map(fn ($a) => rtrim(strtok($a->getAttribute('href'), '?#'), '/'), iterator_to_array($x->query('//nav[contains(@class, "k-smap")]//a')));
        $this->assertSame(1, count(array_keys($hrefs, url($locale.'/members'))), $path);

        // The logo and the address block once.
        $this->assertSame(1, $x->query('//footer[contains(@class, "k-foot")]//img[contains(@src, "crasa-logo")]')->length, $path);
        $this->assertSame(1, $x->query('//footer[contains(@class, "k-foot")]//address')->length, $path);

        // The bottom line: terms, privacy, cookie settings (item 1h),
        // accessibility statement.
        $this->assertSame(
            [__('footer.terms'), __('footer.privacy'), __('d3_privacy.footer_link'), __('footer.accessibility')],
            $this->texts($x, '//div[contains(@class, "k-fbar")]//a'),
            $path
        );
    }

    public function test_the_footer_is_four_columns_with_contact_once_in_three_languages(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach (["/{$locale}", "/{$locale}/about", "/{$locale}/contact"] as $path) {
                $this->assertFooter($locale, $path);
            }
        }
    }

    public function test_the_menu_records_give_the_same_four_columns(): void
    {
        // The footer menu records carry Contact and Member area in the
        // Newsroom column and Members in Publications; each is drawn once.
        $this->seed(MenuSeeder::class);

        $this->assertFooter('en', '/en');
        $this->assertFooter('pt', '/pt/about');
    }

    public function test_the_floating_controls_are_on_every_public_page(): void
    {
        foreach (['/en', '/fr/about', '/pt/contact'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $x = $this->xpath($html);
            $locale = substr($path, 1, 2);

            $float = $x->query('//div[contains(@class, "c-float")][@data-float]');
            $this->assertSame(1, $float->length, $path);
            $this->assertSame(__('d3_pub.float_label', [], $locale), $float->item(0)->getAttribute('aria-label'));

            $top = $x->query('//div[@data-float]/a[@data-float-top]');
            $this->assertSame(1, $top->length, $path);
            $this->assertSame('#top', $top->item(0)->getAttribute('href'));
            $this->assertSame(__('chome.back_to_top', [], $locale), $top->item(0)->getAttribute('aria-label'));
            $this->assertTrue($top->item(0)->hasAttribute('hidden'), $path);
            $this->assertSame(1, $x->query('//*[@id="top"]')->length, $path);

            // The display options in the dock, pause animations among them.
            $this->assertSame(1, $x->query('//div[@data-float]//button[@data-a11y-toggle]')->length, $path);
            $this->assertSame(1, $x->query('//div[@data-float]//button[@data-a11y-flag="pause"]')->length, $path);
            $this->assertStringContainsString(__('a11y.pause', [], $locale), $html);
            $this->assertSame(1, substr_count($html, 'data-a11y-toggle'));
            $this->assertMatchesRegularExpression('#<script src="[^"]*js/float\.js[^"]*" defer></script>#', $html);
        }
    }

    public function test_the_floating_controls_are_44px_clear_of_safe_areas_and_hidden_in_print(): void
    {
        $css = file_get_contents(public_path('css/a11y.css'));
        $float = substr($css, strpos($css, '/* Floating page controls'));

        $this->assertMatchesRegularExpression('/\.c-float \{[^}]*position: fixed;[^}]*env\(safe-area-inset-right[^}]*env\(safe-area-inset-bottom/s', $float);
        $this->assertMatchesRegularExpression('/\.c-float__top,\s*\.c-float \.c-a11y > \.c-a11y__toggle \{[^}]*width: 44px;[^}]*height: 44px;/s', $float);
        $this->assertMatchesRegularExpression('/:focus-visible \{[^}]*outline: 2px solid/s', $float);
        $this->assertMatchesRegularExpression('/@media print \{\s*\.c-float \{ display: none !important; \}/', $float);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $float);

        $js = file_get_contents(public_path('js/float.js'));
        $this->assertStringContainsString('window.innerHeight', $js);
        $this->assertStringContainsString("prefers-reduced-motion: reduce", $js);
        $this->assertStringContainsString('a11y-pause', $js);
    }

    public function test_the_home_social_heading_has_no_sentence_under_it(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringNotContainsString(__('fb.latest_intro'), $html);
        $this->assertStringNotContainsString(__('fb.channels_intro'), $html);
    }

    public function test_the_strings_exist_in_three_languages(): void
    {
        $en = array_keys(require lang_path('en/d3_pub.php'));
        foreach (['fr', 'pt'] as $locale) {
            $this->assertSame($en, array_keys(require lang_path($locale.'/d3_pub.php')), $locale);
        }
        $this->assertSame('États membres', __('d3_pub.member_states', [], 'fr'));
        $this->assertSame('Estados-Membros', __('d3_pub.member_states', [], 'pt'));
    }
}
