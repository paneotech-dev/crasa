<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Enums\CommitteeRole;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A committee seat opens the committee's folders, whatever the country.
 *
 * Before this, CountryScope answered "nothing" for any account without a
 * country, so a committee member who sits for the region rather than for a
 * member state could not see their own committee's working papers. The seat
 * is now enough for the committee's folders; everything else about the scope
 * is unchanged, and the folder and document policies still decide clearance.
 */
class CommitteeFolderAccessTest extends TestCase
{
    use RefreshDatabase;

    private Committee $spectrum;

    private Committee $other;

    private Folder $workingPapers;

    private Folder $secretariatPapers;

    private Folder $southAfricaFolder;

    private Country $southAfrica;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        [$this->spectrum, $this->other] = Committee::query()->orderBy('id')->take(2)->get()->all();
        $this->southAfrica = Country::where('iso_code', 'za')->sole();

        $this->workingPapers = Folder::withoutGlobalScopes()->create([
            'committee_id' => $this->spectrum->id, 'name' => 'Working papers', 'slug' => 'working-papers',
            'clearance' => Clearance::Confidential,
        ]);
        $this->secretariatPapers = Folder::withoutGlobalScopes()->create([
            'name' => 'Secretariat papers', 'slug' => 'secretariat-papers', 'clearance' => Clearance::Secretariat,
        ]);
        $this->southAfricaFolder = Folder::withoutGlobalScopes()->create([
            'country_id' => $this->southAfrica->id, 'name' => 'South Africa', 'slug' => 'south-africa',
            'clearance' => Clearance::Member,
        ]);

        foreach (['working-paper.pdf', 'agenda.pdf'] as $name) {
            $this->fileIn($this->workingPapers, $name);
        }
    }

    private function fileIn(Folder $folder, string $name): Document
    {
        Storage::disk('local')->put('extranet/'.$folder->id.'/'.$name, '%PDF-1.4 test');

        return Document::create([
            'documentable_type' => Folder::class,
            'documentable_id' => $folder->id,
            'folder_id' => $folder->id,
            'country_id' => $folder->country_id,
            'committee_id' => $folder->committee_id,
            'file_path' => 'extranet/'.$folder->id.'/'.$name,
            'original_filename' => $name,
            'mime_type' => 'application/pdf',
            'size_bytes' => 13,
            'clearance' => $folder->clearance,
        ]);
    }

    private function seatedOn(Committee $committee, ?Country $country = null): User
    {
        // The factory gives a country by default; the live account has none.
        $user = User::factory()->committeeMember($country)->create(['country_id' => $country?->id]);
        $user->committees()->attach($committee->id, ['role' => CommitteeRole::Member->value]);

        return $user->fresh();
    }

    public function test_a_committee_member_without_a_country_sees_and_opens_their_committees_papers(): void
    {
        $member = $this->seatedOn($this->spectrum);
        $this->assertNull($member->country_id);

        $this->actingAs($member)
            ->get('/en/extranet/committees/'.$this->spectrum->slug)
            ->assertOk()
            ->assertSee('Working papers');

        $this->actingAs($member)
            ->get('/en/extranet/folders/'.$this->workingPapers->id)
            ->assertOk()
            ->assertSee('working-paper.pdf')
            ->assertSee('agenda.pdf');

        foreach (Document::where('folder_id', $this->workingPapers->id)->get() as $document) {
            $this->actingAs($member)->get('/en/documents/'.$document->id.'/download')->assertOk();
        }

        // Only the committee's folders: nothing regional or national appears.
        $this->actingAs($member);
        $this->assertSame(['Working papers'], Folder::query()->pluck('name')->all());
    }

    public function test_a_member_of_another_committee_does_not_see_them(): void
    {
        $outsider = $this->seatedOn($this->other);

        $this->actingAs($outsider)
            ->get('/en/extranet/folders/'.$this->workingPapers->id)
            ->assertNotFound();

        $this->actingAs($outsider);
        $this->assertSame([], Folder::query()->pluck('name')->all());

        $document = Document::where('folder_id', $this->workingPapers->id)->first();
        $this->actingAs($outsider)->get('/en/documents/'.$document->id.'/download')->assertForbidden();
    }

    public function test_a_viewers_visibility_is_unchanged(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer);
        $this->assertSame([], Folder::query()->pluck('name')->all());
    }

    public function test_a_member_state_representatives_folders_are_unchanged(): void
    {
        $rep = User::factory()->memberStateRep($this->southAfrica)->create();

        $this->actingAs($rep);
        $names = Folder::query()->orderBy('name')->pluck('name')->all();

        // Their country's folder and the regional folders, exactly as before.
        $this->assertSame(['Secretariat papers', 'South Africa', 'Working papers'], $names);
    }

    public function test_a_seat_adds_the_committees_folders_to_a_country_scoped_member(): void
    {
        $member = $this->seatedOn($this->spectrum, $this->southAfrica);

        $this->actingAs($member);
        $this->assertSame(['Secretariat papers', 'South Africa', 'Working papers'], Folder::query()->orderBy('name')->pluck('name')->all());
    }
}
