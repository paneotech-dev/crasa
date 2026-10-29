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

    /**
     * The hole this closed.
     *
     * suggest() used to read the translations table directly and matched
     * every title row of every publication, published or not. A reader
     * could learn the title of an unlisted country publication by typing
     * three letters; the link would then correctly refuse them, which tells
     * them the document exists, which is the part that matters.
     *
     * Autocomplete is where an otherwise sound authorisation layer leaks,
     * because nothing is being "viewed" yet and no policy runs.
     */
    public function test_an_unlisted_publication_is_never_suggested(): void
    {
        $publication = Publication::factory()->create([
            'slug' => 'restricted-tariff-annex',
            'is_public' => false,
            'country_id' => Country::where('iso_code', 'bw')->value('id'),
        ]);
        $publication->translations()->create([
            'locale' => 'en',
            'field' => 'title',
            'value' => 'Confidential wholesale tariff annex',
        ]);

        $suggestions = $this->driver()->suggest('wholesale tariff');

        $this->assertNotContains('Confidential wholesale tariff annex', $suggestions);

        // And the same term is not findable through the results either, so
        // the two paths agree rather than one of them being the loose one.
        $this->assertSame(
            [],
            $this->driver()->search('wholesale tariff annex')->hits->pluck('title')->all()
        );
    }

    public function test_an_unpublished_news_item_is_never_suggested(): void
    {
        $item = NewsItem::factory()->create(['slug' => 'embargoed-note', 'published_at' => null]);
        $item->translations()->create([
            'locale' => 'en',
            'field' => 'title',
            'value' => 'Embargoed statement on numbering',
        ]);

        $this->assertNotContains(
            'Embargoed statement on numbering',
            $this->driver()->suggest('embargoed statement')
        );
    }

    public function test_a_suggestion_only_ever_names_something_the_reader_can_open(): void
    {
        // Whatever comes back for a broad term, every one of it must be
        // reachable as a result. If the two lists can diverge, the narrower
        // one is not the one protecting anything.
        foreach ($this->driver()->suggest('spectrum') as $suggestion) {
            $this->assertNotEmpty(
                $this->driver()->search($suggestion)->hits,
                'Suggested "'.$suggestion.'" but searching for it finds nothing the reader may see.'
            );
        }
    }

    public function test_the_suggest_endpoint_announces_how_many_it_found(): void
    {
        // Pluralised on the server. A dropdown that only appears is a
        // dropdown a screen reader user is never told about, and
        // pluralising in JavaScript in three languages is how a French
        // plural bug reaches a regulator.
        $this->get('/en/search/suggest?q=roam')
            ->assertOk()
            ->assertJsonStructure(['suggestions', 'announce']);

        $this->get('/en/search/suggest?q=qqzzxx')
            ->assertOk()
            ->assertJson(['suggestions' => [], 'announce' => 'No suggestions.']);
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

        /*
         * And autocomplete is held to the same line, as this reader.
         *
         * Note what is and is not restricted here. The Zambian instrument is
         * in force, so its title is public and appears in this
         * representative's results; the Member-clearance file attached to it
         * is what they are refused. So the test is not "this title never
         * appears" — it is that autocomplete never offers a title the same
         * reader could not have reached by searching. Autocomplete is the
         * usual place that invariant breaks, because nothing is being
         * "viewed" yet and no policy runs.
         */
        foreach ($this->driver()->suggest('roaming') as $suggestion) {
            $this->assertNotEmpty(
                $this->driver()->search($suggestion)->hits,
                'Autocomplete offered "'.$suggestion.'" to a reader whose search cannot reach it.'
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

    public function test_the_results_are_cards_grouped_by_kind_with_the_query_marked(): void
    {
        $html = $this->get('/en/search?q=roaming')->getContent();

        // UI system (29 October 2026): cards grouped by kind replace the
        // document rows; the facet chips stay.
        $this->assertStringContainsString('cp-search__group', $html);
        $this->assertStringContainsString('class="u-card cp-result"', $html);
        $this->assertMatchesRegularExpression('~<a href="[^"]+">[^<]*<mark>roaming</mark>~i', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('c-chip', $html);
    }

    public function test_equal_relevance_puts_the_newer_edition_first(): void
    {
        $old = Publication::factory()->create(['slug' => 'zz-older-edition', 'published_on' => '2015-01-01']);
        $old->setTranslations('en', ['title' => 'Zebracrossing policy', 'summary' => 'Zebracrossing']);
        $new = Publication::factory()->create(['slug' => 'zz-newer-edition', 'published_on' => '2021-01-01']);
        $new->setTranslations('en', ['title' => 'Zebracrossing guidelines', 'summary' => 'Zebracrossing']);

        $slugs = collect($this->driver()->search('zebracrossing')->hits)
            ->map(fn ($hit) => basename($hit->url))
            ->values()
            ->all();

        $this->assertSame(['zz-newer-edition', 'zz-older-edition'], array_slice($slugs, 0, 2));
    }

    public function test_a_title_match_outranks_a_mention_in_the_body(): void
    {
        $about = Publication::factory()->create(['slug' => 'zz-about-quokkas', 'published_on' => '2015-01-01']);
        $about->setTranslations('en', ['title' => 'Quokka guidelines', 'summary' => 'Guidance.']);
        $mention = NewsItem::factory()->create(['slug' => 'zz-mentions-quokkas', 'status' => 'published', 'published_at' => '2024-01-01']);
        $mention->setTranslations('en', ['title' => 'Spectrum plan', 'body' => 'It also touches on quokka matters.']);

        $slugs = collect($this->driver()->search('quokka')->hits)->map(fn ($hit) => basename($hit->url))->values()->all();

        $this->assertSame('zz-about-quokkas', $slugs[0] ?? null);
    }
}
