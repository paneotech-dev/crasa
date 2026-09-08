<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Support\ImageLibrary;
use App\Support\Locales;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    /* Images --------------------------------------------------------------- */

    public function test_the_hero_image_is_served_as_derivatives_never_the_original(): void
    {
        $html = $this->get('/en')->getContent();

        $this->assertStringContainsString('images/derivatives/', $html);
        $this->assertStringNotContainsString('crasa-images/people/people-market-mobile-01.jpg', $html);
    }

    public function test_the_hero_image_offers_a_webp_source_and_a_jpeg_fallback(): void
    {
        $html = $this->get('/en')->getContent();

        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('.jpg', $html);
        $this->assertStringContainsString('srcset=', $html);
        $this->assertStringContainsString('sizes=', $html);
    }

    public function test_alt_text_is_present_in_all_three_languages(): void
    {
        $this->get('/en')->assertSee('A woman using a smartphone at a market stall in Cape Town', false);
        $this->get('/fr')->assertSee('étal de marché du Cap', false);
        $this->get('/pt')->assertSee('banca de mercado na Cidade do Cabo', false);
    }

    public function test_no_derivative_is_larger_than_a_mobile_connection_can_carry(): void
    {
        // The archive original is 20 MB. Nothing that size reaches a page.
        $path = ImageLibrary::derivativePath('hero', 480, 'webp');
        $bytes = Storage::disk('public')->size($path);

        $this->assertLessThan(120_000, $bytes, 'The 480px hero derivative is too heavy for a mobile connection.');
    }

    /**
     * A slot with no image renders the designed absence, never a broken img
     * and never a grey box.
     */
    public function test_a_slot_with_no_image_renders_the_designed_absence(): void
    {
        $page = Page::where('slug', 'home')->sole();
        $hero = $page->blocks()->where('block_type', BlockType::Hero->value)->sole();
        $hero->update(['settings' => ['image' => 'nonexistent-slot', 'cta_url' => '/publications']]);

        $html = $this->get('/en')->getContent();

        $this->assertStringContainsString('b__slot', $html);
        $this->assertStringNotContainsString('<img src="" ', $html);
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
