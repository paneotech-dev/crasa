<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Country;
use App\Models\Document;
use App\Models\Instrument;
use App\Models\NewsItem;
use App\Models\Publication;
use App\Models\User;
use App\Search\SearchDriver;
use App\Support\Locales;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * WS-04.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);
    }

    private function driver(): SearchDriver
    {
        return app(SearchDriver::class);
    }

    /* The seam ------------------------------------------------------------- */

    /**
     * ElasticSearch cannot run on this host, so the driver interface is what
     * makes a managed service later a config change rather than a rewrite.
     */
    public function test_the_driver_is_resolved_through_the_interface(): void
    {
        $this->assertInstanceOf(SearchDriver::class, $this->driver());
    }

    public function test_nothing_above_the_driver_depends_on_the_engine(): void
    {
        foreach ([
            app_path('Http/Controllers/SearchController.php'),
            resource_path('views/pages/search/index.blade.php'),
        ] as $file) {
            $contents = file_get_contents($file);

            $this->assertStringNotContainsString('DatabaseSearchDriver', $contents, basename($file));
            $this->assertStringNotContainsString('MATCH(', $contents, basename($file));
            $this->assertStringNotContainsString('FULLTEXT', $contents, basename($file));
        }
    }

    /* Finding things -------------------------------------------------------- */

    public function test_it_searches_across_publications_news_and_members(): void
    {
        $types = $this->driver()->search('roaming')->hits->pluck('type')->unique();
        $this->assertContains('publication', $types->all());

        $this->assertGreaterThan(0, $this->driver()->search('Botswana')->total);
        $this->assertGreaterThan(0, $this->driver()->search('spectrum')->total);
    }

    public function test_a_member_state_is_findable_by_its_authority_acronym(): void
    {
        $this->seed(AuthoritySeeder::class);

        $hits = $this->driver()->search('BOCRA')->hits;

        $this->assertTrue($hits->contains(fn ($h) => $h->type === 'member' && $h->title === 'Botswana'));
    }

    public function test_results_carry_a_snippet_with_the_term_marked(): void
    {
        $hit = $this->driver()->search('roaming')->hits
            ->first(fn ($h) => str_contains($h->snippet, '<mark>'));

        $this->assertNotNull($hit, 'No result highlighted the searched term.');
    }

    /**
     * A title is user-supplied text. Escaping happens before the marks go in,
     * or a publication title becomes an injection point.
     */
    public function test_a_snippet_cannot_carry_markup_from_a_record(): void
    {
        $p = Publication::factory()->create(['slug' => 'xss-probe']);
        $p->setTranslations('en', [
            'title' => 'Probe',
            'summary' => 'A <script>alert(1)</script> roaming note.',
        ]);

        $hit = $this->driver()->search('roaming')->hits->first(fn ($h) => $h->title === 'Probe');

        $this->assertNotNull($hit);
        // Tags are stripped, then the remainder escaped, then marks added.
        $this->assertStringNotContainsString('<script>', $hit->snippet);
        $this->assertStringNotContainsString('</script>', $hit->snippet);
        // Only the highlighting marks survive as markup.
        $this->assertSame(
            substr_count($hit->snippet, '<'),
            substr_count($hit->snippet, '<mark>') + substr_count($hit->snippet, '</mark>')
        );
    }

    public function test_suggestions_come_back_for_a_partial_term(): void
    {
        $this->assertNotEmpty($this->driver()->suggest('roam'));
        // Too short to be useful, and too broad to be cheap.
        $this->assertSame([], $this->driver()->suggest('r'));
    }

    /* Facets ---------------------------------------------------------------- */

    public function test_facets_are_counted_from_the_results_themselves(): void
    {
        $facets = $this->driver()->search('guidelines')->facets;

        $this->assertArrayHasKey('type', $facets);
        $this->assertArrayHasKey('year', $facets);
        // A facet never offers a filter that would return nothing.
        foreach ($facets['type'] as $count) {
            $this->assertGreaterThan(0, $count);
        }
    }

    public function test_a_filter_narrows_the_results(): void
    {
        $all = $this->driver()->search('guidelines')->total;
        $narrowed = $this->driver()->search('guidelines', ['type' => 'publication'])->total;

        $this->assertLessThanOrEqual($all, $narrowed);
        $this->assertGreaterThan(0, $narrowed);
    }

    /* SECURITY — the classic hole ------------------------------------------- */

    /**
     * Search is the classic hole in an otherwise sound authorisation layer:
     * the records are reachable by a different path, no policy is consulted
     * because nothing is being "viewed" yet, and a snippet leaks the text
     * even where the link would 403.
     */
    public function test_unpublished_and_unlisted_records_never_appear(): void
    {
        $hidden = Publication::factory()->unlisted()->create(['slug' => 'internal-only']);
        $hidden->setTranslations('en', ['title' => 'Confidential roaming annex', 'summary' => 'roaming']);

        $draft = NewsItem::factory()->draft()->create(['slug' => 'draft-item']);
        $draft->setTranslations('en', ['title' => 'Unpublished roaming story', 'summary' => 'roaming']);

        $titles = $this->driver()->search('roaming')->hits->pluck('title');

        $this->assertNotContains('Confidential roaming annex', $titles->all());
        $this->assertNotContains('Unpublished roaming story', $titles->all());
    }

    /**
     * The country scope applies to search exactly as it does everywhere
     * else. A representative must not find another member state's restricted
     * document, and must not see its text in a snippet either.
     */
    public function test_a_representative_cannot_find_another_countrys_restricted_document(): void
    {
        $namibia = Country::where('iso_code', 'na')->sole();
        $zambia = Country::where('iso_code', 'zm')->sole();

        $zambianInstrument = Instrument::factory()->inForce()->create([
            'country_id' => $zambia->id,
            'slug' => 'zm-restricted-roaming',
        ]);
        $zambianInstrument->setTranslations('en', [
            'title' => 'Zambia restricted roaming determination',
            'summary' => 'roaming determination',
        ]);

        $document = Document::factory()->create([
            'documentable_type' => Instrument::class,
            'documentable_id' => $zambianInstrument->id,
            'clearance' => Clearance::Member,
            'file_path' => 'documents/zm-restricted.pdf',
        ]);
        Storage::disk('local')->put($document->file_path, '%PDF-1.4 restricted');

        $rep = User::factory()->memberStateRep($namibia)->create();

        // The document itself is refused, which the policy already holds.
        $this->actingAs($rep)
            ->get('/en/documents/'.$document->id)
            ->assertForbidden();

        // And search must not hand over its text by another route.
        $this->actingAs($rep);
        $hits = $this->driver()->search('roaming')->hits;

        foreach ($hits as $hit) {
            $this->assertStringNotContainsString(
                'restricted',
                strtolower($hit->snippet),
                'A restricted document leaked through search: '.$hit->title
            );
        }
    }

    public function test_search_never_links_straight_to_a_file(): void
    {
        foreach ($this->driver()->search('roaming')->hits as $hit) {
            $this->assertStringNotContainsString('/storage/documents/', $hit->url);
            $this->assertStringNotContainsString('app/private', $hit->url);
        }
    }

    /* The page --------------------------------------------------------------- */

    public function test_the_search_page_renders_in_every_locale(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get("/{$locale}/search")->assertOk();
            $this->get("/{$locale}/search?q=roaming")->assertOk();
        }
    }

    /** Empty query, no results and a single result all need designed states. */
    public function test_an_empty_query_invites_a_search_rather_than_showing_nothing(): void
    {
        $this->get('/en/search')
            ->assertOk()
            ->assertSee('What are you looking for?')
            ->assertSee('Two characters is enough to start.');
    }

    public function test_no_results_says_so_and_offers_a_way_on(): void
    {
        $this->get('/en/search?q=zzzzzznothing')
            ->assertOk()
            ->assertSee('Nothing matches')
            ->assertSee('Start a new search');
    }

    public function test_a_single_result_reads_as_one_not_as_a_count(): void
    {
        $html = $this->get('/en/search?q=Comoros')->getContent();

        if (substr_count($html, 'class="b-row"') === 1) {
            $this->assertStringContainsString('One result', $html);
        }

        $this->assertStringNotContainsString('1 results', $html);
    }

    public function test_the_suggest_endpoint_returns_json(): void
    {
        $this->get('/en/search/suggest?q=roam')
            ->assertOk()
            ->assertJsonStructure(['suggestions']);
    }

    public function test_the_results_reuse_the_document_row_rather_than_a_new_pattern(): void
    {
        $html = $this->get('/en/search?q=roaming')->getContent();

        $this->assertStringContainsString('b-rows', $html);
        $this->assertStringContainsString('b-row__title', $html);
        $this->assertStringContainsString('c-chip', $html);
    }
}
