<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use App\Models\Sector;
use App\Support\Locales;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsroomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SectorSeeder::class);
        $this->seed(NewsroomSeeder::class);
    }

    public function test_the_listing_renders_in_every_locale(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get("/{$locale}/newsroom")->assertOk();
        }
    }

    public function test_six_sample_articles_are_seeded_and_all_flagged(): void
    {
        $this->assertSame(6, NewsItem::count());
        $this->assertSame(6, NewsItem::sample()->count());
        $this->assertSame(0, NewsItem::verified()->count());
    }

    public function test_an_article_renders_in_every_locale(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get("/{$locale}/newsroom/spectrum-committee-ninth-meeting")->assertOk();
        }
    }

    public function test_titles_are_translated(): void
    {
        $this->get('/en/newsroom')->assertSee('Spectrum and Frequency Management Committee holds its ninth meeting');
        $this->get('/fr/newsroom')->assertSee('Comité de gestion du spectre');
        $this->get('/pt/newsroom')->assertSee('Comité de Gestão do Espectro');
    }

    public function test_every_sample_article_says_it_is_demonstration_content(): void
    {
        $html = $this->get('/en/newsroom/spectrum-committee-ninth-meeting')->getContent();

        $this->assertStringContainsString('demonstration content', $html);
        $this->assertStringContainsString('should be cited', $html);
    }

    /* Filtering ------------------------------------------------------------- */

    public function test_the_category_filter_narrows_the_listing(): void
    {
        $html = $this->get('/en/newsroom?category=tender')->getContent();

        $this->assertStringContainsString('Tender notice', $html);
        $this->assertStringNotContainsString('ninth meeting', $html);
    }

    public function test_the_sector_filter_narrows_the_listing(): void
    {
        $html = $this->get('/en/newsroom?sector=postal')->getContent();

        $this->assertStringContainsString('Postal and Logistics Committee', $html);
        $this->assertStringNotContainsString('Digital terrestrial television', $html);
    }

    public function test_the_tag_filter_narrows_the_listing(): void
    {
        $html = $this->get('/en/newsroom?tag=roaming')->getContent();

        $this->assertStringContainsString('roaming guidelines', $html);
        $this->assertStringNotContainsString('ninth meeting', $html);
    }

    public function test_filters_combine(): void
    {
        $this->get('/en/newsroom?category=news&sector=broadcasting')
            ->assertOk()
            ->assertSee('Digital terrestrial television')
            ->assertDontSee('Tender notice');
    }

    public function test_a_filter_with_no_matches_invites_an_action(): void
    {
        $html = $this->get('/en/newsroom?category=tender&sector=broadcasting')->getContent();

        $this->assertStringContainsString('No updates match these filters', $html);
        $this->assertStringContainsString('Clear the filters', $html);
    }

    public function test_the_active_filter_is_marked_for_assistive_technology(): void
    {
        $this->get('/en/newsroom?category=tender')->assertSee('aria-current="true"', false);
    }

    /* Publication state ----------------------------------------------------- */

    public function test_a_draft_article_is_not_listed_or_reachable(): void
    {
        $draft = NewsItem::factory()->draft()->create(['slug' => 'not-yet']);
        $draft->setTranslations('en', ['title' => 'Draft item']);

        $this->get('/en/newsroom')->assertDontSee('Draft item');
        $this->get('/en/newsroom/not-yet')->assertNotFound();
    }

    public function test_an_article_dated_in_the_future_is_not_yet_published(): void
    {
        $scheduled = NewsItem::factory()->scheduled()->create(['slug' => 'next-week']);
        $scheduled->setTranslations('en', ['title' => 'Scheduled item']);

        $this->get('/en/newsroom')->assertDontSee('Scheduled item');
        $this->get('/en/newsroom/next-week')->assertNotFound();
    }

    /* The grid rule ---------------------------------------------------------- */

    /**
     * Four articles with no hero of their own must not show one placeholder
     * four times in a row. The fallback is differentiated by sector.
     */
    public function test_the_image_fallback_differs_by_sector(): void
    {
        $slots = NewsItem::with('sector')->get()->map->fallbackImageSlot()->unique();

        $this->assertGreaterThanOrEqual(3, $slots->count(), 'The fallback image does not vary across the seeded set.');
    }

    public function test_each_sector_maps_to_its_own_fallback(): void
    {
        $telecom = NewsItem::factory()->create(['sector_id' => Sector::where('slug', 'telecommunications')->value('id')]);
        $postal = NewsItem::factory()->create(['sector_id' => Sector::where('slug', 'postal')->value('id')]);
        $none = NewsItem::factory()->create(['sector_id' => null]);

        $this->assertSame('sector-postal', $postal->fallbackImageSlot());
        $this->assertSame('institutional', $none->fallbackImageSlot());

        /*
         * Telecommunications has no fallback of its own since 9 September
         * 2026: the only telecoms photograph in the supplied library became
         * the homepage banner, and the same photograph twice on one page is
         * worse than one card showing its designed absence. Same reasoning
         * as broadcasting below.
         */
        $this->assertNull($telecom->fallbackImageSlot());
    }

    /* Article page ------------------------------------------------------------ */

    public function test_an_article_offers_related_items_that_are_not_itself(): void
    {
        $item = NewsItem::where('slug', 'spectrum-committee-ninth-meeting')->sole();
        $related = $item->related();

        $this->assertGreaterThan(0, $related->count());
        $this->assertFalse($related->contains('id', $item->id));
    }

    public function test_related_items_prefer_the_same_sector(): void
    {
        $item = NewsItem::where('slug', 'spectrum-committee-ninth-meeting')->sole();

        $this->assertSame($item->sector_id, $item->related()->first()->sector_id);
    }

    public function test_sharing_is_plain_links_not_third_party_widgets(): void
    {
        $html = $this->get('/en/newsroom/spectrum-committee-ninth-meeting')->getContent();

        $this->assertStringContainsString('linkedin.com/sharing/share-offsite', $html);
        // No tracking script loads on an article page.
        $this->assertStringNotContainsString('platform.twitter.com', $html);
        $this->assertStringNotContainsString('connect.facebook.net', $html);
        $this->assertStringNotContainsString('platform.linkedin.com', $html);
    }

    public function test_an_article_carries_one_h1(): void
    {
        $html = $this->get('/en/newsroom/spectrum-committee-ninth-meeting')->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_pagination_appears_when_there_are_enough_items(): void
    {
        NewsItem::factory()->count(12)->create();

        $this->get('/en/newsroom')->assertSee('c-pagination', false);
    }
}
