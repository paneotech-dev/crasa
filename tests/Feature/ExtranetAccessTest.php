<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Enums\CommitteeRole;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The extranet, IN-02 and WS-09.
 *
 * THIS FILE IS MOSTLY REFUSALS, AND THAT IS DELIBERATE. Screen 12 makes
 * three claims about another member state's folders: they "are not listed
 * above, are not searchable, and are not reachable by guessing a URL".
 * Each of the three is tested separately, because a permissions system
 * usually satisfies the third and quietly fails the first two.
 */
class ExtranetAccessTest extends TestCase
{
    use RefreshDatabase;

    private Country $botswana;

    private Country $mozambique;

    private User $botswanaRep;

    private User $mozambiqueRep;

    private User $secretariat;

    private Folder $botswanaFolder;

    private Folder $mozambiqueFolder;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        $this->botswana = Country::where('iso_code', 'bw')->sole();
        $this->mozambique = Country::where('iso_code', 'mz')->sole();

        $this->botswanaRep = User::factory()->memberStateRep($this->botswana)->create();
        $this->mozambiqueRep = User::factory()->memberStateRep($this->mozambique)->create();
        $this->secretariat = User::factory()->administrator()->create();

        $this->botswanaFolder = Folder::withoutGlobalScopes()->create([
            'country_id' => $this->botswana->id, 'name' => 'Botswana', 'slug' => 'botswana',
            'clearance' => Clearance::Member,
        ]);

