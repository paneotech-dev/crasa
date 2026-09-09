<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Instrument;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Publication;
use App\Models\Sector;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DemonstrationSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\InstrumentSeeder;
use Database\Seeders\LegalPagesSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The strip-before-go-live action, and the demonstration dataset it removes.
 *
 * CLAUDE.md requires demonstration data to be "strippable in one admin
 * action before go-live". The instruction for this run was to test that
 * rather than assume it, and there are three separate things to prove:
 *
 *   It removes everything flagged, not most of it.
 *   It leaves the platform working, not merely standing.
 *   It leaves the reference data alone, or fourteen member states become
 *   orphans.
 *
 * The middle one is the one that would actually bite. A strip that empties
 * the database and takes the site down with it is discovered at go-live, by
 * which time it is a Saturday.
 */
class SampleDataStripTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The disks are deliberately not faked here.
     *
     * Faking them made this class delete the seeded fixture files that the
     * publications tests rely on, and those tests then failed only when run
     * after this one. The strip is exercised against the real seeded state,
     * which is also what it will meet at go-live; the one test that needs a
     * throwaway file fakes the disk itself.
     */
    private function fullDataset(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        // A fully populated platform has its social accounts configured;
        // without them the homepage's "Follow us" section correctly renders
        // its empty state, which is what this test forbids.
        $this->seed(SiteSettingSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(AuthoritySeeder::class);
        $this->seed(IndicatorSeeder::class);
        $this->seed(IndicatorValueSeeder::class);
        $this->seed(InstrumentSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(LegalPagesSeeder::class);
        $this->seed(HomepageSeeder::class);

        /*
         * The four representatives the demonstration submissions attach to.
         *
         * Created here rather than by the seeder, because no seeder on this
         * platform creates an account: a seeded credential is one that
         * exists on a live public URL without anyone having decided it
         * should. In production these come from `php artisan user:create`,
         * which prompts for the password.
         */
        foreach (['na', 'zm', 'bw', 'mw'] as $code) {
            User::factory()
                ->memberStateRep(Country::where('iso_code', $code)->sole())
                ->create();
        }

        $this->seed(DemonstrationSeeder::class);
    }

    /* The dataset the workshop needs ----------------------------------------- */

    public function test_the_demonstration_dataset_has_a_supersession_chain_three_deep(): void
    {
        $this->fullDataset();

        $first = Instrument::where('slug', 'bw-interconnection-2009')->sole();
        $second = Instrument::find($first->superseded_by_id);
        $third = Instrument::find($second->superseded_by_id);

        // Three, because a chain of two shows a line. Three is the shortest
        // length at which the middle of a chain looks different from an end.
        $this->assertNotNull($second);
        $this->assertNotNull($third);
        $this->assertNull($third->superseded_by_id);
        $this->assertSame('bw-interconnection-2023', $third->slug);
    }

    public function test_the_gaps_are_deliberate_so_the_comparator_can_demonstrate_them(): void
    {
        $this->fullDataset();

        $comoros = Country::where('iso_code', 'km')->sole();

        // A comparator showing fourteen complete rows demonstrates nothing
        // about the behaviour a regulator will ask about first.
        $this->assertSame(0, IndicatorValue::where('country_id', $comoros->id)->count());
        $this->assertGreaterThan(0, IndicatorValue::count());

        $this->get('/en/comparator?'.http_build_query([
            'mode' => 'compare',
            'category' => Indicator::first()->category,
            'countries' => 'bw,km',
        ]))->assertOk()->assertSee(__('comparator.not_reported'));
    }

    public function test_the_lineage_spine_has_a_chain_to_draw(): void
    {
        $this->fullDataset();

        $latest = Publication::where('slug', 'sadc-roaming-guidelines-2024')->sole();

        // Two editions draw a line. Three is where the middle of a chain
        // starts to look different from either end, which is what a
        // regulator reads a lineage for.
        $this->assertSame(3, $latest->lineage()->count());

        $this->get('/en/publications/sadc-roaming-guidelines-2024')
            ->assertOk()
            ->assertSee(__('publications.lineage'))
            ->assertSee('2015')
            ->assertSee('2019');
    }

    public function test_there_is_a_submission_in_each_of_the_four_states(): void
    {
        $this->fullDataset();

        $states = Submission::withoutGlobalScopes()->pluck('status')->map->value->sort()->values()->all();

        $this->assertSame(['draft', 'returned', 'submitted', 'validated'], $states);

        // The returned one carries a field comment, which is the state a
        // member state most needs to understand and the only one that
        // demonstrates screen 06's comments.
        $returned = Submission::withoutGlobalScopes()->where('status', 'returned')->sole();
        $this->assertSame(1, $returned->comments()->count());
        $this->assertGreaterThan(0, $returned->events()->count());
    }

    public function test_no_page_shows_an_empty_state_unless_emptiness_is_the_point(): void
    {
        $this->fullDataset();

        foreach (['/en', '/en/publications', '/en/newsroom', '/en/members', '/en/committees', '/en/events'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString(
                'b__empty',
                $html,
                $path.' shows a block empty state with the demonstration dataset loaded.'
            );
        }
    }

    public function test_every_demonstration_row_is_flagged(): void
    {
        $this->fullDataset();

        // Anything unflagged survives the strip and ships as if it were
        // real, which is the failure this whole mechanism exists to stop.
        foreach ([Instrument::class, Publication::class, NewsItem::class, Meeting::class] as $class) {
            $unflagged = $class::withoutGlobalScopes()->where('is_sample', false)->count();

            $this->assertSame(0, $unflagged, class_basename($class).' has '.$unflagged.' unflagged rows.');
        }

        $this->assertSame(0, Submission::withoutGlobalScopes()->where('is_sample', false)->count());
        $this->assertSame(0, Folder::withoutGlobalScopes()->where('is_sample', false)->count());
    }

    /* The strip -------------------------------------------------------------- */

    /**
     * The guard from stage 8, restated here because this seeder is the one
     * that most wants to break it.
     *
     * A demonstration dataset needs an owner for every submission, and the
     * obvious shortcut is to create one. On this project a seeded
     * credential has already reached a circulated PDF.
     */
    public function test_the_demonstration_seeder_creates_no_accounts(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $before = User::count();

        $this->seed(DemonstrationSeeder::class);

        $this->assertSame($before, User::count());

        // And it skips the data rather than attaching it to nobody.
        $this->assertSame(0, Submission::withoutGlobalScopes()->count());
    }

    public function test_the_dry_run_changes_nothing(): void
    {
        $this->fullDataset();

        $before = IndicatorValue::count();

        $this->artisan('content:strip-samples --dry-run')->assertSuccessful();

        $this->assertSame($before, IndicatorValue::count());
    }

    public function test_one_action_removes_every_flagged_row(): void
    {
        $this->fullDataset();

        $this->artisan('content:strip-samples --force')->assertSuccessful();

        // Not most of it. A platform that keeps three demonstration figures
        // after a strip is worse than one that keeps all of them, because
        // nobody is looking any more.
        foreach ([
            Instrument::class, Publication::class, NewsItem::class, IndicatorValue::class,
            Meeting::class, Authority::class, Committee::class,
        ] as $class) {
            $left = $class::withoutGlobalScopes()->where('is_sample', true)->count();

            $this->assertSame(0, $left, class_basename($class).' still has '.$left.' demonstration rows.');
        }

        $this->assertSame(0, Submission::withoutGlobalScopes()->count());
        $this->assertSame(0, Folder::withoutGlobalScopes()->count());
    }

    /**
     * The site's structure is not demonstration data.
     *
     * A page and its blocks are what the Secretariat edits; they are not
     * something it receives fresh after go-live. An earlier version of the
     * strip command removed them because the seeders had flagged them, and
     * the first thing this test found was the homepage returning 404.
     */
    public function test_the_pages_and_their_blocks_survive_the_strip(): void
    {
        $this->fullDataset();

        $before = Page::count();

        $this->artisan('content:strip-samples --force')->assertSuccessful();

        $this->assertSame($before, Page::count());
        $this->assertGreaterThan(0, PageBlock::count());
        $this->assertNotNull(Page::where('slug', 'home')->first());
    }

    public function test_a_soft_deleted_demonstration_row_is_removed_too(): void
    {
        $this->fullDataset();

        $publication = Publication::first();
        $publication->delete();

        $this->artisan('content:strip-samples --force')->assertSuccessful();

        // A strip that leaves rows recoverable has not removed them.
        $this->assertSame(0, Publication::withTrashed()->count());
    }

    public function test_the_reference_data_survives(): void
    {
        $this->fullDataset();

        $this->artisan('content:strip-samples --force')->assertSuccessful();

        // Deleting the row for Namibia does not remove Namibia from SADC.
        // It orphans everything pointing at it.
        $this->assertSame(14, Country::count());
        $this->assertSame(3, Sector::count());
        $this->assertGreaterThan(0, Indicator::count());
    }

    /**
     * The one that would actually bite. A strip that empties the database
     * and takes the site down with it is discovered at go-live, on a
     * Saturday.
     */
    public function test_the_platform_still_works_after_the_strip(): void
    {
        $this->fullDataset();

        $this->artisan('content:strip-samples --force')->assertSuccessful();

        foreach ([
            '/en', '/en/about', '/en/members', '/en/committees',
            '/en/publications', '/en/newsroom', '/en/events', '/en/comparator',
            '/en/search', '/en/contact',
            '/fr', '/pt',
        ] as $path) {
            $this->get($path)->assertOk();
        }

        // The completeness dashboard is behind sign-in, so a redirect is
        // the right answer there. A 404 or a 500 would not be.
        $this->get('/en/completeness')->assertRedirect();
    }

    public function test_pages_that_lose_their_content_show_a_designed_empty_state(): void
    {
        $this->fullDataset();
        $this->artisan('content:strip-samples --force')->assertSuccessful();

        // Emptiness is a state this platform renders on purpose, which is
        // what makes stripping safe rather than merely survivable.
        $this->get('/en/publications')
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertDontSee('Exception');

        $this->get('/en/comparator')->assertOk()->assertSee(__('comparator.empty_title'));
    }

    public function test_the_files_behind_demonstration_documents_are_removed_too(): void
    {
        Storage::fake('local');

        $this->fullDataset();

        $document = Document::withoutGlobalScopes()->first();

        if ($document === null) {
            $this->markTestSkipped('No demonstration documents seeded.');
        }

        Storage::disk('local')->put($document->file_path, 'demonstration');

        $this->artisan('content:strip-samples --force')->assertSuccessful();

        // A stripped platform with a disk full of orphaned PDFs is not a
        // stripped platform.
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_running_it_twice_is_harmless(): void
    {
        $this->fullDataset();

        $this->artisan('content:strip-samples --force')->assertSuccessful();
        $this->artisan('content:strip-samples --force')->assertSuccessful();

        $this->get('/en')->assertOk();
    }

    /**
     * TRANSLATIONS ARE POLYMORPHIC, SO NOTHING CASCADES.
     *
     * Found by rehearsing the purge against the seeded database on 9
     * September 2026: it removed 196 flagged rows and 76 rows behind real
     * foreign keys, and left 337 translation rows addressing records that
     * no longer existed.
     *
     * Dead rows are the smaller half. A translation is addressed by
     * translatable_type and translatable_id, and ids are reused. An orphan
     * for publication 5 becomes the French title of the next publication
     * created as id 5 — a demonstration string under a real document, on a
     * platform whose whole claim is that its content is sourced.
     */
    public function test_deleting_a_record_takes_its_translations_with_it(): void
    {
        $publication = Publication::factory()->create(['is_sample' => true]);
        $publication->setTranslations('fr', ['title' => 'Titre de demonstration']);

        $this->assertSame(1, $publication->translations()->count());

        $publication->forceDelete();

        $this->assertSame(
            0,
            DB::table('translations')
                ->where('translatable_type', Publication::class)
                ->where('translatable_id', $publication->id)
                ->count(),
            'A deleted publication left its French title behind for the next record to inherit.'
        );
    }

    /**
     * A soft delete keeps them, because a soft-deleted record can be
     * restored and would come back mute.
     */
    public function test_a_soft_delete_keeps_the_translations(): void
    {
        $publication = Publication::factory()->create(['is_sample' => true]);
        $publication->setTranslations('fr', ['title' => 'Toujours la']);

        $publication->delete();

        $this->assertSame(1, DB::table('translations')
            ->where('translatable_type', Publication::class)
            ->where('translatable_id', $publication->id)
            ->count());
    }

    /** The purge sweeps what earlier deletions left behind. */
    public function test_the_purge_sweeps_translations_orphaned_before_the_trait_knew_to(): void
    {
        // An orphan of exactly the kind the rehearsal found: a row whose
        // record was deleted by something that did not clean up.
        DB::table('translations')->insert([
            'translatable_type' => Publication::class,
            'translatable_id' => 999_999,
            'locale' => 'fr',
            'field' => 'title',
            'value' => 'Orpheline',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('content:strip-samples --force')->assertSuccessful();

        $this->assertSame(0, DB::table('translations')->where('translatable_id', 999_999)->count());
    }

    /**
     * The purge runs the sweep even when nothing is flagged, because a
     * platform with no demonstration data can still be carrying orphans
     * from a reseed.
     */
    public function test_the_sweep_runs_even_when_there_is_nothing_flagged(): void
    {
        DB::table('translations')->insert([
            'translatable_type' => Publication::class,
            'translatable_id' => 888_888,
            'locale' => 'pt',
            'field' => 'title',
            'value' => 'Orfa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Nothing carries is_sample in this test's database.
        Publication::query()->forceDelete();

        $this->artisan('content:strip-samples --force')->assertSuccessful();

        $this->assertSame(0, DB::table('translations')->where('translatable_id', 888_888)->count());
    }

    /**
     * The other half of the rehearsal.
     *
     * Restoring the database brings a document's row back and does not
     * bring its file back, so every download 404s while every page looks
     * healthy. That is the failure docs/BACKUP_SETUP.md tells the reader to
     * check for by downloading a file after a restore, and the seeder could
     * not heal it because it returned early on finding the row.
     */
    public function test_the_seeder_repairs_a_document_row_whose_file_is_missing(): void
    {
        $this->seed(PublicationSeeder::class);

        $document = Document::whereNotNull('file_path')->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($document->file_path));

        // The state a database-only restore leaves behind.
        Storage::disk('local')->delete($document->file_path);
        $this->assertFalse(Storage::disk('local')->exists($document->file_path));

        $this->seed(PublicationSeeder::class);

        $this->assertTrue(
            Storage::disk('local')->exists($document->file_path),
            'A document row survived a restore without its file and the seeder could not repair it.'
        );
    }
}
