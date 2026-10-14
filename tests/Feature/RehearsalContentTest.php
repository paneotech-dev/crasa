<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Country;
use App\Models\Document;
use App\Models\Folder;
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
        $this->get('/en/extranet/folders/'.$folder->getRouteKey())->assertOk()->assertSee('sample-annual-return-guidance');

        $this->get('/en/announcements')->assertOk()->assertSee('annual returns window open');
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
                $this->assertGreaterThanOrEqual(3, substr_count($pdf, '/Type/Page/'), 'A sample paper has fewer than three pages.');
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

    public function test_strip_samples_removes_all_of_it(): void
    {
        $this->artisan('content:strip-samples', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Submission::where('country_id', $this->member->country_id)->count());
        $this->assertSame(0, Announcement::withTrashed()->where('is_sample', true)->count());
        $this->assertSame(0, Document::withoutGlobalScopes()->whereNotNull('folder_id')->where('is_sample', true)->count());
    }
}
