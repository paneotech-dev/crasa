<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Publication;
use App\Models\Sector;
use App\Support\SectionCounts;
use Database\Seeders\CountrySeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The reader always knows how much is there.
 *
 * A count is only worth printing if it is computed, if it is a link to what
 * it counts, and if it tells the truth about what a click would return.
 * These hold all three.
 */
class CountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);
    }

    /**
     * A facet count states what a reader would get if they clicked, not how
     * many exist in total. "South Africa (7)" that yields two once the year
     * filter is applied is worse than no number at all.
     */
    public function test_a_facet_count_is_computed_with_the_other_filters_applied(): void
    {
        $year = Publication::listed()->orderByDesc('published_on')->first()->published_on->format('Y');

        $unfiltered = $this->countsFrom($this->get('/en/publications')->getContent());
        $filtered = $this->countsFrom($this->get('/en/publications?year='.$year)->getContent());

        // The type counts must narrow once a year is chosen, or they are
        // counting the whole library and lying about the click.
        $this->assertLessThanOrEqual(
            array_sum($unfiltered),
            array_sum($filtered),
            'Facet counts did not narrow when another filter was applied.'
        );
    }

    /**
     * An option that would return nothing keeps its place and is shown as
     * unavailable. A reader who cannot see that Comoros has no publications
     * cannot tell whether it has none or whether the filter is broken, and
     * the shape of the data is part of the answer.
     */
    public function test_an_option_with_no_results_is_disabled_rather_than_hidden(): void
    {
        $html = $this->get('/en/publications')->assertOk()->getContent();

        $this->assertStringContainsString('c-chip is-empty', $html);

        // Present, and not a link: there is no disabled state for an
        // anchor, and aria-disabled on something still focusable is a
        // promise the browser does not keep.
        preg_match('~<span role="listitem" class="c-chip is-empty">\s*([^<]+)~', $html, $match);
        $this->assertNotEmpty($match, 'No unavailable option is rendered at all.');
        $this->assertStringNotContainsString('is-empty" href', $html);
    }

    public function test_every_facet_option_carries_its_count(): void
    {
        foreach (['/en/publications', '/en/newsroom', '/en/search?q=roaming'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $chips = preg_match_all('~class="c-chip[^"]*"[^>]*>\s*[^<]+<span class="c-chip__count">~', $html);
            $this->assertGreaterThan(0, $chips, $url.' has facet options with no counts.');
        }
    }

    /**
     * A member state card says how much that state has on the platform, so a
     * reader scanning fourteen of them sees which have filed and which have
     * not without opening any.
     */
    public function test_member_cards_state_what_each_member_state_has(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        $this->assertStringContainsString('b-member__tally', $html);
        $this->assertSame(
            Country::count(),
            substr_count($html, 'b-member__tally'),
            'Not every member card carries its tally.'
        );
    }

    /**
     * Zero is information. A member state with no instruments has filed
     * none, and hiding that turns a fact about the data into a gap in the
     * interface.
     */
    public function test_a_member_state_with_nothing_filed_says_zero(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        $empty = Country::whereDoesntHave('authorities')->first() ?? Country::first();
        $tally = SectionCounts::forCountry($empty);

        $this->assertContains(0, $tally, 'Expected at least one zero in a seeded member state tally.');
        $this->assertMatchesRegularExpression('~b-member__tally.*?<dd>0</dd>~s', $html);
    }

    public function test_every_count_is_a_query_and_not_a_number_written_down(): void
    {
        $before = SectionCounts::publications()[0]['value'];

        Publication::factory()->create(['is_public' => true]);

        $this->assertNotSame(
            $before,
            SectionCounts::publications()[0]['value'],
            'The publications count did not move when a publication was added.'
        );
    }

    /**
     * The facet component is shared. Three hand-written copies of the same
     * chip list is how one of them ends up with the count and the other two
     * without, which is exactly what had happened.
     */
    public function test_all_three_filtered_surfaces_use_the_same_facet_component(): void
    {
        foreach ([
            'resources/views/pages/publications/index.blade.php',
            'resources/views/pages/newsroom/index.blade.php',
            'resources/views/pages/search/index.blade.php',
        ] as $view) {
            $this->assertStringContainsString(
                '<x-facet',
                file_get_contents(base_path($view)),
                basename(dirname($view)).' writes its own facet markup instead of using the shared component.'
            );
        }
    }

    /**
     * Screen 07 draws five facets: Sector, Instrument type, Year, Owning
     * committee, Available in. The build had four and neither Sector nor
     * Available in was among them.
     */
    public function test_the_library_carries_every_facet_screen_07_draws(): void
    {
        $html = $this->get('/en/publications')->assertOk()->getContent();

        foreach (['sector', 'type', 'year', 'committee', 'language'] as $facet) {
            $this->assertStringContainsString('facet-'.$facet, $html, 'Screen 07 draws a '.$facet.' facet and it is not built.');
        }
    }

    /**
     * "Available in" reads the attached document's language, not the
     * record's translations. The title may be translated while the PDF is
     * not, and it is the PDF the reader came for.
     */
    public function test_the_language_facet_filters_on_the_document_not_the_title(): void
    {
        $publication = Publication::listed()->whereHas('documents')->first();
        $this->assertNotNull($publication, 'No seeded publication has a document to filter on.');

        $locale = $publication->documents->first()->locale;

        $this->get('/en/publications?language='.$locale)
            ->assertOk()
            ->assertSee($publication->translated('title', $publication->slug));

        // A language nothing is filed in returns nothing rather than
        // everything, which is the failure mode of a filter that silently
        // does not apply.
        $empty = $this->get('/en/publications?language=zz')->assertOk()->getContent();
        $this->assertSame(0, substr_count($empty, 'class="b-row"'));
    }

    public function test_the_sector_facet_narrows_the_library(): void
    {
        $sector = Sector::whereIn(
            'id',
            Publication::listed()->whereNotNull('sector_id')->pluck('sector_id')
        )->first();

        $this->assertNotNull($sector, 'No seeded publication carries a sector.');

        $all = $this->get('/en/publications')->assertOk()->getContent();
        $filtered = $this->get('/en/publications?sector='.$sector->slug)->assertOk()->getContent();

        $this->assertNotSame(
            substr_count($all, 'class="b-row"'),
            substr_count($filtered, 'class="b-row"'),
            'Filtering by sector returned the same number of rows as no filter.'
        );
    }

    /**
     * Screen 07's document detail. A regulator who works in French needs to
     * know before downloading whether the document exists in French, and a
     * language that is missing is stated rather than left to be inferred
     * from a list of one.
     */
    public function test_a_document_page_states_every_language_including_the_missing_ones(): void
    {
        $publication = Publication::listed()->whereHas('documents')->firstOrFail();

        $html = $this->get('/en/publications/'.$publication->slug)->assertOk()->getContent();

        $this->assertStringContainsString('Available in', $html);

        foreach (['English', 'French', 'Portuguese'] as $language) {
            $this->assertStringContainsString($language, $html, $language.' is not accounted for.');
        }

        // The ones it is not in say so.
        $this->assertStringContainsString('Not available', $html);
    }

    /**
     * A document that will be quoted in a ministerial brief needs a
     * citation, and a reader assembling one by hand assembles it
     * differently every time.
     */
    public function test_a_document_page_offers_a_citation_with_a_permanent_url(): void
    {
        $publication = Publication::listed()->firstOrFail();

        $this->get('/en/publications/'.$publication->slug)
            ->assertOk()
            ->assertSee('Cite this document')
            // The slug is permanent, which is the whole point of citing it.
            ->assertSee('/en/publications/'.$publication->slug, false);
    }

    /** @return array<int, int> */
    private function countsFrom(string $html): array
    {
        preg_match_all('~c-chip__count">(\d+)<~', $html, $matches);

        return array_map('intval', $matches[1]);
    }
}
