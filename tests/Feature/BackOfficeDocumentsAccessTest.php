<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Country;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The back office's documents screen and its recycle bin.
 *
 * The bin and restore sat behind the back-office door alone, so a moderator
 * or an auditor opened the list of deleted files, and the folders screen
 * counted every binned file whether or not the reader could find it in the
 * bin. Both now ask what the folders screen asks (manage content), and the
 * count is the bin's own list.
 */
class BackOfficeDocumentsAccessTest extends TestCase
{
    use RefreshDatabase;

    private Country $botswana;

    private Folder $folder;

    private Document $binned;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['crasa.documents_dir' => 'd3sec-test']);

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(AuthoritySeeder::class);
        $this->seed(CommitteeSeeder::class);

        $this->botswana = Country::where('iso_code', 'bw')->sole();
        $this->folder = Folder::withoutGlobalScopes()->create([
            'country_id' => $this->botswana->id, 'name' => 'Botswana', 'slug' => 'botswana', 'clearance' => Clearance::Member,
        ]);

        $rep = User::factory()->memberStateRep($this->botswana)->create();
        $this->actingAs($rep)->from('/en/extranet/folders/'.$this->folder->id)
            ->post('/en/extranet/folders/'.$this->folder->id.'/upload', ['files' => [UploadedFile::fake()->create('binned-return.pdf', 10, 'application/pdf')]]);
        $file = Document::sole();
        $this->actingAs($rep)->delete('/en/extranet/documents/'.$file->id)->assertRedirect();
        $this->binned = Document::withTrashed()->findOrFail($file->id);
        auth()->logout();
    }

    public static function forbidden(): array
    {
        // Moderator and auditor pass the back-office door and hold no
        // content rights; the other three are refused at the door. There is
        // no built-in editor without content rights (the editor holds
        // manage content).
        return [
            'moderator' => ['moderator'], 'auditor' => ['auditor'],
            'member' => ['memberStateRep'], 'committee' => ['committeeMember'], 'viewer' => ['viewer'],
        ];
    }

    private function as(string $state): User
    {
        return in_array($state, ['memberStateRep', 'committeeMember'], true)
            ? User::factory()->{$state}($this->botswana)->create()
            : User::factory()->{$state}()->create();
    }

    #[DataProvider('forbidden')]
    public function test_the_documents_screen_bin_and_restore_are_refused(string $state): void
    {
        $user = $this->as($state);

        $this->actingAs($user)->get('/en/admin/documents')->assertForbidden();
        $this->actingAs($user)->get('/en/admin/documents/folders/new')->assertForbidden();
        $this->actingAs($user)->post('/en/admin/documents/folders', ['name' => 'x', 'clearance' => 'member'])->assertForbidden();
        $this->actingAs($user)->get('/en/admin/documents/bin')->assertForbidden();
        $this->actingAs($user)->put('/en/admin/documents/files/'.$this->binned->id.'/restore')->assertForbidden();

        $this->assertSoftDeleted($this->binned);
    }

    public function test_the_secretariat_opens_the_bin_and_restores(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/en/admin/documents')->assertOk()
            ->assertSee(__('d3_docs.bin_title').' (1)');
        $this->actingAs($admin)->get('/en/admin/documents/bin')->assertOk()->assertSee('binned-return.pdf');
        $this->actingAs($admin)->put('/en/admin/documents/files/'.$this->binned->id.'/restore')->assertRedirect();

        $this->assertNotSoftDeleted($this->binned);
    }

    public function test_the_count_is_only_what_the_reader_finds_in_the_bin(): void
    {
        // An editor manages content but does not upload into a member
        // state's folder, so that state's binned file is not theirs to see
        // or restore, and is not counted.
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get('/en/admin/documents')->assertOk()
            ->assertSee(__('d3_docs.bin_title').' (0)')
            ->assertDontSee(__('d3_docs.bin_title').' (1)');
        $this->actingAs($editor)->get('/en/admin/documents/bin')->assertOk()->assertDontSee('binned-return.pdf');
        $this->actingAs($editor)->put('/en/admin/documents/files/'.$this->binned->id.'/restore')->assertNotFound();

        $this->assertSoftDeleted($this->binned);
    }
}
