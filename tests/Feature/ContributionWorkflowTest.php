<?php

namespace Tests\Feature;

use App\Enums\ContributionState;
use App\Models\Country;
use App\Models\Instrument;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\RegulatoryActivity;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A member state contributing a national instrument. RP-03 and RP-06.
 *
 * This is how the register fills: from fourteen members, not from a
 * Secretariat of five typing in fourteen countries' legislation. What these
 * tests hold is the boundary that makes that safe -- Botswana contributes
 * for Botswana and for nobody else -- and the workflow that makes it
 * trustworthy: nothing reaches the register until the Secretariat has said
 * so, and nothing comes back without a reason.
 */
class ContributionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $botswanaRep;

    private User $mozambiqueRep;

    private User $secretariat;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        $this->botswanaRep = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => Country::where('iso_code', 'bw')->value('id'),
        ]);

        $this->mozambiqueRep = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => Country::where('iso_code', 'mz')->value('id'),
        ]);

        $this->secretariat = User::factory()->administrator()->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'reference_number' => 'BW Act 3 of 2024',
            'instrument_type' => 'act',
            'sector_id' => Sector::first()->id,
            'status' => 'in_force',
            'title_en' => 'Botswana communications act',
            'summary_en' => 'The act that establishes the authority.',
        ], $overrides);
    }

    private function contribute(?User $as = null): Instrument
    {
        $this->actingAs($as ?? $this->botswanaRep)
            ->post('/en/contributions', $this->payload())
            ->assertRedirect();

        return Instrument::withContributor()->latest('id')->firstOrFail();
    }

    public function test_a_representative_contributes_an_instrument_as_a_draft(): void
    {
        $instrument = $this->contribute();

        $this->assertSame(ContributionState::Draft, $instrument->contribution_state);
        $this->assertSame($this->botswanaRep->country_id, $instrument->country_id);
        $this->assertSame($this->botswanaRep->id, $instrument->contributed_by);
        $this->assertSame('Botswana communications act', $instrument->translated('title'));
    }

    /**
     * The country is the contributor's own, whatever the request says.
     *
     * There is no country field on the form, so this is a request nobody
     * can make through the interface. It is exactly the request somebody
     * makes with curl.
     */
    public function test_a_representative_cannot_contribute_for_another_member_state(): void
    {
        $mozambique = Country::where('iso_code', 'mz')->value('id');

        $this->actingAs($this->botswanaRep)
            ->post('/en/contributions', $this->payload(['country_id' => $mozambique]))
            ->assertRedirect();

        $instrument = Instrument::withContributor()->latest('id')->firstOrFail();

        $this->assertSame(
            Country::where('iso_code', 'bw')->value('id'),
            $instrument->country_id,
            'A contribution was filed against a member state the contributor does not represent.'
        );
    }

    /** Version history from the first save, not from the first edit. */
    public function test_a_version_is_written_on_the_very_first_save(): void
    {
        $instrument = $this->contribute();

        $this->assertSame(1, $instrument->versions()->count());
        $this->assertNotNull($instrument->current_version_id);
    }

    public function test_every_edit_writes_another_version(): void
    {
        $instrument = $this->contribute();

        $this->actingAs($this->botswanaRep)
            ->put('/en/contributions/'.$instrument->slug, $this->payload(['title_en' => 'Botswana communications act, revised']))
            ->assertRedirect();

        $this->assertSame(2, $instrument->fresh()->versions()->count());
    }

    public function test_a_draft_is_not_in_the_public_register(): void
    {
        $instrument = $this->contribute();

        $this->assertFalse(Instrument::published()->whereKey($instrument->getKey())->exists());
    }

    public function test_validation_puts_it_in_the_register(): void
    {
        $instrument = $this->contribute();

        $this->actingAs($this->botswanaRep)->post('/en/contributions/'.$instrument->slug.'/submit')->assertRedirect();
        $this->assertSame(ContributionState::Submitted, $instrument->fresh()->contribution_state);

        $this->actingAs($this->secretariat)
            ->put('/en/admin/contributions/'.$instrument->slug.'/validate')
            ->assertRedirect();

        $instrument->refresh();

        $this->assertSame(ContributionState::Validated, $instrument->contribution_state);
        $this->assertTrue(Instrument::published()->whereKey($instrument->getKey())->exists());
    }

    public function test_a_contribution_cannot_be_returned_without_a_reason(): void
    {
        $instrument = $this->contribute();
        $this->actingAs($this->botswanaRep)->post('/en/contributions/'.$instrument->slug.'/submit');

        $this->actingAs($this->secretariat)
            ->put('/en/admin/contributions/'.$instrument->slug.'/return', ['review_note' => ''])
            ->assertSessionHasErrors('review_note');

        $this->assertSame(ContributionState::Submitted, $instrument->fresh()->contribution_state);
    }

    public function test_a_returned_contribution_carries_the_reason_and_is_editable_again(): void
    {
        $instrument = $this->contribute();
        $this->actingAs($this->botswanaRep)->post('/en/contributions/'.$instrument->slug.'/submit');

        $this->actingAs($this->secretariat)
            ->put('/en/admin/contributions/'.$instrument->slug.'/return', [
                'review_note' => 'The citation does not match the gazette. Give the section number.',
            ])
            ->assertRedirect();

        $instrument->refresh();

        $this->assertSame(ContributionState::Returned, $instrument->contribution_state);
        $this->assertStringContainsString('gazette', $instrument->review_note);
        $this->assertTrue($instrument->isEditableByContributor());

        $this->actingAs($this->botswanaRep)
            ->get('/en/contributions/'.$instrument->slug)
            ->assertOk()
            ->assertSee('does not match the gazette');
    }

    /** A contribution with the Secretariat is not editable by its author. */
    public function test_a_submitted_contribution_is_locked_to_its_contributor(): void
    {
        $instrument = $this->contribute();
        $this->actingAs($this->botswanaRep)->post('/en/contributions/'.$instrument->slug.'/submit');

        $this->actingAs($this->botswanaRep)
            ->put('/en/contributions/'.$instrument->slug, $this->payload(['title_en' => 'Changed behind their back']))
            ->assertForbidden();
    }

    public function test_a_contribution_cannot_be_validated_without_being_sent(): void
    {
        $instrument = $this->contribute();

        $this->actingAs($this->secretariat)
            ->put('/en/admin/contributions/'.$instrument->slug.'/validate')
            ->assertSessionHasErrors('contribution_state');

        $this->assertSame(ContributionState::Draft, $instrument->fresh()->contribution_state);
    }

    /* The boundary ---------------------------------------------------------- */

    public function test_botswana_cannot_open_or_edit_mozambiques_contribution(): void
    {
        $instrument = $this->contribute($this->mozambiqueRep);

        // A 404 rather than a 403: the request must not confirm that a
        // draft exists in Mozambique.
        $this->actingAs($this->botswanaRep)->get('/en/contributions/'.$instrument->slug)->assertNotFound();
        $this->actingAs($this->botswanaRep)->put('/en/contributions/'.$instrument->slug, $this->payload())->assertNotFound();
        $this->actingAs($this->botswanaRep)->post('/en/contributions/'.$instrument->slug.'/submit')->assertNotFound();
    }

    public function test_botswana_does_not_see_mozambiques_contribution_in_its_own_list(): void
    {
        $this->contribute($this->mozambiqueRep);

        $this->actingAs($this->botswanaRep)
            ->get('/en/contributions')
            ->assertOk()
            ->assertDontSee('Botswana communications act');
    }

    /**
     * Reading is not country-scoped, and that is deliberate. The register
     * exists so fourteen regulators can read each other's instruments,
     * which is the harmonisation mandate.
     */
    public function test_botswana_can_read_mozambiques_instrument_once_it_is_in_the_register(): void
    {
        $instrument = $this->contribute($this->mozambiqueRep);
        $this->actingAs($this->mozambiqueRep)->post('/en/contributions/'.$instrument->slug.'/submit');
        $this->actingAs($this->secretariat)->put('/en/admin/contributions/'.$instrument->slug.'/validate');

        $this->actingAs($this->botswanaRep)
            ->get('/en/register/'.$instrument->slug)
            ->assertOk()
            ->assertSee('Botswana communications act');
    }

    /* Notifications --------------------------------------------------------- */

    public function test_the_contributor_is_told_when_their_contribution_is_returned(): void
    {
        Notification::fake();

        $instrument = $this->contribute();
        $this->actingAs($this->botswanaRep)->post('/en/contributions/'.$instrument->slug.'/submit');

        $this->actingAs($this->secretariat)->put('/en/admin/contributions/'.$instrument->slug.'/return', [
            'review_note' => 'Give the section number.',
        ]);

        Notification::assertSentTo($this->botswanaRep, RegulatoryActivity::class);
    }

    public function test_the_pdf_is_stored_privately_and_never_on_the_public_disk(): void
    {
        $this->actingAs($this->botswanaRep)
            ->post('/en/contributions', $this->payload([
                'file' => UploadedFile::fake()->create('act.pdf', 40, 'application/pdf'),
            ]))
            ->assertRedirect();

        $instrument = Instrument::withContributor()->latest('id')->firstOrFail();
        $document = $instrument->documents()->sole();

        $this->assertStringStartsWith('contributions/', $document->file_path);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame('member', $document->clearance->value);
    }
}
