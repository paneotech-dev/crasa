<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Document;
use App\Models\Publication;
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
 * The Concept 3 home page (C1, C5, C6, C7, C9, C10, C11, C12; 29 October
 * 2026).
 */
class ConceptHomeTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_the_hero_is_one_composition_with_figures_and_one_h1(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('class="k-hero"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('class="k-hero__figs"', $html);
        $this->assertMatchesRegularExpression('/<dd data-count="\d+">\d+<\/dd>/', $html);
        $this->assertStringContainsString('Open the library', $html);
        $this->assertStringContainsString('Open the comparator', $html);
    }

    public function test_the_slider_script_covers_every_control(): void
    {
        $js = file_get_contents(public_path('js/hero.js'));

        foreach (['[data-k-hero]', 'visibilitychange', 'mouseenter', 'focusin', 'touchstart', "'ArrowLeft'", "'ArrowRight'", 'aria-pressed', 'prefers-reduced-motion', 'DELAY = 7000'] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle.' is missing.');
        }

        $css = file_get_contents(public_path('css/k.css'));
        $this->assertStringContainsString('.k-hero__fig { position: absolute; inset: 0; margin: 0; opacity: 0; transition: opacity .6s ease; }', $css);
        $this->assertMatchesRegularExpression('/\.k-hero__box \{[^}]*grid-template-columns: minmax\(360px, 37%\) minmax\(0, 1fr\)[^}]*height: 580px/', $css);
    }

    /**
     * Every slide shows the whole photograph (30 October 2026): drawn with
     * object-fit: contain over a blurred copy of itself, never cropped, with
     * the text on a CRASA blue card beside it and a counter and one progress
     * bar per slide in the controls.
     */
    public function test_the_hero_shows_each_photograph_whole_beside_the_card(): void
    {
        $css = file_get_contents(public_path('css/k.css'));
        $this->assertMatchesRegularExpression('/\.k-hero__img \{[^}]*object-fit: contain/', $css);
        $this->assertMatchesRegularExpression('/\.k-hero__bg \{[^}]*object-fit: cover[^}]*filter: blur\(/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.k-hero__img \{[^}]*object-fit: cover/', $css);
        $this->assertStringContainsString('--k-hero-card: var(--k-crasa-blue)', $css);

        $js = file_get_contents(public_path('js/hero.js'));
        $this->assertStringContainsString('[data-k-count]', $js);

        foreach (['en', 'fr', 'pt'] as $locale) {
            $html = $this->get('/'.$locale)->assertOk()->getContent();
            $hero = substr($html, strpos($html, '<section class="k-hero"'));
            $hero = substr($hero, 0, strpos($hero, '</section>'));

            $this->assertSame(1, substr_count($hero, '<h1 class="k-cap__h">'), $locale);
            $this->assertStringNotContainsString('object-position', $hero, $locale);
            $slides = substr_count($hero, 'data-k-slide');
            if ($slides > 1) {
                $this->assertSame($slides - 1, substr_count($hero, 'class="k-cap__h" role="heading" aria-level="2"'), $locale);
                $this->assertSame($slides, substr_count($hero, '<span><i></i></span></button>'), $locale);
                $this->assertMatchesRegularExpression('~<span class="k-hctl__count" data-k-count aria-hidden="true"><b>01</b> / 0'.$slides.'</span>~', $hero);
                $this->assertStringContainsString('data-k-pause aria-pressed="false"', $hero);
            }
        }
    }

    public function test_a_publication_without_a_file_or_link_is_not_listed(): void
    {
        $without = Publication::factory()->create(['slug' => 'test-of-publication', 'is_sample' => false]);
        $without->setTranslation('title', 'en', 'Test of Publication');

        $with = Publication::factory()->create(['slug' => 'with-a-file', 'is_sample' => false]);
        $with->setTranslation('title', 'en', 'A guideline with its file');
        Document::factory()->create([
            'documentable_type' => Publication::class,
            'documentable_id' => $with->id,
            'clearance' => Clearance::Public,
        ]);

        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringNotContainsString('Test of Publication', $html);
        $this->assertStringContainsString('A guideline with its file', $html);
        $this->assertStringContainsString('class="k-pcard__dl"', $html);
    }

    public function test_the_slots_are_optional_and_the_fallbacks_render(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        // The page renders with or without the other streams' partials: the
        // social feed when it exists, otherwise the channel cards in one row.
        $this->assertStringContainsString('class="k-home"', $html);
        if (\Illuminate\Support\Facades\View::exists('partials.home.social')) {
            $this->assertStringContainsString('id="latest-from-crasa"', $html);
        } else {
            $this->assertStringContainsString('class="k-sbtns"', $html);
        }
        $this->assertStringContainsString('class="k-dir"', $html);

        $view = file_get_contents(resource_path('views/pages/home.blade.php'));
        $this->assertStringContainsString("@includeIf('partials.home.upcoming')", $view);
        // The comparison console left the home on 30 October (client review).
        $this->assertStringNotContainsString('partials.home.comparator', $view);
        $this->assertStringContainsString("View::exists('partials.home.social')", $view);
        $this->assertStringContainsString('<x-region-map', $view);
    }

    public function test_the_news_placeholder_is_pale_green(): void
    {
        $image = imagecreatefrompng(public_path('img/newsroom/placeholder-news.png'));
        $rgb = imagecolorat($image, 40, 40);

        $this->assertSame([0xEA, 0xF4, 0xEF], [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF]);
    }

    public function test_motion_is_short_and_off_under_reduced_motion(): void
    {
        $js = file_get_contents(public_path('js/motion.js'));

        $this->assertStringContainsString('IntersectionObserver', $js);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $js);
        $this->assertStringContainsString('i * 60', $js);
        $this->assertStringContainsString('duration = 400', $js);
        $this->assertStringContainsString('counter.unobserve', $js);
    }

    public function test_the_glance_figures_link_to_what_they_count(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<a class="k-fig u-reveal"\s+href="http://localhost/en/members"\s*>\s*<span class="k-fig__n"\s+data-count="14"~', $html);
    }

    public function test_the_members_paragraph_is_printed_once_beside_the_map_and_fourteen_cards(): void
    {
        foreach (\App\Models\Country::all() as $country) {
            \App\Models\Authority::factory()->create(['country_id' => $country->id]);
        }

        $html = $this->get('/en')->assertOk()->getContent();
        $section = substr($html, strpos($html, 'class="k-sec k-memsec"'));
        $section = substr($section, 0, strpos($section, '</section>'));

        $intro = \App\Models\PageBlock::where('block_type', 'member_grid')->whereHas('page', fn ($q) => $q->where('slug', 'home'))->firstOrFail()->translate('intro', 'en')?->value;
        // Item 8f: no explanatory sentence under the heading; the map and
        // the fourteen cards say it.
        if ($intro) {
            $this->assertSame(0, substr_count($html, e($intro)));
        }
        $this->assertSame(14, substr_count($section, 'class="k-dcard u-reveal"'));
        $dir = substr($section, strpos($section, 'class="k-dir"'));
        $this->assertSame(14, preg_match_all('~href="http://localhost/en/members/[a-z]{2}"~i', $dir));
        $this->assertStringContainsString('<svg', $section);
    }

    public function test_the_hero_sits_below_the_header(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, '<h1'), strpos($html, '</header>'));
    }
}
