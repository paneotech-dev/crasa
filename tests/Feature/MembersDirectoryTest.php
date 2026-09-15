<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\AuthorityContact;
use App\Models\Country;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The members directory. Extranet item 4.
 *
 * Two rules hold this thing up, and both are here. Consent gates the
 * listing: a person appears because they said their details may, and comes
 * out the moment that is withdrawn. And the directory never leaves the
 * extranet: no public route, no export, no report.
 */
class MembersDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $botswanaRep;

    private User $mozambiqueRep;

    private Authority $botswanaAuthority;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        $botswana = Country::where('iso_code', 'bw')->firstOrFail();
        $mozambique = Country::where('iso_code', 'mz')->firstOrFail();

        $this->botswanaAuthority = Authority::factory()->create(['country_id' => $botswana->id]);
        Authority::factory()->create(['country_id' => $mozambique->id]);

        $this->botswanaRep = User::factory()->create(['role' => 'member_state_rep', 'country_id' => $botswana->id]);
        $this->mozambiqueRep = User::factory()->create(['role' => 'member_state_rep', 'country_id' => $mozambique->id]);
    }

    /** @return array<string, mixed> */
    private function person(array $overrides = []): array
    {
        return array_merge([
            'name' => 'A named officer',
            'role' => 'Head of spectrum',
            'email' => 'officer@example.test',
            'phone' => '+267 000 0000',
            'sectors' => [Sector::first()->id],
        ], $overrides);
    }

    public function test_a_person_without_consent_is_not_in_the_directory(): void
    {
        $this->actingAs($this->botswanaRep)->post('/en/directory', $this->person())->assertRedirect();

        $contact = AuthorityContact::sole();
        $this->assertNull($contact->listed_at);

        $this->actingAs($this->mozambiqueRep)
            ->get('/en/directory')
            ->assertOk()
            ->assertDontSee('A named officer');
    }

    public function test_consent_puts_a_person_in_the_directory_for_every_member_state(): void
    {
        $this->actingAs($this->botswanaRep)
            ->post('/en/directory', $this->person(['consented' => 1]))
            ->assertRedirect();

        // Regional by design: the point of the directory is that a
        // Mozambican regulator can find the right person in Botswana.
        $this->actingAs($this->mozambiqueRep)
            ->get('/en/directory')
            ->assertOk()
            ->assertSee('A named officer');
    }

    public function test_withdrawing_consent_takes_a_person_out_straight_away(): void
    {
        $this->actingAs($this->botswanaRep)->post('/en/directory', $this->person(['consented' => 1]));
        $contact = AuthorityContact::sole();

        $this->actingAs($this->botswanaRep)
            ->put('/en/directory/'.$contact->id, $this->person())
            ->assertRedirect();

        $this->assertNull($contact->fresh()->listed_at);

        $this->actingAs($this->mozambiqueRep)
            ->get('/en/directory')
            ->assertOk()
            ->assertDontSee('A named officer');
    }

    public function test_a_representative_adds_only_to_their_own_authority(): void
    {
        $this->actingAs($this->botswanaRep)
            ->post('/en/directory', $this->person(['authority_id' => 999999]))
            ->assertRedirect();

        $this->assertSame($this->botswanaAuthority->id, AuthorityContact::sole()->authority_id);
    }

    public function test_botswana_cannot_edit_or_delete_a_person_at_mozambiques_authority(): void
    {
        $this->actingAs($this->mozambiqueRep)->post('/en/directory', $this->person(['consented' => 1]));
        $contact = AuthorityContact::sole();

        $this->actingAs($this->botswanaRep)->put('/en/directory/'.$contact->id, $this->person())->assertNotFound();
        $this->actingAs($this->botswanaRep)->delete('/en/directory/'.$contact->id)->assertNotFound();
    }

    public function test_the_directory_is_searchable_by_authority_sector_and_role(): void
    {
        $postal = Sector::where('slug', 'postal')->firstOrFail();

        $this->actingAs($this->botswanaRep)->post('/en/directory', $this->person([
            'consented' => 1,
            'name' => 'A postal officer',
            'role' => 'Head of postal',
            'sectors' => [$postal->id],
        ]));

        $this->actingAs($this->botswanaRep)->post('/en/directory', $this->person([
            'consented' => 1,
            'name' => 'A spectrum officer',
            'role' => 'Head of spectrum',
            'sectors' => [Sector::where('slug', 'telecommunications')->value('id')],
        ]));

        $this->actingAs($this->mozambiqueRep)
            ->get('/en/directory?sector=postal')
            ->assertOk()
            ->assertSee('A postal officer')
            ->assertDontSee('A spectrum officer');

        $this->actingAs($this->mozambiqueRep)
            ->get('/en/directory?q=spectrum')
            ->assertOk()
            ->assertSee('A spectrum officer')
            ->assertDontSee('A postal officer');

        $this->actingAs($this->mozambiqueRep)
            ->get('/en/directory?authority='.$this->botswanaAuthority->id)
            ->assertOk()
            ->assertSee('A postal officer');
    }

    /**
     * Never exported, never on the public site.
     *
     * A directory of fourteen regulators' direct lines is exactly the file
     * that must not leave the extranet, so this reads the codebase rather
     * than trusting that nobody will add an export later.
     */
    public function test_nothing_exports_the_directory_and_no_public_page_reads_it(): void
    {
        $publicViews = File::allFiles(resource_path('views/pages'));

        foreach ($publicViews as $file) {
            $path = str_replace(resource_path('views/pages').'/', '', $file->getPathname());

            if (str_starts_with($path, 'extranet/') || str_starts_with($path, 'admin/')) {
                continue;
            }

            $this->assertStringNotContainsString(
                'AuthorityContact',
                $file->getContents(),
                "[{$path}] reads the members directory. It is members-only and never public."
            );
        }

        // No export route, no report, no CSV.
        foreach (File::allFiles(app_path()) as $file) {
            $contents = $file->getContents();

            if (! str_contains($contents, 'AuthorityContact')) {
                continue;
            }

            foreach (['streamDownload', 'fputcsv', 'toCsv', 'Excel::'] as $export) {
                $this->assertStringNotContainsString(
                    $export,
                    $contents,
                    $file->getFilename().' exports the members directory. It never leaves the extranet.'
                );
            }
        }
    }

    public function test_a_visitor_cannot_reach_the_directory(): void
    {
        $this->get('/en/directory')->assertRedirect();
    }
}
