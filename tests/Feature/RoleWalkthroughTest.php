<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Enums\CommitteeRole;
use App\Enums\SubmissionStatus;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Indicator;
use App\Models\Meeting;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The two paths a member state and a committee actually walk, end to end.
 *
 * The submission workflow is the platform's spine and its states are
 * contractual: draft, submitted, validated, returned, every transition
 * audited. This walks the whole of it in one test, in the order a
 * representative and the Secretariat walk it, because a workflow tested one
 * transition at a time passes while the sequence is broken.
 *
 * The second half is the boundary. Botswana and Mozambique file separately,
 * and neither may reach the other's return, folder or document. That is
 * asserted by requesting the other's records directly, because a hidden row
 * is not access control.
 */
class RoleWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $botswana;

    private User $mozambique;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $this->admin = User::factory()->administrator()->create();
        $this->botswana = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();
        $this->mozambique = User::factory()->memberStateRep(Country::where('iso_code', 'mz')->sole())->create();
    }

    /* The e-Form, all the way round ------------------------------------- */

    public function test_a_return_goes_draft_submitted_returned_resubmitted_validated(): void
    {
        $indicators = Indicator::active()->orderBy('display_order')->take(3)->get();
        $this->assertCount(3, $indicators, 'The taxonomy seeded fewer than three indicators.');

        // 1. Botswana opens a return for the period.
        $this->actingAs($this->botswana)
            ->post('/en/submissions', ['period' => (string) now()->year])
            ->assertRedirect();

        $submission = Submission::sole();
        $this->assertSame(SubmissionStatus::Draft, $submission->status);
        $this->assertSame($this->botswana->country_id, $submission->country_id);

        // 2. Three figures, saved as a draft.
        $values = [];

        foreach ($indicators as $i => $indicator) {
            $values[$indicator->id] = [
                'value' => in_array($indicator->data_type, ['numeric', 'percentage', 'currency', 'boolean', 'year'], true)
                    ? (string) (10 + $i)
                    : 'Reported by BOCRA',
                'source_note' => 'BOCRA annual return',
            ];
        }

        $this->actingAs($this->botswana)
            ->put("/en/submissions/{$submission->id}", ['values' => $values])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $submission->fresh()->values()->count());
        $this->assertSame(SubmissionStatus::Draft, $submission->fresh()->status);

        // 3. Submitted.
        $this->actingAs($this->botswana)
            ->post("/en/submissions/{$submission->id}/submit")
            ->assertRedirect();

        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);

        // 4. The Secretariat sees it in the queue.
        $this->actingAs($this->admin)->get('/en/admin/submissions')
            ->assertOk()
            ->assertSee('Botswana');

        $this->actingAs($this->admin)->get("/en/admin/submissions/{$submission->id}")->assertOk();

        // 5. Returned, with a comment against one field.
        $this->actingAs($this->admin)
            ->post("/en/admin/submissions/{$submission->id}/comment", [
                'indicator_id' => $indicators[0]->id,
                'comment' => 'This figure is for the whole year. State the quarter it covers.',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post("/en/admin/submissions/{$submission->id}/decide", [
                'status' => SubmissionStatus::Returned->value,
                'note' => 'One figure needs its period stated.',
            ])
            ->assertRedirect();

        $this->assertSame(SubmissionStatus::Returned, $submission->fresh()->status);

        // 6. Botswana sees the comment against the field it belongs to.
        $this->actingAs($this->botswana)->get("/en/submissions/{$submission->id}")
            ->assertOk()
            ->assertSee('State the quarter it covers', false);

        // 7. Corrected and resubmitted.
        $values[$indicators[0]->id]['source_note'] = 'BOCRA annual return, Q4';

        $this->actingAs($this->botswana)
            ->put("/en/submissions/{$submission->id}", ['values' => $values])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->botswana)
            ->post("/en/submissions/{$submission->id}/submit")
            ->assertRedirect();

        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);

        // 8. Validated.
        $this->actingAs($this->admin)
            ->post("/en/admin/submissions/{$submission->id}/decide", [
                'status' => SubmissionStatus::Validated->value,
            ])
            ->assertRedirect();

        $this->assertSame(SubmissionStatus::Validated, $submission->fresh()->status);

        // Every transition wrote an event. Four of them, in order.
        $this->assertSame(
            ['submitted', 'returned', 'submitted', 'validated'],
            $submission->fresh()->events()->orderBy('id')->pluck('to_status')->all()
        );
    }

    /** A validated figure reaches the public comparator. */
    public function test_a_validated_figure_reaches_the_public_side(): void
    {
        $indicator = Indicator::active()->orderBy('display_order')->firstOrFail();

        $this->actingAs($this->botswana)->post('/en/submissions', ['period' => (string) now()->year]);
        $submission = Submission::sole();

        $this->actingAs($this->botswana)->put("/en/submissions/{$submission->id}", [
            'values' => [$indicator->id => ['value' => '42', 'source_note' => 'BOCRA']],
        ]);

        $this->actingAs($this->botswana)->post("/en/submissions/{$submission->id}/submit");
        $this->actingAs($this->admin)->post("/en/admin/submissions/{$submission->id}/decide", [
            'status' => SubmissionStatus::Validated->value,
        ]);

        // The workflow copies validated figures into indicator_values,
        // which is what the comparator and the member pages read.
        $this->assertDatabaseHas('indicator_values', [
            'indicator_id' => $indicator->id,
            'country_id' => $this->botswana->country_id,
            'period' => (string) now()->year,
        ]);

        $this->get('/en/comparator?countries[]=bw&indicator='.$indicator->slug)->assertOk();
    }

    /* The boundary ------------------------------------------------------- */

    public function test_botswana_cannot_reach_mozambiques_return(): void
    {
        $this->actingAs($this->mozambique)->post('/en/submissions', ['period' => (string) now()->year]);
        $theirs = Submission::sole();

        /*
         * 404, not 403, and that is the stronger answer.
         *
         * Submission carries a country scope, so another member state's
         * return is not merely refused: it is not found. A 403 would
         * confirm that a return exists for Mozambique in that period, which
         * is itself a fact about another regulator's filing.
         */
        $this->actingAs($this->botswana)->get("/en/submissions/{$theirs->id}")->assertNotFound();
        $this->actingAs($this->botswana)->put("/en/submissions/{$theirs->id}", ['values' => []])->assertNotFound();
        $this->actingAs($this->botswana)->post("/en/submissions/{$theirs->id}/submit")->assertNotFound();

        // And the record is genuinely still there, so the 404 is the scope
        // rather than a deletion.
        $this->assertNotNull(Submission::withoutGlobalScopes()->find($theirs->id));

        // And not by listing them. The scope is on the query, so it is not
        // there to be missed.
        $this->actingAs($this->botswana)->get('/en/submissions')
            ->assertOk()
            ->assertDontSee('Mozambique');
    }

    public function test_neither_representative_reaches_the_others_folder_or_document(): void
    {
        $mozambique = Country::where('iso_code', 'mz')->sole();
        $botswana = Country::where('iso_code', 'bw')->sole();

        $theirs = Folder::create([
            'name' => 'Mozambique country returns',
            'slug' => 'mz-country-returns',
            'country_id' => $mozambique->id,
            'clearance' => Clearance::Member,
            'is_sample' => true,
        ]);

        $mine = Folder::create([
            'name' => 'Botswana country returns',
            'slug' => 'bw-country-returns',
            'country_id' => $botswana->id,
            'clearance' => Clearance::Member,
            'is_sample' => true,
        ]);

        $document = Document::create([
            'documentable_type' => Folder::class,
            'documentable_id' => $theirs->id,
            'folder_id' => $theirs->id,
            'file_path' => 'legacy/mz-return.pdf',
            'original_filename' => 'INCM annual return.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'locale' => 'en',
            'clearance' => Clearance::Member,
            'is_sample' => true,
        ]);

        // Their folder is not reachable and not listed.
        // Not found rather than refused, for the same reason: the scope
        // makes it unfindable and a 403 would confirm it exists.
        $this->actingAs($this->botswana)->get("/en/extranet/folders/{$theirs->id}")->assertNotFound();
        $this->actingAs($this->botswana)->get("/en/documents/{$document->id}")->assertForbidden();

        $html = $this->actingAs($this->botswana)->get('/en/extranet/folders')->assertOk()->getContent();
        $this->assertStringNotContainsString('Mozambique country returns', $html);
        $this->assertStringContainsString('Botswana country returns', $html);

        // And it does not turn up in search either, which is the listing
        // people forget to scope.
        $this->actingAs($this->botswana)->get('/en/search?q=INCM')
            ->assertOk()
            ->assertDontSee('INCM annual return.pdf');

        // The mirror image holds.
        $this->actingAs($this->mozambique)->get("/en/extranet/folders/{$mine->id}")->assertNotFound();
        $this->actingAs($this->mozambique)->get("/en/extranet/folders/{$theirs->id}")->assertOk();
    }

    /* The committee workspace -------------------------------------------- */

    public function test_a_committee_member_reads_their_papers_at_their_clearance(): void
    {
        $zambia = Country::where('iso_code', 'zm')->sole();
        $member = User::factory()->committeeMember($zambia)->create();
        $committee = Committee::where('slug', 'spectrum-and-frequency-management')->sole();

        $member->committees()->attach($committee->id, [
            'role' => CommitteeRole::Member->value,
            'is_sample' => true,
        ]);

        $folder = Folder::create([
            'name' => 'Spectrum working papers',
            'slug' => 'spectrum-working-papers',
            'committee_id' => $committee->id,
            'clearance' => Clearance::Confidential,
            'is_sample' => true,
        ]);

        $confidential = Document::create([
            'documentable_type' => Folder::class,
            'documentable_id' => $folder->id,
            'folder_id' => $folder->id,
            'committee_id' => $committee->id,
            'file_path' => 'legacy/spectrum-draft.pdf',
            'original_filename' => 'Draft spectrum determination.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
            'locale' => 'en',
            'clearance' => Clearance::Confidential,
            'is_sample' => true,
        ]);

        $secretariat = Document::create([
            'documentable_type' => Folder::class,
            'documentable_id' => $folder->id,
            'folder_id' => $folder->id,
            'committee_id' => $committee->id,
            'file_path' => 'legacy/secretariat-note.pdf',
            'original_filename' => 'Secretariat budget note.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 512,
            'locale' => 'en',
            'clearance' => Clearance::Secretariat,
            'is_sample' => true,
        ]);

        Meeting::create([
            'committee_id' => $committee->id,
            'slug' => 'sfm-41st-session',
            'title' => 'Spectrum and Frequency Management, 41st session',
            'starts_on' => now()->addWeeks(2)->toDateString(),
            'ends_on' => now()->addWeeks(2)->addDay()->toDateString(),
            'location' => 'Gaborone',
            'kind' => 'committee',
            'clearance' => Clearance::Member,
            'is_sample' => true,
        ]);

        // The workspace opens and names its committee.
        $this->actingAs($member)->get('/en/extranet/committees/'.$committee->slug)
            ->assertOk()
            ->assertSee($committee->name);

        // A confidential paper of their own committee opens.
        $this->assertTrue($member->can('view', $confidential));

        // A secretariat-clearance paper does not, even in their own
        // committee. Clearance is a grade, not a location.
        $this->assertFalse($member->can('view', $secretariat));
        $this->actingAs($member)->get("/en/documents/{$secretariat->id}")->assertForbidden();

        // And it is not listed for them either.
        $html = $this->actingAs($member)->get("/en/extranet/folders/{$folder->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Draft spectrum determination.pdf', $html);
        $this->assertStringNotContainsString('Secretariat budget note.pdf', $html);

        // The calendar carries their meeting.
        $this->actingAs($member)->get('/en/extranet/calendar')
            ->assertOk()
            ->assertSee('41st session', false);
    }

    /** A seat on one committee is not a seat on another's papers. */
    public function test_a_committee_member_cannot_read_another_committees_confidential_paper(): void
    {
        $zambia = Country::where('iso_code', 'zm')->sole();
        $member = User::factory()->committeeMember($zambia)->create();

        $mine = Committee::where('slug', 'spectrum-and-frequency-management')->sole();
        $theirs = Committee::where('slug', 'postal-and-logistics')->sole();

        $member->committees()->attach($mine->id, ['role' => CommitteeRole::Member->value, 'is_sample' => true]);

        $folder = Folder::create([
            'name' => 'Postal working papers',
            'slug' => 'postal-working-papers',
            'committee_id' => $theirs->id,
            'clearance' => Clearance::Confidential,
            'is_sample' => true,
        ]);

        $document = Document::create([
            'documentable_type' => Folder::class,
            'documentable_id' => $folder->id,
            'folder_id' => $folder->id,
            'committee_id' => $theirs->id,
            'file_path' => 'legacy/postal-draft.pdf',
            'original_filename' => 'Draft postal tariff paper.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 700,
            'locale' => 'en',
            'clearance' => Clearance::Confidential,
            'is_sample' => true,
        ]);

        $this->assertFalse($member->can('view', $document));
        $this->actingAs($member)->get("/en/documents/{$document->id}")->assertForbidden();
        $this->actingAs($member)->get('/en/extranet/committees/'.$theirs->slug)->assertForbidden();
    }

    /**
     * An observer attends but does not hold the papers. DATA_MODEL section
     * 7: clearance derives from the seat, not from the person.
     */
    public function test_an_observer_does_not_hold_the_committees_confidential_papers(): void
    {
        $zambia = Country::where('iso_code', 'zm')->sole();
        $observer = User::factory()->committeeMember($zambia)->create();
        $committee = Committee::where('slug', 'spectrum-and-frequency-management')->sole();

        $observer->committees()->attach($committee->id, [
            'role' => CommitteeRole::Observer->value,
            'is_sample' => true,
        ]);

        $document = Document::create([
            'documentable_type' => Committee::class,
            'documentable_id' => $committee->id,
            'committee_id' => $committee->id,
            'file_path' => 'legacy/spectrum-confidential.pdf',
            'original_filename' => 'Confidential spectrum annex.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 900,
            'locale' => 'en',
            'clearance' => Clearance::Confidential,
            'is_sample' => true,
        ]);

        $this->assertFalse($observer->can('view', $document));
    }
}
