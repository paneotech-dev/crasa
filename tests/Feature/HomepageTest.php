<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\Sector;
use App\Models\User;
use App\Support\HeroPanel;
use App\Support\Locales;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

/**
 * Screen 02, composed from the block library.
 */
class HomepageTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedComposedPages();
    }

    /* The page is data ----------------------------------------------------- */

    public function test_the_homepage_is_a_page_record_not_a_blade_template(): void
    {
        $page = Page::where('slug', 'home')->sole();

        $this->assertTrue($page->isPublished());
        $this->assertSame('landing', $page->template);
        $this->assertGreaterThanOrEqual(8, $page->blocks()->count());
    }

    public function test_the_blocks_are_the_ones_screen_02_draws(): void
    {
        $types = Page::where('slug', 'home')->sole()->blocks->map(fn ($b) => $b->type());

        foreach ([
            BlockType::Hero,
            BlockType::KeyFigures,
            BlockType::Pillars,
            BlockType::FeaturedComparison,
            BlockType::FeaturedPublications,
            BlockType::NewsroomStrip,
            BlockType::MemberGrid,
            BlockType::SocialFeed,
        ] as $expected) {
            $this->assertTrue($types->contains($expected), 'Missing block: '.$expected->value);
        }
    }

    public function test_hiding_a_block_removes_it_from_the_page_without_a_deploy(): void
    {
        $page = Page::where('slug', 'home')->sole();
        $page->blocks()->where('block_type', BlockType::Pillars->value)->update(['is_visible' => false]);

        $html = $this->get('/en')->getContent();

        $this->assertStringNotContainsString('Harmonised frameworks', $html);
    }

    public function test_reordering_blocks_reorders_the_page(): void
    {
        $page = Page::where('slug', 'home')->sole();
        $hero = $page->blocks()->where('block_type', BlockType::Hero->value)->sole();
        $hero->update(['position' => 99]);

        $ordered = $page->fresh()->blocks->map(fn ($b) => $b->type()->value);

        $this->assertSame('hero', $ordered->last());
    }

    /* Rendering ------------------------------------------------------------ */

    public function test_the_homepage_renders_in_all_three_locales(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get('/'.$locale)->assertOk();
        }
    }

    public function test_the_headline_is_the_approved_copy_in_each_language(): void
    {
        $this->get('/en')->assertSee('Harmonising ICT and postal regulation across Southern Africa.');
        $this->get('/fr')->assertSee('Harmoniser la réglementation des TIC');
        $this->get('/pt')->assertSee('Harmonizar a regulamentação das TIC');
    }

    public function test_the_four_lines_of_work_render_in_each_language(): void
    {
        $this->get('/en')->assertSee('Harmonised frameworks');
        $this->get('/fr')->assertSee('Cadres harmonisés');
        $this->get('/pt')->assertSee('Quadros harmonizados');
    }

    public function test_every_string_comes_from_a_lang_file_or_the_database(): void
    {
        // No English leaks into the French page from a hardcoded template.
        $french = $this->get('/fr')->getContent();

        $this->assertStringNotContainsString('What we do', $french);
        $this->assertStringNotContainsString('Member regulators', $french);
    }

    /* The hero --------------------------------------------------------------- */

    /**
     * Every figure in the panel is computed. Screen 08's own band carries
     * "350M+ citizens served", which is a number nobody has sourced for us.
     * One invented figure makes every real one on the page unusable.
     */
    public function test_every_figure_in_the_hero_panel_is_computed(): void
    {
        $this->seed(IndicatorSeeder::class);
        $this->seedIndicatorValues();

        $panel = HeroPanel::build();

        foreach ($panel['bars'] as $bar) {
            $this->assertSame(
                round($bar['filed'] / $bar['expected'] * 100).'%',
                $bar['value'],
                'A hero bar states a figure it did not compute.'
            );
        }

        $this->assertSame((string) Country::count(), $panel['counts'][0]['value']);
        $this->assertStringNotContainsString('350M', $this->get('/en')->getContent());
    }

    /**
     * Per-member-state completeness ranks fourteen sovereign regulators by
     * how well they have filed. That view lives behind an account on the
     * completeness dashboard, and publishing it on the front page is
     * CRASA's decision to take rather than ours.
     */
    public function test_the_panel_does_not_rank_member_states_on_the_public_homepage(): void
    {
        $this->seed(IndicatorSeeder::class);
        $this->seedIndicatorValues();

        // Checked on the panel itself rather than on the rendered band.
        // Slide one is a photograph now and carries no panel, so a string
        // search of the homepage would pass for the wrong reason: there
        // would be no bars to find a country name in.
        $panel = HeroPanel::build();

        $this->assertNotEmpty($panel['bars']);

        $names = array_column($panel['bars'], 'name');

        foreach (Country::pluck('name') as $name) {
            $this->assertNotContains(
                $name,
                $names,
                $name.' is named in the figures panel bars, which rank filing performance.'
            );
        }
    }

    /**
     * A panel with no sector carrying an indicator renders nothing rather
     * than three empty tracks. An empty bar chart is worse than no bar
     * chart: it reports zero where it means "not measured".
     */
    public function test_the_panel_is_absent_when_there_is_nothing_to_measure(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringNotContainsString('b-hero__panel', $html);
    }

    private function seedIndicatorValues(): void
    {
        foreach (Indicator::active()->get() as $indicator) {
            foreach (Country::limit(9)->get() as $country) {
                IndicatorValue::factory()->create([
                    'indicator_id' => $indicator->id,
                    'country_id' => $country->id,
                    'period' => '2025',
                ]);
            }
        }
    }

    /* Empty states --------------------------------------------------------- */

    /**
     * An empty state is correct when there is no data and a bug when there
     * is. The blocks were written with their empty states first and never
     * given a source, so a fully seeded database still rendered "No
     * publications match this filter" three times down the homepage.
     */
    public function test_a_seeded_database_never_renders_an_empty_publications_or_news_block(): void
    {
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);

        $this->assertGreaterThan(0, Publication::listed()->count());
        $this->assertGreaterThan(0, NewsItem::published()->count());

        $html = $this->get('/en')->getContent();

        $this->assertStringNotContainsString('No publications match this filter', $html);
        $this->assertStringNotContainsString('No updates yet', $html);
    }

    public function test_the_seeded_blocks_render_real_records(): void
    {
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);

        $html = $this->get('/en')->getContent();

        $newest = Publication::listed()->orderByDesc('published_on')->first();
        $this->assertStringContainsString($newest->translated('title', $newest->slug), $html);

        $latest = NewsItem::published()->orderByDesc('published_at')->first();
        $this->assertStringContainsString($latest->translated('title', $latest->slug), $html);
    }

    /* Layout at 375px ------------------------------------------------------ */

    public function test_the_page_carries_one_h1(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $html = $this->get('/'.$locale)->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), 'Locale '.$locale.' has more than one h1.');
        }
    }
}
