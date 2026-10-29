<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Events\DocumentAdded;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\User;
use App\Support\Documents\DocumentStore;
use App\Support\Documents\FolderAccess;
use App\Support\Documents\PdfText;
use App\Support\SampleDocumentPdf;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Document management: folders, files, versions, the recycle bin, share
 * links, access per folder and search inside documents.
 *
 * The refusals come first. PM-02: a representative of one member state
 * cannot list or download another's folder, and cannot reach a file of it
 * by its share link, by id, by version id or through search.
 */
class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    private Country $botswana;

    private Country $mozambique;

    private User $botswanaRep;

    private User $mozambiqueRep;

    private User $admin;

    private Folder $botswanaFolder;

    private Folder $mozambiqueFolder;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['crasa.documents_dir' => 'd3docs-test']);

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(AuthoritySeeder::class);
        $this->seed(CommitteeSeeder::class);

        $this->botswana = Country::where('iso_code', 'bw')->sole();
        $this->mozambique = Country::where('iso_code', 'mz')->sole();
        $this->botswanaRep = User::factory()->memberStateRep($this->botswana)->create(['name' => 'Kagiso Botswana']);
        $this->mozambiqueRep = User::factory()->memberStateRep($this->mozambique)->create(['name' => 'Amélia Mozambique']);
        $this->admin = User::factory()->administrator()->create(['name' => 'Secretariat Admin']);

        $this->botswanaFolder = Folder::withoutGlobalScopes()->create([
            'country_id' => $this->botswana->id, 'name' => 'Botswana', 'slug' => 'botswana', 'clearance' => Clearance::Member,
        ]);
        $this->mozambiqueFolder = Folder::withoutGlobalScopes()->create([
            'country_id' => $this->mozambique->id, 'name' => 'Mozambique', 'slug' => 'mozambique', 'clearance' => Clearance::Member,
        ]);
    }

    private function pdf(string $text): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'd3d');
        file_put_contents($path, SampleDocumentPdf::make('Return note', $text, '1 January 2026', [
            ['heading' => 'Figures', 'paragraphs' => [$text]],
        ]));

        return new UploadedFile($path, 'return-note.pdf', 'application/pdf', null, true);
    }

    private function upload(User $user, Folder $folder, array $files)
    {
        return $this->actingAs($user)
            ->from('/en/extranet/folders/'.$folder->id)
            ->post('/en/extranet/folders/'.$folder->id.'/upload', ['files' => $files]);
    }

    private function mozambiqueFile(): Document
    {
        $this->upload($this->mozambiqueRep, $this->mozambiqueFolder, [$this->pdf('Zambezi spectrum auction 2026')]);

        return Document::where('folder_id', $this->mozambiqueFolder->id)->latest('id')->firstOrFail();
    }

    /* PM-02 ------------------------------------------------------------------ */

    public function test_pm02_a_representative_cannot_list_or_open_another_member_states_folder(): void
    {
        $this->actingAs($this->botswanaRep)->get('/en/extranet/folders')->assertOk()->assertDontSee('Mozambique');
        $this->actingAs($this->botswanaRep)->get('/en/extranet/folders/'.$this->mozambiqueFolder->id)->assertNotFound();
    }

    public function test_pm02_a_representative_cannot_reach_another_member_states_file_any_way(): void
    {
        $file = $this->mozambiqueFile();
        $version = DocumentVersion::where('document_id', $file->id)->sole();

        $this->actingAs($this->botswanaRep);

        $this->assertContains($this->get('/en/documents/'.$file->id.'/download')->getStatusCode(), [403, 404]);
        $this->assertContains($this->get('/en/documents/'.$file->id)->getStatusCode(), [403, 404]);
        $this->get('/en/extranet/documents/'.$file->id)->assertNotFound();
        $this->get('/en/extranet/shared/'.$file->share_token)->assertNotFound();
        $this->get('/en/extranet/versions/'.$version->id)->assertNotFound();

        // Through search: by name and, where a PDF tool exists, by text.
        $this->get('/en/extranet/search?q=return-note')->assertOk()->assertDontSee('return-note.pdf');
        $this->get('/en/extranet/search?q=Zambezi')->assertOk()->assertDontSee('return-note.pdf');

        // And the owner reaches all of it.
        $this->actingAs($this->mozambiqueRep);
        $this->get('/en/extranet/documents/'.$file->id)->assertOk()->assertSee('return-note.pdf');
        $this->get('/en/extranet/shared/'.$file->share_token)->assertRedirect('/en/extranet/documents/'.$file->id);
        $this->get('/en/extranet/versions/'.$version->id)->assertOk();
    }

    public function test_pm02_a_representative_cannot_change_another_member_states_file(): void
    {
        $file = $this->mozambiqueFile();
        $this->actingAs($this->botswanaRep);

        $this->put('/en/extranet/documents/'.$file->id.'/name', ['name' => 'x.pdf'])->assertNotFound();
        $this->delete('/en/extranet/documents/'.$file->id)->assertNotFound();
        $this->post('/en/extranet/documents/'.$file->id.'/versions', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertNotFound();
        $this->put('/en/extranet/documents/'.$file->id.'/validate')->assertNotFound();

        $this->assertSame('return-note.pdf', $file->fresh()->original_filename);
        $this->assertSame(1, $file->fresh()->version);
    }

    public function test_a_file_cannot_be_moved_into_another_member_states_folder(): void
    {
        $this->upload($this->botswanaRep, $this->botswanaFolder, [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]);
        $file = Document::sole();

        $this->actingAs($this->botswanaRep)
            ->put('/en/extranet/documents/'.$file->id.'/folder', ['folder_id' => $this->mozambiqueFolder->id])
            ->assertSessionHasErrors('folder_id');

        $this->assertSame($this->botswanaFolder->id, $file->fresh()->folder_id);
    }

    /* Files ------------------------------------------------------------------ */

    public function test_several_files_upload_at_once_with_a_version_each_and_the_event(): void
    {
        Event::fake([DocumentAdded::class]);

        $this->upload($this->botswanaRep, $this->botswanaFolder, [
            UploadedFile::fake()->create('market-report.pdf', 40, 'application/pdf'),
            UploadedFile::fake()->create('figures.csv', 4, 'text/csv'),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, Document::where('folder_id', $this->botswanaFolder->id)->count());
        $this->assertSame(2, DocumentVersion::count());

        foreach (Document::all() as $document) {
            $this->assertStringStartsWith('d3docs-test/'.$this->botswanaFolder->id.'/', $document->file_path);
            Storage::disk('local')->assertExists($document->file_path);
            $this->assertSame($this->botswanaRep->id, $document->uploaded_by);
        }

        Event::assertDispatchedTimes(DocumentAdded::class, 2);
        Event::assertDispatched(DocumentAdded::class, fn (DocumentAdded $e) => $e->folder->is($this->botswanaFolder) && $e->document->original_filename === 'market-report.pdf');

        $this->assertSame(2, AuditLog::where('action', DocumentStore::ADDED)->count());
    }

    public function test_an_archive_is_refused_among_several(): void
    {
        $this->upload($this->botswanaRep, $this->botswanaFolder, [
            UploadedFile::fake()->create('ok.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('bundle.zip', 10, 'application/zip'),
        ])->assertSessionHasErrors('files.1');

        $this->assertSame(0, Document::count());
    }

    public function test_the_upload_help_says_what_is_accepted_in_plain_words(): void
    {
        $this->actingAs($this->botswanaRep)->get('/en/extranet/folders/'.$this->botswanaFolder->id)
            ->assertOk()
            ->assertSee('PDF, Word, Excel, CSV or text, up to 25 MB. Archives are not accepted.')
            ->assertSee('name="files[]"', false)
            ->assertSee('multiple', false);
    }

    public function test_replacing_a_file_keeps_every_version_with_who_and_when(): void
    {
        Event::fake([DocumentAdded::class]);

        $this->upload($this->botswanaRep, $this->botswanaFolder, [UploadedFile::fake()->create('return.pdf', 10, 'application/pdf')]);
        $file = Document::sole();
        $first = $file->file_path;

        $colleague = User::factory()->memberStateRep($this->botswana)->create(['name' => 'Neo Second']);
        $this->actingAs($colleague)
            ->post('/en/extranet/documents/'.$file->id.'/versions', ['file' => UploadedFile::fake()->create('return-v2.pdf', 12, 'application/pdf')])
            ->assertRedirect();

        $file->refresh();
        $this->assertSame(2, $file->version);
        $this->assertNotSame($first, $file->file_path);
        $this->assertSame([2, 1], $file->versions()->pluck('version')->all());
        Storage::disk('local')->assertExists($first);
        Event::assertDispatchedTimes(DocumentAdded::class, 2);

        $this->actingAs($this->botswanaRep)->get('/en/extranet/documents/'.$file->id)
            ->assertOk()
            ->assertSee('Kagiso Botswana')
            ->assertSee('Neo Second')
            ->assertSee('return-v2.pdf');

        $this->assertDatabaseHas('audit_log', ['action' => DocumentStore::REPLACED, 'auditable_id' => $file->id, 'user_id' => $colleague->id]);
    }

    public function test_a_file_is_renamed_keeping_its_format_and_moved_within_its_country(): void
    {
        $sub = Folder::withoutGlobalScopes()->create([
            'parent_id' => $this->botswanaFolder->id, 'country_id' => $this->botswana->id, 'name' => '2026 cycle', 'slug' => '2026-cycle', 'clearance' => Clearance::Member,
        ]);
        $this->upload($this->botswanaRep, $this->botswanaFolder, [UploadedFile::fake()->create('draft.pdf', 10, 'application/pdf')]);
        $file = Document::sole();

        $this->actingAs($this->botswanaRep)->put('/en/extranet/documents/'.$file->id.'/name', ['name' => 'Market review 2026'])->assertRedirect();
        $this->assertSame('Market review 2026.pdf', $file->fresh()->original_filename);

        $this->actingAs($this->botswanaRep)->put('/en/extranet/documents/'.$file->id.'/folder', ['folder_id' => $sub->id])->assertRedirect();
        $this->assertSame($sub->id, $file->fresh()->folder_id);
    }

    public function test_the_recycle_bin_restores_within_thirty_days_and_the_purge_is_scheduled(): void
    {
        $this->upload($this->botswanaRep, $this->botswanaFolder, [UploadedFile::fake()->create('old.pdf', 10, 'application/pdf')]);
        $file = Document::sole();

        $this->actingAs($this->botswanaRep)->delete('/en/extranet/documents/'.$file->id)->assertRedirect();
        $this->assertSoftDeleted($file);
        $this->assertSame($this->botswanaRep->id, Document::withTrashed()->find($file->id)->deleted_by);

        // Gone from the folder and its links, listed in the bin.
        $this->actingAs($this->botswanaRep)->get('/en/extranet/folders/'.$this->botswanaFolder->id)->assertDontSee('/documents/'.$file->id.'/download');
        $this->actingAs($this->botswanaRep)->get('/en/documents/'.$file->id.'/download')->assertNotFound();
        $this->actingAs($this->botswanaRep)->get('/en/extranet/documents/bin')->assertOk()->assertSee('old.pdf');
        $this->actingAs($this->mozambiqueRep)->get('/en/extranet/documents/bin')->assertOk()->assertDontSee('old.pdf');
        $this->actingAs($this->mozambiqueRep)->put('/en/extranet/documents/'.$file->id.'/restore')->assertNotFound();

        $this->actingAs($this->botswanaRep)->put('/en/extranet/documents/'.$file->id.'/restore')->assertRedirect();
        $this->assertNotSoftDeleted($file);

        // The purge takes only what has been in the bin over thirty days.
        $recent = $this->upload($this->botswanaRep, $this->botswanaFolder, [UploadedFile::fake()->create('recent.pdf', 10, 'application/pdf')]);
        $kept = Document::where('original_filename', 'recent.pdf')->sole();
        $kept->delete();
        $file->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();
        $path = $file->file_path;

        $this->artisan('documents:purge-bin')->assertSuccessful();

        $this->assertNull(Document::withTrashed()->find($file->id));
        Storage::disk('local')->assertMissing($path);
        $this->assertNotNull(Document::withTrashed()->find($kept->id));

        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');
        $this->assertStringContainsString('documents:purge-bin', $commands);
    }

    public function test_a_pdf_opens_inline_with_the_security_headers(): void
    {
        $this->upload($this->botswanaRep, $this->botswanaFolder, [UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')]);
        $file = Document::sole();

        $response = $this->actingAs($this->botswanaRep)->get('/en/documents/'.$file->id)->assertOk();

        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_downloads_and_additions_are_the_folders_activity(): void
    {
        $this->upload($this->botswanaRep, $this->botswanaFolder, [UploadedFile::fake()->create('figures.pdf', 10, 'application/pdf')]);
        $file = Document::sole();

        $this->actingAs($this->botswanaRep)->get('/en/documents/'.$file->id.'/download')->assertOk();

        $row = AuditLog::where('action', AuditLog::DOCUMENT_DOWNLOADED)->sole();
        $this->assertSame($this->botswanaFolder->id, $row->changes['folder_id']);

        $this->actingAs($this->botswanaRep)->get('/en/extranet/folders/'.$this->botswanaFolder->id)
            ->assertOk()
            ->assertSee('Kagiso Botswana added figures.pdf')
            ->assertSee('Kagiso Botswana downloaded figures.pdf');
    }

    public function test_search_finds_a_pdf_by_its_text(): void
    {
        if (PdfText::tool() === null) {
            $this->markTestSkipped('No PDF text tool on this host: search is by name and description only.');
        }

        $this->upload($this->botswanaRep, $this->botswanaFolder, [$this->pdf('Okavango interconnection tariff schedule')]);

        $this->assertStringContainsString('Okavango', (string) Document::sole()->search_text);
        $this->actingAs($this->botswanaRep)->get('/en/extranet/search?q=Okavango')->assertOk()->assertSee('return-note.pdf');
        $this->actingAs($this->mozambiqueRep)->get('/en/extranet/search?q=Okavango')->assertOk()->assertDontSee('return-note.pdf');
    }

    /* Folders ---------------------------------------------------------------- */

    public function test_the_secretariat_creates_renames_moves_and_deletes_folders_three_levels_deep(): void
    {
        $this->actingAs($this->admin);

        $this->post('/en/admin/documents/folders', [
            'name' => 'Regional workshop', 'description' => 'Papers for the regional workshop.', 'clearance' => 'member',
            'access' => ['view' => ['type' => 'signed_in'], 'upload' => ['type' => 'secretariat'], 'validate' => ['type' => 'secretariat']],
        ])->assertRedirect();
        $root = Folder::where('name', 'Regional workshop')->sole();
        $this->assertSame('Papers for the regional workshop.', $root->description);

        $this->post('/en/admin/documents/folders', ['name' => 'Day one', 'parent_id' => $root->id, 'clearance' => 'member', 'inherit' => '1'])->assertRedirect();
        $two = Folder::where('name', 'Day one')->sole();
        $this->post('/en/admin/documents/folders', ['name' => 'Slides', 'parent_id' => $two->id, 'clearance' => 'member', 'inherit' => '1'])->assertRedirect();
        $three = Folder::where('name', 'Slides')->sole();
        $this->assertSame(3, $three->depth());

        // A fourth level is refused.
        $this->post('/en/admin/documents/folders', ['name' => 'Too deep', 'parent_id' => $three->id, 'clearance' => 'member', 'inherit' => '1'])
            ->assertSessionHasErrors('parent_id');
        $this->assertNull(Folder::where('name', 'Too deep')->first());

        // Rename and move: "Slides" up under the root.
        $this->put('/en/admin/documents/folders/'.$three->id, ['name' => 'Presentations', 'parent_id' => $root->id, 'clearance' => 'member', 'inherit' => '1'])->assertRedirect();
        $this->assertSame(['Presentations', $root->id], [$three->fresh()->name, $three->fresh()->parent_id]);

        // A folder cannot go inside itself.
        $this->put('/en/admin/documents/folders/'.$root->id, ['name' => 'Regional workshop', 'parent_id' => $two->id, 'clearance' => 'member', 'inherit' => '1'])
            ->assertSessionHasErrors('parent_id');

        // Deleting: only when empty.
        $this->delete('/en/admin/documents/folders/'.$root->id)->assertSessionHasErrors('folder');
        $this->delete('/en/admin/documents/folders/'.$three->id)->assertRedirect();
        $this->assertSoftDeleted($three);

        $this->get('/en/admin/documents');
        $this->get('/en/admin/documents')->assertOk()->assertSee('Regional workshop')->assertSee('Day one')->assertDontSee('Presentations');
    }

    public function test_every_document_screen_opens_for_the_secretariat(): void
    {
        $this->upload($this->botswanaRep, $this->botswanaFolder, [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $file = Document::sole();
        $file->delete();

        $this->actingAs($this->admin);
        foreach (['/en/admin/documents', '/en/admin/documents/folders/new', '/en/admin/documents/folders/new?parent='.$this->botswanaFolder->id,
            '/en/admin/documents/folders/'.$this->botswanaFolder->id.'/edit', '/en/admin/documents/bin',
            '/en/extranet/documents/folders/new', '/en/extranet/documents/folders/'.$this->botswanaFolder->id.'/edit',
            '/en/extranet/documents/bin', '/fr/admin/documents/folders/new', '/pt/extranet/documents/bin'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/en/admin/documents/bin')->assertSee('a.pdf');
        $this->put('/en/admin/documents/files/'.$file->id.'/restore')->assertRedirect();
        $this->assertNotSoftDeleted($file);
        $this->get('/en/extranet/documents/'.$file->id)->assertOk()->assertSee('Mark as validated');
    }

    public function test_a_folder_with_files_is_not_deleted(): void
    {
        $this->upload($this->botswanaRep, $this->botswanaFolder, [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);

        $this->actingAs($this->admin)->delete('/en/admin/documents/folders/'.$this->botswanaFolder->id)->assertSessionHasErrors('folder');
        $this->assertNotSoftDeleted($this->botswanaFolder);
    }

    public function test_a_representative_cannot_manage_folders(): void
    {
        $this->actingAs($this->botswanaRep);

        $this->get('/en/admin/documents')->assertForbidden();
        $this->post('/en/admin/documents/folders', ['name' => 'x', 'clearance' => 'member'])->assertForbidden();
        $this->get('/en/extranet/documents/folders/new')->assertForbidden();
        $this->post('/en/extranet/documents/folders', ['name' => 'x', 'clearance' => 'member'])->assertForbidden();
        $this->get('/en/extranet/documents/folders/'.$this->botswanaFolder->id.'/edit')->assertForbidden();
        $this->delete('/en/extranet/documents/folders/'.$this->botswanaFolder->id)->assertForbidden();
        $this->get('/en/extranet/documents/folders/'.$this->mozambiqueFolder->id.'/edit')->assertNotFound();

        $this->assertSame(2, Folder::withoutGlobalScopes()->count());
    }

    /* Access per folder ------------------------------------------------------ */

    public function test_the_folder_page_says_who_has_access_in_plain_words(): void
    {
        $this->actingAs($this->botswanaRep)->get('/en/extranet/folders/'.$this->botswanaFolder->id)
            ->assertOk()
            ->assertSee('BOCRA representatives, upload and download')
            ->assertSee('CRASA Secretariat, read and validate');
    }

    public function test_access_is_inherited_by_subfolders_unless_changed(): void
    {
        $committee = Committee::first();
        $member = User::factory()->committeeMember($this->botswana)->create();
        $member->committees()->attach($committee->id, ['role' => 'member']);
        $named = User::factory()->memberStateRep($this->mozambique)->create(['name' => 'Named Reader']);

        $root = Folder::withoutGlobalScopes()->create(['name' => 'Spectrum papers', 'slug' => 'spectrum-papers', 'clearance' => Clearance::Member, 'access' => [
            'view' => ['type' => 'committee', 'committee_id' => $committee->id],
            'upload' => ['type' => 'committee', 'committee_id' => $committee->id],
            'validate' => ['type' => 'secretariat'],
        ]]);
        FolderAccess::sync($root);
        $child = Folder::withoutGlobalScopes()->create(['parent_id' => $root->id, 'name' => 'Session 1', 'slug' => 'session-1', 'clearance' => Clearance::Member]);
        FolderAccess::sync($child);

        // The child follows the parent: the committee seat opens it, a
        // representative without one does not.
        $this->actingAs($member->fresh())->get('/en/extranet/folders/'.$child->id)->assertOk()->assertSee('As set on the folder Spectrum papers.');
        $this->assertContains($this->actingAs($this->botswanaRep)->get('/en/extranet/folders/'.$child->id)->getStatusCode(), [403, 404]);

        // Changed on the child: named people only.
        $child->update(['access' => [
            'view' => ['type' => 'users', 'user_ids' => [$named->id]],
            'upload' => ['type' => 'users', 'user_ids' => [$named->id]],
            'validate' => ['type' => 'users', 'user_ids' => [$named->id]],
        ]]);
        FolderAccess::sync($child);

        $this->assertContains($this->actingAs($member->fresh())->get('/en/extranet/folders/'.$child->id)->getStatusCode(), [403, 404]);
        $this->actingAs($named)->get('/en/extranet/folders/'.$child->id)->assertOk()->assertSee('Named people: Named Reader, upload and download');
        $this->actingAs($member->fresh())->get('/en/extranet/folders/'.$root->id)->assertOk();
    }

    public function test_a_rule_naming_one_member_state_keeps_the_country_boundary(): void
    {
        $this->actingAs($this->admin)->post('/en/admin/documents/folders', [
            'name' => 'Botswana returns', 'clearance' => 'member',
            'access' => [
                'view' => ['type' => 'country', 'country_id' => $this->botswana->id],
                'upload' => ['type' => 'country', 'country_id' => $this->botswana->id],
                'validate' => ['type' => 'secretariat'],
            ],
        ])->assertRedirect();

        $folder = Folder::withoutGlobalScopes()->where('name', 'Botswana returns')->sole();
        $this->assertSame($this->botswana->id, $folder->country_id);

        $this->actingAs($this->mozambiqueRep)->get('/en/extranet/folders')->assertOk()->assertDontSee('/extranet/folders/'.$folder->id.'"', false);
        $this->actingAs($this->mozambiqueRep)->get('/en/extranet/folders/'.$folder->id)->assertNotFound();
        $this->actingAs($this->botswanaRep)->get('/en/extranet/folders/'.$folder->id)->assertOk();
    }

    public function test_upload_and_validate_follow_the_rule(): void
    {
        $validator = User::factory()->memberStateRep($this->botswana)->create(['name' => 'Validating Officer']);
        $this->botswanaFolder->update(['access' => [
            'view' => ['type' => 'country', 'country_id' => $this->botswana->id],
            'upload' => ['type' => 'users', 'user_ids' => [$validator->id]],
            'validate' => ['type' => 'users', 'user_ids' => [$validator->id]],
        ]]);

        // The representative still reads, and may no longer upload.
        $this->actingAs($this->botswanaRep)->get('/en/extranet/folders/'.$this->botswanaFolder->id)->assertOk()->assertDontSee('name="files[]"', false);
        $this->upload($this->botswanaRep, $this->botswanaFolder, [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertForbidden();

        $this->upload($validator, $this->botswanaFolder, [UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])->assertRedirect();
        $file = Document::sole();

        $this->actingAs($this->botswanaRep)->put('/en/extranet/documents/'.$file->id.'/validate')->assertForbidden();
        $this->actingAs($validator)->put('/en/extranet/documents/'.$file->id.'/validate')->assertRedirect();
        $this->assertSame($validator->id, $file->fresh()->validated_by);
    }

    /* The demonstration folders ---------------------------------------------- */

    public function test_the_demonstration_folders_show_real_names_without_touching_the_files(): void
    {
        $path = 'extranet/'.$this->botswanaFolder->id.'/sample-validated-returns.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 sample');
        Document::withoutEvents(fn () => Document::create([
            'documentable_type' => Folder::class, 'documentable_id' => $this->botswanaFolder->id, 'folder_id' => $this->botswanaFolder->id,
            'country_id' => $this->botswana->id, 'file_path' => $path, 'original_filename' => 'sample-validated-returns.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 15, 'clearance' => Clearance::Member, 'is_sample' => true,
        ]));

        $migration = require database_path('migrations/2026_10_29_190100_realistic_names_for_demonstration_folder_files.php');
        $migration->up();

        $document = Document::sole();
        $this->assertSame('BOCRA validated annual returns.pdf', $document->original_filename);
        $this->assertSame($path, $document->file_path);
        $this->assertSame('%PDF-1.4 sample', Storage::disk('local')->get($path));

        $migration->down();
        $this->assertSame('sample-validated-returns.pdf', $document->fresh()->original_filename);
    }
}
