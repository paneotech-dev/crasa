<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Page;
use App\Support\ImageLibrary;
use App\Support\SectionCounts;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LegalPagesSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The band that opens each section, as screen 08 draws it.
 *
 * The pattern is CRASA's own: screen 08 puts an eyebrow, a descriptive
 * heading, a standfirst and a row of counts on a navy band at the head of
 * the membership page. Everything here holds the platform to that.
 */
class SectionBandTest extends TestCase
{
    use RefreshDatabase;

    /** Every public section that should open with a band. */
    private const SECTIONS = [
        '/en/publications',
        '/en/newsroom',
        '/en/search',
        '/en/events',
        '/en/members',
        '/en/committees',
        '/en/about',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);
        $this->seed(LegalPagesSeeder::class);
    }

    public function test_every_public_section_opens_with_a_band(): void
    {
        foreach (self::SECTIONS as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // The section band or, since the UI system (29 October 2026), the
            // short hero that replaces it on the rebuilt pages.
            $this->assertMatchesRegularExpression('~class="(c-band|u-hero[ "])~', $html, $url.' has no section band.');
            $this->assertMatchesRegularExpression('~c-band__title|class="u-h1"~', $html, $url.' has a band with no title.');
        }
    }

    /**
     * The band states how much is in the section. A reader should never
     * have to scroll a listing to find out how big it is.
     */
    public function test_the_band_states_how_much_is_in_the_section(): void
    {
        foreach (['/en/publications', '/en/newsroom', '/en/events', '/en/members', '/en/committees', '/en/about', '/en/search'] as $url) {
            $this->assertMatchesRegularExpression(
                '~c-band__counts|class="u-figures[ "]~',
                $this->get($url)->assertOk()->getContent(),
                $url.' opens with a band that says nothing about how much is in it.'
            );
        }
    }

    /**
     * Every count is a query. A hardcoded count is wrong the first time
     * anything is published and stays wrong silently, which on a platform
     * whose whole claim is that its figures are sourced is the one kind of
     * error that costs the most.
     */
    public function test_the_counts_are_computed_and_move_with_the_data(): void
    {
        $before = SectionCounts::members();

        Country::factory()->create();

        $after = SectionCounts::members();

        $this->assertNotSame(
            $before[0]['value'],
            $after[0]['value'],
            'The member count did not change when a member state was added, so it is not computed.'
        );
    }

    /**
     * Zero is information. A section that is empty and says nothing about
     * being empty reads as a section that failed to load.
     */
    public function test_a_count_of_zero_is_stated_rather_than_hidden(): void
    {
        $counts = SectionCounts::events();

        $this->assertNotEmpty($counts);
        foreach ($counts as $count) {
            $this->assertIsString($count['value']);
        }

        $this->get('/en/events')->assertOk()->assertSee('c-band__counts', false);
    }

    /** A count a reader cannot follow is a fact about the site, not a way in. */
    public function test_counts_link_to_what_they_count(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();
        $at = strpos($html, 'c-band__counts');
        $band = substr($html, $at !== false ? $at : (int) strpos($html, 'class="u-figures'), 1400);

        $this->assertStringContainsString('/en/committees', $band);
        $this->assertStringContainsString('/en/comparator', $band);
    }

    /**
     * The eyebrow names the area and the heading describes it, as screen 08
     * draws them. Printing the same words twice is what happens when the
     * page's navigation label is used for both.
     */
    public function test_the_eyebrow_never_repeats_the_heading(): void
    {
        foreach (self::SECTIONS as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            preg_match('~c-band__eyebrow">(.*?)</p>~s', $html, $eyebrow);
            preg_match('~c-band__title">(.*?)</h1>~s', $html, $title);

            if ($eyebrow === [] || $title === []) {
                continue;
            }

            $this->assertNotSame(
                trim($eyebrow[1]),
                trim($title[1]),
                $url.' prints the same words as its eyebrow and its heading.'
            );
        }
    }

    /**
     * THE IMAGE RULE.
     *
     * A photograph in a band is texture, not subject — it sits under a heavy
     * overlay and warms the ground. That does not make it exempt from being
     * about the section it opens. A broadcasting photograph on the postal
     * section is a lie about what the reader is looking at, and a technician
     * at a test bench does not illustrate a committee meeting.
     *
     * Today no section carries one, because the supplied library has five
     * images and none of them is about any of these eight sections. The
     * capability is built and measured; the library is the gap.
     */
    public function test_no_band_carries_a_photograph_that_is_not_about_its_section(): void
    {
        // Slot => the sections it would be truthful on. Empty means the
        // image is about nothing on this list.
        $truthful = [
            'sector-telecom' => [],
            'sector-postal' => [],
            'institutional' => [],
            'people' => [],
            'hero' => [],
        ];

        foreach (self::SECTIONS as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            if (! str_contains($html, 'c-band--image')) {
                continue;
            }

            foreach (array_keys($truthful) as $slot) {
                if (str_contains($html, ImageLibrary::derivativePath($slot, 1440, 'jpg'))) {
                    $this->assertContains(
                        $url,
                        $truthful[$slot],
                        'The band on '.$url.' carries the "'.$slot.'" photograph, which is not about that section.'
                    );
                }
            }
        }

        $this->assertTrue(true);
    }

    /**
     * A band without a photograph must not reserve space for one, or the
     * absence reads as a failed image rather than a decision.
     */
    public function test_a_band_with_no_photograph_renders_no_media_layer(): void
    {
        $html = $this->get('/en/publications')->assertOk()->getContent();

        $this->assertStringNotContainsString('c-band__media', $html);
        $this->assertStringNotContainsString('c-band__scrim', $html);
        $this->assertStringNotContainsString('c-band--image', $html);
    }

    /**
     * The overlay is a contract with the contrast test. If someone lightens
     * it, the measured floor in ContrastTest is measuring a value the
     * stylesheet no longer uses.
     */
    public function test_the_overlay_matches_the_value_the_contrast_test_measures(): void
    {
        $this->assertStringContainsString(
            'background: rgba(15, 27, 45, .82)',
            File::get(public_path('css/blocks.css')),
            'The band overlay changed. ContrastTest measures 0.82 and would now be measuring the wrong value.'
        );
    }

    /**
     * The comparator keeps screen 03's band, which carries its counts in the
     * standfirst and puts the demonstration notice where a counts row would
     * go. An approved screen is not overridden by a pattern from another
     * platform.
     */
    public function test_the_comparator_keeps_the_band_screen_03_draws(): void
    {
        $html = $this->get('/en/comparator')->assertOk()->getContent();

        $this->assertStringContainsString('c-cmp__masthead', $html);
        $this->assertStringNotContainsString('c-band__counts', $html);
    }

    public function test_a_composed_page_with_nothing_to_count_renders_no_counts_row(): void
    {
        $this->assertSame([], SectionCounts::forPage('privacy'));

        $this->get('/en/privacy')
            ->assertOk()
            ->assertDontSee('c-band__counts', false);
    }

    /**
     * Screen 01 draws select, choice, upload and search as one component
     * set. The select had been defined three times, with the same values
     * written out each time — on the map, on the homepage comparison, and
     * in the back office. Three definitions of one control is how the
     * fourth one ends up different.
     */
    public function test_the_select_control_is_defined_once(): void
    {
        $base = File::get(public_path('css/base.css'));
        $blocks = File::get(public_path('css/blocks.css'));

        $this->assertStringContainsString('.c-select {', $base, 'The select component is not in the foundations layer.');

        // No second definition of the control anywhere in the public sheets.
        $this->assertDoesNotMatchRegularExpression(
            '~\.[a-z-]*select \{[^}]*border-radius~',
            $blocks,
            'A select is being redefined instead of using .c-select.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~select \{[^}]*font-family~',
            $blocks,
            'A select is being redefined instead of using .c-select.'
        );
    }

    public function test_the_band_carries_its_copy_in_all_three_languages(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            $page = Page::where('slug', 'members')->sole();

            $this->assertNotNull(
                $page->translate('heading', $locale),
                'The members band has no '.$locale.' heading.'
            );
            $this->assertNotNull($page->translate('standfirst', $locale));
        }
    }
}
