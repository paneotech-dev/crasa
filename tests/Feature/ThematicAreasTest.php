<?php

namespace Tests\Feature;

use Database\Seeders\CountrySeeder;
use Database\Seeders\HeaderMenuSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * CRASA's thematic areas (30 October 2026): the scrolling band on the
 * home page, the index, one page per theme in three languages, the About
 * menu link and the vendored icons.
 */
class ThematicAreasTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'fr', 'pt'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(HeaderMenuSeeder::class);
    }

    private function slugs(): array
    {
        return array_keys(config('themes'));
    }

    private function band(string $html): string
    {
        $this->assertMatchesRegularExpression('#<section class="th-band".*?</section>#s', $html);
        preg_match('#<section class="th-band".*?</section>#s', $html, $m);

        return $m[0];
    }

    public function test_there_are_eight_to_ten_themes_each_with_a_vendored_icon(): void
    {
        $this->assertGreaterThanOrEqual(8, count($this->slugs()));
        $this->assertLessThanOrEqual(10, count($this->slugs()));

        foreach (config('themes') as $slug => $theme) {
            $this->assertFileExists(public_path("vendor/bootstrap-icons/icons/{$theme['icon']}.svg"), $slug);
        }
        $this->assertFileExists(public_path('vendor/bootstrap-icons/LICENSE'));
    }

    public function test_the_home_band_links_each_theme_once_and_hides_the_copy(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->get("/$locale")->assertOk()->getContent();
            $band = $this->band($html);

            $this->assertStringContainsString(__('d3_themes.home_heading', [], $locale), $band);
            $this->assertSame(1, substr_count($html, '<h1'), $locale);

            foreach ($this->slugs() as $slug) {
                $this->assertSame(1, substr_count($band, "/$locale/themes/$slug\""), "$locale $slug");
            }

            // The loop copy is hidden and holds nothing focusable.
            $this->assertMatchesRegularExpression('#<ul class="th-list th-list--copy" role="list" aria-hidden="true">(.*?)</ul>#s', $band);
            preg_match('#<ul class="th-list th-list--copy"[^>]*>(.*?)</ul>#s', $band, $copy);
            $this->assertStringNotContainsString('<a ', $copy[1]);
            $this->assertStringNotContainsString('tabindex', $copy[1]);
            $this->assertStringNotContainsString('<button', $copy[1]);

            // Every icon is decorative: the title beside it names the theme.
            $this->assertSame(substr_count($band, '<svg'), substr_count($band, 'aria-hidden="true"') - 1);
            $this->assertSame(2 * count($this->slugs()), substr_count($band, 'class="th-svg"'));
        }
    }

    public function test_the_band_sits_right_after_the_figures(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();
        $glance = strpos($html, 'class="k-glance"');
        $band = strpos($html, 'class="th-band"');
        $work = strpos($html, 'id="what-we-do"');

        $this->assertNotFalse($band);
        if ($glance !== false) {
            $this->assertLessThan($band, $glance);
        }
        if ($work !== false) {
            $this->assertLessThan($work, $band);
        }
    }

    public function test_the_band_stops_for_reduced_motion_and_the_pause_option(): void
    {
        $css = file_get_contents(public_path('css/themes.css'));

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('html.a11y-pause .th-track { animation: none;', $css);
        $this->assertStringContainsString('.th-marquee:hover .th-track', $css);
        $this->assertStringContainsString('.th-marquee:focus-within .th-track { animation: none; }', $css);
        $this->assertStringNotContainsString('http', preg_replace('#/\*.*?\*/#s', '', $css));
    }

    public function test_the_index_lists_every_theme_in_three_languages(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->get("/$locale/themes")->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), $locale);
            $this->assertMatchesRegularExpression('#<h1[^>]*>'.preg_quote(e(__('d3_themes.index_title', [], $locale)), '#').'</h1>#', $html);
            foreach ($this->slugs() as $slug) {
                $this->assertStringContainsString("/$locale/themes/$slug\"", $html);
                $this->assertStringContainsString(e(__("d3_themes.themes.$slug.title", [], $locale)), $html);
            }
        }
    }

    public function test_each_theme_page_has_one_h1_and_the_partner_button(): void
    {
        \App\Models\Page::factory()->create(['slug' => 'partner-with-us']);

        foreach (self::LOCALES as $locale) {
            foreach ($this->slugs() as $slug) {
                $html = $this->get("/$locale/themes/$slug")->assertOk()->getContent();
                $title = e(__("d3_themes.themes.$slug.title", [], $locale));

                $this->assertSame(1, substr_count($html, '<h1'), "$locale $slug");
                $this->assertMatchesRegularExpression('#<h1[^>]*>'.preg_quote($title, '#').'</h1>#', $html);
                $this->assertStringContainsString("/$locale/partner-with-us?theme=$slug\"", $html);
                $this->assertStringContainsString(e(__('d3_themes.partner_button', [], $locale)), $html);
                foreach ((array) __("d3_themes.themes.$slug.body", [], $locale) as $paragraph) {
                    $this->assertStringContainsString(e($paragraph), $html);
                }
            }
        }
    }

    public function test_the_partner_button_falls_back_to_contact_until_the_partner_page_is_published(): void
    {
        $html = $this->get('/fr/themes/broadcasting')->assertOk()->getContent();

        $this->assertStringNotContainsString('partner-with-us', $html);
        $this->assertStringContainsString('/fr/contact?theme=broadcasting"', $html);
        $this->assertStringContainsString(e(__('d3_themes.partner_button', [], 'fr')), $html);
    }

    public function test_an_unknown_theme_is_a_404(): void
    {
        $this->get('/en/themes/not-a-theme')->assertNotFound();
        $this->get('/en/themes/NOT_A_THEME')->assertNotFound();
    }

    public function test_the_about_menu_links_the_index(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->get("/$locale/about")->assertOk()->getContent();
            preg_match('#<header.*?</header>#s', $html, $header);
            $nav = $header[0] ?? $html;

            $this->assertMatchesRegularExpression('#href="[^"]*/'.$locale.'/themes"[^>]*>.*?'.preg_quote(e(__('d3_themes.menu', [], $locale)), '#').'#s', $nav);
        }
    }

    public function test_every_string_is_translated(): void
    {
        $en = require lang_path('en/d3_themes.php');
        $flatten = function (array $a, string $p = '') use (&$flatten): array {
            $out = [];
            foreach ($a as $k => $v) {
                is_array($v) ? $out += $flatten($v, "$p$k.") : $out["$p$k"] = $v;
            }

            return $out;
        };
        $enFlat = $flatten($en);
        $keys = array_keys($enFlat);

        foreach (['fr', 'pt'] as $locale) {
            $other = $flatten(require lang_path("$locale/d3_themes.php"));
            $this->assertSame($keys, array_keys($other), $locale);
            foreach ($other as $key => $value) {
                $this->assertNotSame('', trim($value), "$locale $key");
                if ($key !== 'related_committee' && strlen($enFlat[$key]) > 12) {
                    $this->assertNotSame($enFlat[$key], $value, "$locale $key is untranslated");
                }
            }
        }

        foreach (self::LOCALES as $locale) {
            $text = file_get_contents(lang_path("$locale/d3_themes.php"));
            $this->assertStringNotContainsString("\u{2014}", $text, "$locale has an em dash");
        }
    }
}
