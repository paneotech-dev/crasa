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

    public function test_the_fourteen_member_states_render(): void
    {
        $html = $this->get('/en')->getContent();

        $this->assertSame(14, substr_count($html, 'b-member__name'));
        $this->assertStringContainsString('Angola', $html);
        $this->assertStringContainsString('Zimbabwe', $html);
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
     * The homepage runs with no photograph, and that is the composition
     * rather than a gap.
     *
     * The band held a market scene in Cape Town, then an equipment testing
     * bench. Both were wrong in the same way: CRASA is a treaty body that
     * fourteen national regulatory authorities answer to, and a hero makes a
     * claim about what an organisation is, on the first screen, to a
     * regulator. Screen 02 draws a slide with no photograph of its own, so
     * navy and the typography are the screen's own answer and not a fallback
     * from it.
     *
     * This is held by test because "the hero has no image" looks exactly
     * like an oversight to anyone who arrives later, and the natural repair
     * is to put a photograph back.
     */
    public function test_the_hero_runs_on_navy_and_typography_with_no_photograph(): void
    {
        $hero = Page::where('slug', 'home')->sole()
            ->blocks()->where('block_type', BlockType::Hero->value)->sole();

        $this->assertNull(
            $hero->setting('image'),
            'The homepage hero has a photograph again. See App\Support\ImageLibrary for why it does not.'
        );

        $html = $this->get('/en')->assertOk()->getContent();
        $band = substr($html, 0, strpos($html, 'b-figures__grid'));

        // No photograph, and no grey box saying one is coming either. A
        // placeholder on the first screen of a treaty body's site tells a
        // regulator the site is unfinished.
        $this->assertStringNotContainsString('b-hero__media', $band);
        $this->assertStringNotContainsString('b__slot', $band);

        // What is there instead: the navy ground, the weave screen 02 draws
        // on a slide with no photograph, and the headline.
        $this->assertStringContainsString('b-hero__weave', $band);
        $this->assertStringContainsString('b-hero__headline', $band);
    }

    /**
     * The panel screen 02 draws in the second column of a slide with no
     * photograph. Its absence was the gap that made the band read thin at
     * laptop widths, where the copy has stopped filling the row.
     */
    public function test_the_hero_carries_the_data_panel_the_screen_draws(): void
    {
        $this->seed(IndicatorSeeder::class);
        $this->seedIndicatorValues();

        $html = $this->get('/en')->assertOk()->getContent();
        $band = substr($html, 0, strpos($html, 'b-figures__grid'));

        $this->assertStringContainsString('b-hero__panel', $band);

        // One bar per sector, each stating its own figure as text. A bar a
        // reader cannot read a number off is a picture of a proportion.
        foreach (Sector::pluck('name') as $sector) {
            $this->assertStringContainsString($sector, $band, $sector.' has no bar in the hero panel.');
        }

        $this->assertMatchesRegularExpression('~b-hero__bar-value">\d+%<~', $band);
        $this->assertMatchesRegularExpression('~b-hero__bar-fill" style="width: \d+%"~', $band);
    }

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
     * The counts under the bars go where they point, like every other count
     * on the platform.
     */
    public function test_the_panel_counts_link_to_what_they_count(): void
    {
        $this->seed(IndicatorSeeder::class);
        $this->seedIndicatorValues();

        $band = substr($this->get('/en')->getContent(), 0, strpos($this->get('/en')->getContent(), 'b-figures__grid'));

        $this->assertMatchesRegularExpression(
            '~b-hero__counts.*?href="http://localhost/en/members"~s',
            $band
        );
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

        $band = substr($this->get('/en')->getContent(), 0, strpos($this->get('/en')->getContent(), 'b-figures__grid'));
        $bars = substr($band, strpos($band, 'b-hero__bars'), 2000);

        foreach (Country::pluck('name') as $name) {
            $this->assertStringNotContainsString(
                '>'.$name.'<',
                $bars,
                $name.' is named in the hero panel bars, which rank filing performance.'
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

    /**
     * The converse still has to hold: with nothing seeded, the empty state
     * is the right answer and must still appear.
     */
    public function test_an_unseeded_block_still_shows_its_empty_state(): void
    {
        $html = $this->get('/en')->getContent();

        $this->assertStringContainsString('No publications match this filter', $html);
        $this->assertStringContainsString('No updates yet', $html);
        // Indicators arrive with the completeness dashboard.
        $this->assertStringContainsString('Nothing selected yet', $html);
    }

    /* Layout at 375px ------------------------------------------------------ */

    /**
     * French runs 15 to 30 percent longer than English, and the hero headline
     * is where that first bites.
     *
     * This is reasoning from the CSS, not measurement: there is no browser on
     * this host. What it proves is that nothing pins the headline to a fixed
     * size or width, so it cannot collide by construction. The four-width
     * pass in DevTools is still the confirmation.
     */
    public function test_the_hero_headline_cannot_collide_with_the_header(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));
        $rule = substr($css, strpos($css, '.b-hero__headline'));
        $rule = substr($rule, 0, strpos($rule, '}'));

        // Fluid size: the longest language sets it, not English.
        $this->assertStringContainsString('clamp(', $rule);
        // No fixed height or width to overflow out of. Matched on a word
        // boundary so line-height does not read as a fixed height.
        $this->assertDoesNotMatchRegularExpression('/(?<!line-)height:\s*\d/', $rule);
        $this->assertStringNotContainsString('white-space: nowrap', $rule);
        $this->assertStringContainsString('text-wrap: balance', $rule);

        // And the hero is a separate section below the sticky header, so
        // the headline and the switcher never share a line.
        $html = $this->get('/fr')->getContent();
        $headerEnd = strpos($html, '</header>');
        $heroStart = strpos($html, 'b-hero');

        $this->assertLessThan($heroStart, $headerEnd, 'The hero must sit below the header, not inside it.');
    }

    public function test_the_page_carries_one_h1(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $html = $this->get('/'.$locale)->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), 'Locale '.$locale.' has more than one h1.');
        }
    }
}
