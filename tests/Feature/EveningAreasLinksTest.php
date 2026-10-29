<?php

namespace Tests\Feature;

use Database\Seeders\CountrySeeder;
use Database\Seeders\HeaderMenuSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Concept 3 "areas" quick links. Measured on the preview: the nav row has
 * about 200 px free at 1366 and is a drawer at 1024, so the four links sit
 * in the sitemap band on every page, not in the nav row.
 */
class EveningAreasLinksTest extends TestCase
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

    public function test_the_sitemap_band_carries_the_four_areas_on_every_page_in_three_languages(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach (["/{$locale}", "/{$locale}/contact"] as $path) {
                $html = $this->get($path)->assertOk()->getContent();
                $start = strpos($html, '<div data-k-areas>');
                $this->assertNotFalse($start, $path);
                $col = substr($html, $start, strpos($html, '</div>', $start) - $start);

                app()->setLocale($locale);
                $this->assertStringContainsString(e(__('evening.areas_heading')), $col);
                $this->assertStringContainsString("/{$locale}/publications?type[]=guideline", urldecode($col));
                $this->assertStringContainsString("/{$locale}/publications?sector=telecommunications", $col);
                $this->assertStringContainsString("/{$locale}/publications?sector=postal", $col);
                $this->assertStringContainsString("/{$locale}#what-we-do", $col);
                $this->assertSame(4, substr_count($col, '<a href='));
            }
        }

        // Not in the nav row.
        $html = $this->get('/en')->getContent();
        $row = substr($html, strpos($html, 'data-k-navrow'), strpos($html, '</header>') - strpos($html, 'data-k-navrow'));
        $this->assertStringNotContainsString(__('evening.area_frameworks'), $row);
    }

    public function test_the_capacity_anchor_exists_on_home(): void
    {
        $this->assertStringContainsString('id="what-we-do"', $this->get('/en')->getContent());
    }
}