        $this->mozambiqueFolder = Folder::withoutGlobalScopes()->create([
            'country_id' => $this->mozambique->id, 'name' => 'Mozambique', 'slug' => 'mozambique',
            'clearance' => Clearance::Member,
        ]);
    }

    private function fileIn(Folder $folder, Clearance $clearance = Clearance::Member): Document
    {
        return Document::create([
            'documentable_type' => Folder::class,
            'documentable_id' => $folder->id,
            'folder_id' => $folder->id,
            'country_id' => $folder->country_id,
            'committee_id' => $folder->committee_id,
            'file_path' => 'extranet/'.$folder->id.'/evidence.pdf',
            'original_filename' => 'evidence.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'clearance' => $clearance,
        ]);
    }

    /* Screen 12's three claims, one at a time -------------------------------- */

    public function test_another_member_states_folder_is_not_listed(): void
    {
        $html = $this->actingAs($this->botswanaRep)
            ->get('/en/extranet/folders')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Botswana', $html);
        $this->assertStringNotContainsString('Mozambique', $html);
    }

    public function test_another_member_states_folder_is_not_searchable(): void
    {
        $this->actingAs($this->botswanaRep);

        // The global scope, so any query anywhere returns nothing. Not a
        // filter in one controller that a later query would miss.
        $this->assertSame(
            ['Botswana'],
            Folder::query()->pluck('name')->all()
        );
        $this->assertNull(Folder::find($this->mozambiqueFolder->id));
    }

    public function test_another_member_states_folder_is_not_reachable_by_guessing_a_url(): void
    {
        // Screen 12 draws GET /folders/mz/... answered with a refusal. A 404
        // rather than a 403, because a 403 confirms the folder exists.
        $this->actingAs($this->botswanaRep)
            ->get('/en/extranet/folders/'.$this->mozambiqueFolder->id)
            ->assertNotFound();
    }

    public function test_a_member_state_cannot_upload_into_another_countrys_folder(): void
    {
        $this->actingAs($this->botswanaRep)
            ->post('/en/extranet/folders/'.$this->mozambiqueFolder->id.'/upload', [
                'file' => UploadedFile::fake()->create('evidence.pdf', 40, 'application/pdf'),
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_member_state_cannot_download_another_countrys_file_by_id(): void
    {
        $file = $this->fileIn($this->mozambiqueFolder);

        // The direct file URL, which is where this usually leaks: the
        // listing is scoped and the download route is forgotten.
        $this->actingAs($this->botswanaRep)
            ->get('/en/documents/'.$file->id.'/download')
            ->assertForbidden();
    }

    public function test_nothing_in_the_extranet_is_served_from_the_filesystem(): void
    {
        $file = $this->fileIn($this->botswanaFolder);

        // The stored path is on the private disk and never appears as a URL
        // in the markup. Every byte goes through the controller.
        $html = $this->actingAs($this->botswanaRep)
            ->get('/en/extranet/folders/'.$this->botswanaFolder->id)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($file->file_path, $html);
        $this->assertStringContainsString('/documents/'.$file->id.'/download', $html);
    }

    /* Clearance -------------------------------------------------------------- */

    public function test_a_confidential_committee_document_is_refused_to_a_non_member(): void
    {
        $committee = Committee::first();

        $folder = Folder::withoutGlobalScopes()->create([
            'committee_id' => $committee->id, 'name' => 'Working papers', 'slug' => 'working-papers',
            'clearance' => Clearance::Confidential,
        ]);

        $file = $this->fileIn($folder, Clearance::Confidential);

        // Access to Confidential comes from a seat on the owning committee,
        // per the earlier ruling. Being signed in is not enough.
        $this->actingAs($this->botswanaRep)->get('/en/documents/'.$file->id.'/download')->assertForbidden();
        $this->actingAs($this->botswanaRep)->get('/en/extranet/folders/'.$folder->id)->assertForbidden();
    }

    public function test_a_committee_member_opens_their_own_committees_confidential_folder(): void
    {
        $committee = Committee::first();
        $this->botswanaRep->committees()->attach($committee->id, ['role' => CommitteeRole::Member->value]);

        $folder = Folder::withoutGlobalScopes()->create([
            'committee_id' => $committee->id, 'name' => 'Working papers', 'slug' => 'working-papers',
            'clearance' => Clearance::Confidential,
        ]);

        $this->actingAs($this->botswanaRep->fresh())
            ->get('/en/extranet/folders/'.$folder->id)
            ->assertOk();
    }

    public function test_a_secretariat_folder_is_refused_to_a_member_state(): void
    {
        $folder = Folder::withoutGlobalScopes()->create([
            'name' => 'Secretariat', 'slug' => 'secretariat', 'clearance' => Clearance::Secretariat,
        ]);

        $this->actingAs($this->botswanaRep)->get('/en/extranet/folders/'.$folder->id)->assertForbidden();
        $this->actingAs($this->secretariat)->get('/en/extranet/folders/'.$folder->id)->assertOk();
    }

    /* The tree cannot leak downwards ----------------------------------------- */

    public function test_a_subfolder_cannot_be_less_restricted_than_its_parent(): void
    {
        $parent = Folder::withoutGlobalScopes()->create([
            'name' => 'Confidential', 'slug' => 'confidential', 'clearance' => Clearance::Confidential,
        ]);

        $this->expectException(\RuntimeException::class);

        Folder::withoutGlobalScopes()->create([
            'parent_id' => $parent->id, 'name' => 'Open', 'slug' => 'open', 'clearance' => Clearance::Member,
        ]);
    }

    /* Uploading -------------------------------------------------------------- */

    public function test_a_representative_uploads_into_their_own_folder(): void
    {
        $this->actingAs($this->botswanaRep)
            ->from('/en/extranet/folders/'.$this->botswanaFolder->id)
            ->post('/en/extranet/folders/'.$this->botswanaFolder->id.'/upload', [
                'file' => UploadedFile::fake()->create('market-report.pdf', 40, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = Document::sole();

        $this->assertSame($this->botswana->id, $document->country_id);
        $this->assertSame($this->botswanaRep->id, $document->uploaded_by);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_an_archive_is_refused(): void
    {
        $this->actingAs($this->botswanaRep)
            ->from('/en/extranet/folders/'.$this->botswanaFolder->id)
            ->post('/en/extranet/folders/'.$this->botswanaFolder->id.'/upload', [
                'file' => UploadedFile::fake()->create('archive.zip', 40, 'application/zip'),
            ])
            ->assertSessionHasErrors('file');

        // Screen 12: "Archives are not accepted." A zip is a way past a
        // file-type check.
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_an_upload_writes_an_audit_row(): void
    {
        $this->actingAs($this->botswanaRep)
            ->post('/en/extranet/folders/'.$this->botswanaFolder->id.'/upload', [
                'file' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'),
            ]);

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->botswanaRep->id,
            'auditable_type' => Document::class,
        ]);
    }

    public function test_every_download_of_a_non_public_document_is_recorded(): void
    {
        $file = $this->fileIn($this->botswanaFolder);
        Storage::disk('local')->put($file->file_path, 'x');

        $this->actingAs($this->botswanaRep)->get('/en/documents/'.$file->id.'/download');

        $this->assertDatabaseHas('audit_log', [
            'user_id' => $this->botswanaRep->id,
            'action' => AuditLog::DOCUMENT_DOWNLOADED,
            'auditable_id' => $file->id,
        ]);
    }

    /* The committee workspace ------------------------------------------------- */

    public function test_the_committee_workspace_needs_a_seat_on_the_committee(): void
    {
        $committee = Committee::first();

        // A workspace any signed-in account can open is a shared drive.
        $this->actingAs($this->botswanaRep)
            ->get('/en/extranet/committees/'.$committee->slug)
            ->assertForbidden();

        $this->botswanaRep->committees()->attach($committee->id, ['role' => CommitteeRole::Member->value]);

        $this->actingAs($this->botswanaRep->fresh())
            ->get('/en/extranet/committees/'.$committee->slug)
            ->assertOk();
    }

    /* Meetings ---------------------------------------------------------------- */

    public function test_a_restricted_meeting_is_not_shown_to_someone_outside_the_committee(): void
    {
        $committee = Committee::first();

        Meeting::create([
            'committee_id' => $committee->id, 'title' => 'Closed working session',
            'slug' => 'closed-working-session', 'starts_on' => now()->addDays(10),
            'clearance' => Clearance::Confidential,
        ]);

        Meeting::create([
            'title' => 'Annual General Meeting', 'slug' => 'agm-2026',
            'starts_on' => now()->addDays(20), 'clearance' => Clearance::Member,
        ]);

        $html = $this->actingAs($this->botswanaRep)
            ->get('/en/extranet/calendar')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Annual General Meeting', $html);
        $this->assertStringNotContainsString('Closed working session', $html);
    }

    /**
     * The boundary this whole section sits on. The platform records what a
     * meeting is and links out to registration; it manages nothing about
     * the operation of one.
     */
    public function test_the_meeting_record_holds_nothing_about_travel_or_logistics(): void
    {
        $columns = Schema::getColumnListing('meetings');

        foreach (['hotel', 'accommodation', 'transfer', 'driver', 'flight', 'arrival', 'departure',
            'interpretation', 'dietary', 'catering', 'feedback', 'reminder'] as $forbidden) {
            foreach ($columns as $column) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $column,
                    'meetings.'.$column.' crosses into event management, which is reserved commercial scope.'
                );
            }
        }
    }

    /* Guests ------------------------------------------------------------------ */

    public function test_a_guest_reaches_no_part_of_the_extranet(): void
    {
        $this->get('/en/extranet/folders')->assertRedirect();
        $this->get('/en/extranet/folders/'.$this->botswanaFolder->id)->assertRedirect();
        $this->get('/en/extranet/calendar')->assertRedirect();
    }

    public function test_the_extranet_entry_point_sends_a_visitor_to_sign_in(): void
    {
        $this->get('/en/extranet')->assertRedirectContains('/login');

        $this->actingAs($this->botswanaRep)
            ->get('/en/extranet')
            ->assertRedirectContains('/extranet/dashboard');
    }
}
