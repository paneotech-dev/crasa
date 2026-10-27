<?php

namespace Tests\Feature;

use App\Enums\NewsCategory;
use App\Models\NewsItem;
use App\Support\NewsFormat;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The newsroom listing: newest first, a featured item on page 1 only, whole
 * pages of twelve, no filter option with nothing behind it, and excerpts
 * that stop at a word.
 */
class NewsroomLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SectorSeeder::class);
    }

    /** @return list<NewsItem> */
    private function items(int $count): array
    {
        $made = [];

        for ($i = 1; $i <= $count; $i++) {
            $item = NewsItem::factory()->create([
                'slug' => sprintf('item-%02d', $i),
                'category' => NewsCategory::News,
                'status' => 'published',
                'published_at' => Carbon::parse('2025-01-01')->addDays($i),
                'date_unknown' => false,
            ]);
            $item->setTranslations('en', ['title' => sprintf('Item %02d', $i), 'summary' => 'Summary '.$i]);
            $made[] = $item;
        }

        return $made;
    }

    private function slugsIn(string $html): array
    {
        preg_match_all('#/en/newsroom/(item-\d\d)"#', $html, $m);

        return array_values(array_unique($m[1]));
    }

    public function test_newest_first_with_the_newest_featured_on_page_one_only(): void
    {
        $this->items(30);

        $first = $this->get('/en/newsroom')->assertOk()->getContent();
        $slugs = $this->slugsIn($first);

        // Featured, then twelve in the grid: item-30 down to item-18.
        $this->assertSame('item-30', $slugs[0]);
        $this->assertCount(13, $slugs);
        $this->assertSame('item-18', end($slugs));
        $this->assertStringContainsString('n-card--featured', $first);
        $this->assertStringContainsString('Showing 1 to 13 of 30', $first);

        $second = $this->get('/en/newsroom?page=2')->assertOk()->getContent();
        $this->assertStringNotContainsString('n-card--featured', $second);
        $this->assertCount(12, $this->slugsIn($second));
        $this->assertSame('item-17', $this->slugsIn($second)[0]);
        $this->assertStringContainsString('Showing 14 to 25 of 30', $second);
    }

    public function test_oldest_first_on_request(): void
    {
        $this->items(5);

        $html = $this->get('/en/newsroom?sort=oldest')->assertOk()->getContent();

        $this->assertSame('item-01', $this->slugsIn($html)[0]);
    }

    public function test_a_filter_option_with_no_items_is_not_offered(): void
    {
        $this->items(3);

        $html = $this->get('/en/newsroom')->assertOk()->getContent();

        // Every item is News: no tender, consultation or communique option,
        // and no topic group at all.
        $this->assertStringContainsString('value="news"', $html);
        $this->assertStringNotContainsString('value="tender"', $html);
        $this->assertStringNotContainsString('value="communique"', $html);
        $this->assertStringNotContainsString('name="tag[]"', $html);
    }

    public function test_year_and_keyword_filters_narrow_the_list(): void
    {
        $this->items(3);
        $old = NewsItem::factory()->create(['slug' => 'item-99', 'status' => 'published', 'published_at' => '2019-05-05', 'date_unknown' => false]);
        $old->setTranslations('en', ['title' => 'Spectrum zebra notice', 'summary' => 'x']);

        $this->assertSame(['item-99'], $this->slugsIn($this->get('/en/newsroom?year=2019')->getContent()));
        $this->assertSame(['item-99'], $this->slugsIn($this->get('/en/newsroom?q=zebra')->getContent()));
    }

    public function test_an_excerpt_stops_at_a_word_with_an_ellipsis(): void
    {
        $text = str_repeat('regulation ', 30);
        $excerpt = NewsFormat::excerpt($text, 50);

        $this->assertStringEndsWith('…', $excerpt);
        $this->assertStringEndsWith('regulation…', $excerpt);
        $this->assertLessThanOrEqual(51, mb_strlen($excerpt));
        $this->assertSame('Short text', NewsFormat::excerpt('Short text', 50));
    }

    public function test_the_long_date_follows_the_page_language(): void
    {
        $date = Carbon::parse('2025-11-04');

        $this->assertSame('4 November 2025', NewsFormat::longDate($date, 'en'));
        $this->assertSame('4 novembre 2025', NewsFormat::longDate($date, 'fr'));
        $this->assertSame('4 de novembro de 2025', NewsFormat::longDate($date, 'pt'));
    }
}
