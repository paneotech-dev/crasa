<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Country;
use App\Models\Document;
use App\Models\Folder;
use App\Models\IndicatorValue;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * No page a demonstration account opens is empty.
 *
 * In the presentation of 14 October 2026 the South Africa account opened an
 * empty submissions list, empty folders and an empty announcements page.
 */
class RehearsalContentTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(ReferenceDataSeeder::class);
        // The seeder creates no accounts: the representative exists first, as on the live site.
        $this->member = User::factory()->memberStateRep(Country::where('iso_code', 'za')->sole())->create();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_south_africa_has_returns_folders_with_papers_and_notices(): void
    {
        $this->actingAs($this->member);

        $this->assertSame(2, Submission::where('country_id', $this->member->country_id)->count());
        $this->get('/en/submissions')->assertOk()->assertSee((string) (now()->year - 1));

        $folder = Folder::withoutGlobalScopes()->where('slug', 'za')->sole();
        $this->get('/en/extranet/folders')->assertOk()->assertSee('South Africa');
        $this->get('/en/extranet/folders/'.$folder->getRouteKey())->assertOk()->assertSee('sample-validated-returns');

        $this->get('/en/announcements')->assertOk()->assertSee('Annual returns window open');
        $this->get('/fr/announcements')->assertOk()->assertSee('déclarations annuelles', false);
        $this->get('/pt/announcements')->assertOk()->assertSee('declarações anuais', false);
    }

    public function test_every_sample_folder_has_papers_that_download(): void
    {
        foreach (Folder::withoutGlobalScopes()->where('is_sample', true)->get() as $folder) {
            $documents = Document::withoutGlobalScopes()->where('folder_id', $folder->id)->get();
            $this->assertCount(2, $documents, "Folder {$folder->slug} is empty.");
            foreach ($documents as $document) {
                $pdf = Storage::disk('local')->get($document->file_path);
                $this->assertStringStartsWith('%PDF-1.4', $pdf);
                $this->assertGreaterThanOrEqual(3, substr_count($pdf, '/Type/Page/'), $document->original_filename.' has '.substr_count($pdf, '/Type/Page/').' pages.');
                $this->assertStringContainsString('Sample document', $pdf);
            }
        }
    }

    public function test_the_seeder_can_run_twice(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, Submission::where('country_id', $this->member->country_id)->count());
        $this->assertSame(3, Announcement::where('is_sample', true)->count());
    }

    public function test_each_folder_holds_papers_for_what_it_is_for(): void
    {
        $za = Folder::withoutGlobalScopes()->where('slug', 'za')->sole();
        $returns = Document::withoutGlobalScopes()->where('folder_id', $za->id)->where('original_filename', 'sample-validated-returns.pdf')->sole();
        $pdf = Storage::disk('local')->get($returns->file_path);
        // The figures as filed, from the sample returns.
        $this->assertStringContainsString('168.9', $pdf);
        $this->assertStringContainsString('ICASA annual report', $pdf);

        $committee = Folder::withoutGlobalScopes()->whereNotNull('committee_id')->first();
        $this->assertSame(['sample-committee-agenda.pdf', 'sample-committee-working-paper.pdf'],
            Document::withoutGlobalScopes()->where('folder_id', $committee->id)->orderBy('original_filename')->pluck('original_filename')->all());

        $secretariat = Folder::withoutGlobalScopes()->where('slug', 'secretariat-papers')->sole();
        $this->assertSame(2, Document::withoutGlobalScopes()->where('folder_id', $secretariat->id)->where('original_filename', 'like', 'sample-secretariat-note-%')->count());
    }

    public function test_strip_samples_removes_all_of_it(): void
    {
        $files = Document::withoutGlobalScopes()->where('is_sample', true)->pluck('file_path')->all();
        $this->assertNotEmpty($files);

        $this->artisan('content:strip-samples', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Submission::where('country_id', $this->member->country_id)->count());
        $this->assertSame(0, Announcement::withTrashed()->where('is_sample', true)->count());
        $this->assertSame(0, Document::withoutGlobalScopes()->whereNotNull('folder_id')->where('is_sample', true)->count());
        $this->assertSame(0, Folder::withoutGlobalScopes()->withTrashed()->where('is_sample', true)->count());
        $this->assertSame(0, IndicatorValue::where('is_sample', true)->count());
        foreach ($files as $file) {
            $this->assertFalse(Storage::disk('local')->exists($file), "{$file} is still on disk after strip-samples.");
        }
    }
}
