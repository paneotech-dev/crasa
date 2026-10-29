<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Publication;
use App\Support\Locales;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PublicationsLibraryTest extends TestCase
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
    }

    public function test_the_library_renders_in_every_locale(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $this->get("/{$locale}/publications")->assertOk();
        }
    }

    public function test_every_seeded_publication_is_flagged_as_sample(): void
    {
        $this->assertSame(10, Publication::count());
        $this->assertSame(10, Publication::sample()->count());
    }

    /* Faceting -------------------------------------------------------------- */

    public function test_the_type_facet_narrows_the_listing(): void
    {
        $html = $this->get('/en/publications?type=annual_report')->getContent();

        $this->assertStringContainsString('Annual report', $html);
        $this->assertStringNotContainsString('roaming guidelines, first edition', $html);
        // Screen 07 line 206: "12 of 35 documents match your filters".
        $this->assertStringContainsString('<strong>1</strong> of '.Publication::listed()->count().' documents', $html);
    }

    public function test_the_year_facet_narrows_the_listing(): void
    {
        // Asserted on titles, not on type labels: every type label appears
        // in the facet chips regardless of what the filter returns.
        $this->get('/en/publications?year=2016')
            ->assertSee('Postal service and access guidelines')
            ->assertDontSee('Universal access and service funds');
    }

    public function test_the_country_facet_narrows_the_listing(): void
    {
        $withCountry = Publication::whereNotNull('country_id')->with('country')->first();

        $this->get('/en/publications?country='.$withCountry->country->iso_code)
            ->assertOk()
            ->assertSee($withCountry->translated('title', $withCountry->slug));
    }

    public function test_the_committee_facet_narrows_the_listing(): void
    {
        $this->get('/en/publications?committee=nonexistent-committee')
            ->assertOk()
            ->assertSee('No publications match these filters');
    }

    public function test_facets_combine(): void
    {
        $this->get('/en/publications?type=guideline&year=2019')
            ->assertOk()
            ->assertSee('first edition')
            ->assertDontSee('third edition');
    }

    public function test_an_empty_result_invites_an_action(): void
    {
        $this->get('/en/publications?year=1990')
            ->assertSee('No publications match these filters')
            ->assertSee('Clear the filters');
    }

    /* Supersession ---------------------------------------------------------- */

    public function test_the_lineage_runs_in_both_directions(): void
    {
        $middle = Publication::where('slug', 'sadc-roaming-guidelines-2022')->sole();
        $years = $middle->lineage()->map(fn ($p) => $p->published_on->format('Y'))->all();

        $this->assertSame(['2019', '2022', '2025'], $years);
    }

    public function test_a_superseded_document_tells_the_reader_before_they_read_it(): void
    {
        $html = $this->get('/en/publications/sadc-roaming-guidelines-2019')->getContent();

        $this->assertStringContainsString('A later version of this document exists', $html);
        $this->assertStringContainsString('second edition', $html);

        // The warning comes before the body, not after it.
        $this->assertLessThan(
            strpos($html, '<h1'),
            strpos($html, 'A later version of this document exists')
        );
    }

    public function test_the_current_edition_carries_no_superseded_warning(): void
    {
        $this->get('/en/publications/sadc-roaming-guidelines-2025')
            ->assertOk()
            ->assertDontSee('A later version of this document exists');
    }

    public function test_a_superseded_document_is_never_a_dead_end(): void
    {
        $html = $this->get('/en/publications/sadc-roaming-guidelines-2019')->getContent();

        // Both the successor and the lineage are reachable from the page.
        $this->assertStringContainsString('sadc-roaming-guidelines-2022', $html);
        $this->assertStringContainsString('Version lineage', $html);
    }

    public function test_the_listing_marks_superseded_documents(): void
    {
        $this->get('/en/publications?type=guideline')->assertSee('Superseded');
    }

    /* Slugs ------------------------------------------------------------------ */

    public function test_a_publication_slug_is_permanent(): void
    {
        $publication = Publication::first();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('citation permalink');

        $publication->update(['slug' => 'renamed']);
    }

    /* Documents and thumbnails ----------------------------------------------- */

    public function test_every_publication_has_a_document_served_through_the_controller(): void
    {
        foreach (Publication::with('documents')->get() as $publication) {
            $document = $publication->primaryDocument();
            $this->assertNotNull($document, $publication->slug.' has no document.');
        }

        $html = $this->get('/en/publications')->getContent();

        // Never a filesystem path, always a controller route.
        $this->assertStringContainsString('/documents/', $html);
        $this->assertStringNotContainsString('storage/documents/', $html);
    }

    public function test_thumbnails_are_generated_through_the_stage_two_pipeline(): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick is not available.');
        }

        $this->artisan('documents:thumbnails', ['--sync' => true])->assertSuccessful();

        $this->assertSame(0, Document::whereNull('thumbnail_path')->where('mime_type', 'application/pdf')->count());
    }

    public function test_a_document_without_a_thumbnail_falls_back_to_a_file_type_mark(): void
    {
        Document::query()->update(['thumbnail_path' => null]);

        $html = $this->get('/en/publications')->getContent();

        $this->assertStringContainsString('class="cp-placeholder"', $html);
        $this->assertStringNotContainsString('src="" ', $html);
    }

    /* Download counts --------------------------------------------------------- */

    public function test_a_download_increments_the_count(): void
    {
        $publication = Publication::with('documents')->first();
        $document = $publication->primaryDocument();

        $this->assertSame(0, $publication->downloadCount());

        $this->get('/en/documents/'.$document->id.'/download')->assertOk();

        $this->assertSame(1, $publication->fresh()->downloadCount());
    }

    /* Listing state ----------------------------------------------------------- */

    public function test_an_unlisted_publication_is_neither_listed_nor_reachable(): void
    {
        $hidden = Publication::factory()->unlisted()->create(['slug' => 'not-listed']);
        $hidden->setTranslations('en', ['title' => 'Internal only']);

        $this->get('/en/publications')->assertDontSee('Internal only');
        $this->get('/en/publications/not-listed')->assertNotFound();
    }

    public function test_a_title_missing_a_language_falls_back_with_a_notice(): void
    {
        // The 2025 edition has an English title only.
        $this->get('/pt/publications/sadc-roaming-guidelines-2025')
            ->assertOk()
            ->assertSee('third edition')
            ->assertSee('ainda não está disponível em português');
    }

    public function test_the_download_promise_from_the_screens_is_kept(): void
    {
        $this->get('/en/publications/annual-report-2024')
            ->assertSee('No redirect to Google Drive or SharePoint anywhere.');
    }
}
