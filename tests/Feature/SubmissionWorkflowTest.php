<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\User;
use App\Notifications\SubmissionStateChanged;
use App\Services\SubmissionWorkflow;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The e-Form and its workflow, RP-09.
 *
 * The negative paths carry the weight here. A submission system that lets
 * one member state read or edit another's return is not a bug, it is a
 * diplomatic incident, and this is exactly where a permissions layer
 * usually leaks: the read is scoped and the write is forgotten.
 */
class SubmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Country $botswana;

    private Country $zambia;

    private User $botswanaRep;

    private User $zambiaRep;

    private User $secretariat;

    private Indicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $this->botswana = Country::where('iso_code', 'bw')->sole();
        $this->zambia = Country::where('iso_code', 'zm')->sole();

        $this->botswanaRep = User::factory()->memberStateRep($this->botswana)->create();
        $this->zambiaRep = User::factory()->memberStateRep($this->zambia)->create();
        $this->secretariat = User::factory()->administrator()->create();

        $this->indicator = Indicator::active()->orderBy('display_order')->firstOrFail();
    }

    private function openReturn(Country $country, User $actor, string $period = '2026'): Submission
    {
        return app(SubmissionWorkflow::class)->open($country->id, $period, $actor);
    }

    private function fill(Submission $submission, ?string $source = 'BOCRA register 2026'): Submission
    {
        $submission->values()->updateOrCreate(
            ['indicator_id' => $this->indicator->id],
            ['value_numeric' => 42, 'source_note' => $source]
        );

        return $submission->refresh()->load('values.indicator');
    }

    /* Country scope, read side ------------------------------------------------ */

    public function test_a_representative_sees_only_their_own_country_returns(): void
    {
        $this->openReturn($this->botswana, $this->botswanaRep);
        $this->openReturn($this->zambia, $this->zambiaRep);

        $this->actingAs($this->botswanaRep);

        // The global scope, not a controller check. It holds in a job, a
        // report and a console command as well as here.
        $this->assertSame(
            [$this->botswana->id],
            Submission::query()->pluck('country_id')->unique()->values()->all()
        );
    }

    public function test_a_representative_cannot_open_another_countrys_return(): void
    {
        $zambian = $this->openReturn($this->zambia, $this->zambiaRep);

        $this->actingAs($this->botswanaRep)
            ->get('/en/submissions/'.$zambian->id)
            // The scope hides the row entirely, so it is a 404 rather than
            // a 403: a 403 would confirm that the Zambian return exists.
            ->assertNotFound();
    }

    /* Country scope, write side ----------------------------------------------- */

    public function test_a_representative_cannot_edit_another_countrys_return(): void
    {
        $zambian = $this->openReturn($this->zambia, $this->zambiaRep);

        $this->actingAs($this->botswanaRep)
            ->put('/en/submissions/'.$zambian->id, [
                'values' => [$this->indicator->id => ['value' => '999', 'source_note' => 'invented']],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('submission_values', ['submission_id' => $zambian->id]);
    }

    public function test_a_representative_cannot_submit_another_countrys_return(): void
    {
        $zambian = $this->fill($this->openReturn($this->zambia, $this->zambiaRep));

        $this->actingAs($this->botswanaRep)
            ->post('/en/submissions/'.$zambian->id.'/submit')
            ->assertNotFound();

        $this->assertSame(SubmissionStatus::Draft, $zambian->fresh()->status);
    }

    public function test_a_representative_cannot_validate_their_own_return(): void
    {
        $own = $this->fill($this->openReturn($this->botswana, $this->botswanaRep));

        app(SubmissionWorkflow::class)->transition($own, SubmissionStatus::Submitted, $this->botswanaRep);

        // Filing and approving are different acts by different people. A
        // representative who could do both would be publishing their own
        // figures into the regional dataset unreviewed.
        $this->actingAs($this->botswanaRep)
            ->post('/en/admin/submissions/'.$own->id.'/decide', ['status' => 'validated'])
            ->assertForbidden();

        $this->assertSame(SubmissionStatus::Submitted, $own->fresh()->status);
    }

    public function test_a_viewer_reaches_no_part_of_the_workflow(): void
    {
        $viewer = User::factory()->viewer()->create();
        $submission = $this->openReturn($this->botswana, $this->botswanaRep);

        $this->actingAs($viewer)->get('/en/submissions')->assertForbidden();
        $this->actingAs($viewer)->get('/en/admin/submissions')->assertForbidden();
        $this->actingAs($viewer)->get('/en/submissions/'.$submission->id)->assertNotFound();
    }

    /* The workflow ------------------------------------------------------------ */

    public function test_the_four_states_run_in_order_and_each_writes_an_event(): void
    {
        $submission = $this->fill($this->openReturn($this->botswana, $this->botswanaRep));
        $workflow = app(SubmissionWorkflow::class);

        $workflow->transition($submission, SubmissionStatus::Submitted, $this->botswanaRep);
        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);

        $workflow->transition($submission->fresh()->load('values', 'comments'), SubmissionStatus::Returned, $this->secretariat, 'Check the operator data.');
        $this->assertSame(SubmissionStatus::Returned, $submission->fresh()->status);

        $workflow->transition($submission->fresh()->load('values.indicator', 'comments'), SubmissionStatus::Submitted, $this->botswanaRep);
        $workflow->transition($submission->fresh()->load('values', 'comments'), SubmissionStatus::Validated, $this->secretariat);

        $this->assertSame(SubmissionStatus::Validated, $submission->fresh()->status);

        // CLAUDE.md: "Every transition writes to the audit log."
        $this->assertSame(
            ['submitted', 'returned', 'submitted', 'validated'],
            SubmissionEvent::where('submission_id', $submission->id)->orderBy('id')->pluck('to_status')->all()
        );
    }

    public function test_a_draft_cannot_jump_straight_to_validated(): void
    {
        $submission = $this->fill($this->openReturn($this->botswana, $this->botswanaRep));

        $this->expectException(ValidationException::class);

        app(SubmissionWorkflow::class)->transition($submission, SubmissionStatus::Validated, $this->secretariat);
    }

    public function test_a_validated_return_is_terminal(): void
    {
        $this->assertSame([], SubmissionStatus::Validated->transitions());
    }

    public function test_returning_without_a_reason_is_refused(): void
    {
        $submission = $this->fill($this->openReturn($this->botswana, $this->botswanaRep));
        $workflow = app(SubmissionWorkflow::class);

        $workflow->transition($submission, SubmissionStatus::Submitted, $this->botswanaRep);

        // A return with no reason makes the member state guess, which costs
        // a round trip measured in weeks.
        $this->expectException(ValidationException::class);

        $workflow->transition($submission->fresh()->load('values', 'comments'), SubmissionStatus::Returned, $this->secretariat);
    }

    /* Publication ------------------------------------------------------------- */

    public function test_an_unvalidated_return_is_invisible_to_the_public_comparator(): void
    {
        $submission = $this->fill($this->openReturn($this->botswana, $this->botswanaRep));
        app(SubmissionWorkflow::class)->transition($submission, SubmissionStatus::Submitted, $this->botswanaRep);

        // The figures are in submission_values, not indicator_values. That
        // separation is what keeps the public comparator a simple, safe
        // read with no country scope on it at all.
        $this->assertDatabaseCount('indicator_values', 0);

        $this->get('/en/comparator?'.http_build_query([
            'mode' => 'compare', 'category' => $this->indicator->category, 'countries' => 'bw,zm',
        ]))->assertOk()->assertDontSee('42<');
    }

    public function test_validating_publishes_the_figures_with_their_sources(): void
    {
        $submission = $this->fill($this->openReturn($this->botswana, $this->botswanaRep));
        $workflow = app(SubmissionWorkflow::class);

        $workflow->transition($submission, SubmissionStatus::Submitted, $this->botswanaRep);
        $workflow->transition($submission->fresh()->load('values', 'comments'), SubmissionStatus::Validated, $this->secretariat);

        $published = IndicatorValue::where('indicator_id', $this->indicator->id)
            ->where('country_id', $this->botswana->id)
            ->sole();

        $this->assertSame('42.0000', $published->value_numeric);
        $this->assertSame('BOCRA register 2026', $published->source_note);
        $this->assertSame('validated', $published->confidence);
        $this->assertSame($submission->id, $published->submission_id);
    }

    public function test_an_empty_field_publishes_as_nothing_rather_than_as_zero(): void
    {
        $submission = $this->openReturn($this->botswana, $this->botswanaRep);
        $this->fill($submission);

        // A second indicator left empty.
        $second = Indicator::active()->where('id', '!=', $this->indicator->id)->firstOrFail();
        $submission->values()->create(['indicator_id' => $second->id, 'source_note' => null]);

        $workflow = app(SubmissionWorkflow::class);
        $workflow->transition($submission->fresh()->load('values.indicator'), SubmissionStatus::Submitted, $this->botswanaRep);
        $workflow->transition($submission->fresh()->load('values', 'comments'), SubmissionStatus::Validated, $this->secretariat);

        // Screen 06: "An empty value is published as Not reported." Not as
        // a zero, and not as a row saying zero.
        $this->assertDatabaseMissing('indicator_values', [
            'indicator_id' => $second->id,
            'country_id' => $this->botswana->id,
        ]);
    }

    /* Sources ----------------------------------------------------------------- */

    public function test_a_figure_without_a_source_cannot_be_sent(): void
    {
        $submission = $this->fill($this->openReturn($this->botswana, $this->botswanaRep), source: null);

        $this->actingAs($this->botswanaRep)
            ->from('/en/submissions/'.$submission->id)
            ->post('/en/submissions/'.$submission->id.'/submit')
            ->assertSessionHasErrors('values');

        $this->assertSame(SubmissionStatus::Draft, $submission->fresh()->status);
    }

    public function test_an_empty_return_cannot_be_sent(): void
    {
        $submission = $this->openReturn($this->botswana, $this->botswanaRep);

        $this->actingAs($this->botswanaRep)
            ->from('/en/submissions/'.$submission->id)
            ->post('/en/submissions/'.$submission->id.'/submit')
            ->assertSessionHasErrors('values');
    }

    /* Validation against the indicator's own type ------------------------------ */

    public function test_a_numeric_indicator_refuses_a_word(): void
    {
        $submission = $this->openReturn($this->botswana, $this->botswanaRep);

        $this->actingAs($this->botswanaRep)
            ->from('/en/submissions/'.$submission->id)
            ->put('/en/submissions/'.$submission->id, [
                'values' => [$this->indicator->id => ['value' => 'about a hundred', 'source_note' => 'x']],
            ])
            ->assertSessionHasErrors('values.'.$this->indicator->id.'.value');
    }

    /* Field comments ---------------------------------------------------------- */

    public function test_a_returned_submission_carries_comments_against_specific_fields(): void
    {
        $submission = $this->fill($this->openReturn($this->botswana, $this->botswanaRep));
        $workflow = app(SubmissionWorkflow::class);
        $workflow->transition($submission, SubmissionStatus::Submitted, $this->botswanaRep);

        $this->actingAs($this->secretariat)
            ->from('/en/admin/submissions/'.$submission->id)
            ->post('/en/admin/submissions/'.$submission->id.'/comment', [
                'indicator_id' => $this->indicator->id,
                'comment' => 'This is materially above the 2025 return. Attach the operator data.',
            ])
            ->assertRedirect();

        $workflow->transition($submission->fresh()->load('values', 'comments'), SubmissionStatus::Returned, $this->secretariat);

        // The representative sees what to fix and where, rather than
        // re-reading every indicator.
        $this->actingAs($this->botswanaRep)
            ->get('/en/submissions/'.$submission->id)
            ->assertOk()
            ->assertSee('Attach the operator data.')
            ->assertSee($this->indicator->name);
    }

    public function test_resubmitting_resolves_the_comments_it_was_returned_with(): void
    {
        $submission = $this->fill($this->openReturn($this->botswana, $this->botswanaRep));
        $workflow = app(SubmissionWorkflow::class);

        $workflow->transition($submission, SubmissionStatus::Submitted, $this->botswanaRep);
        $workflow->comment($submission, $this->indicator, $this->secretariat, 'Please correct.');
        $workflow->transition($submission->fresh()->load('values', 'comments'), SubmissionStatus::Returned, $this->secretariat);

        $workflow->transition($submission->fresh()->load('values.indicator', 'comments'), SubmissionStatus::Submitted, $this->botswanaRep);

        $this->assertSame(0, $submission->comments()->where('resolved', false)->count());
    }

    /* Notifications, RP-05 ---------------------------------------------------- */

    public function test_a_state_change_notifies_the_member_state(): void
    {
        Notification::fake();

        $submission = $this->fill($this->openReturn($this->botswana, $this->botswanaRep));
        app(SubmissionWorkflow::class)->transition($submission, SubmissionStatus::Submitted, $this->botswanaRep);

        $this->actingAs($this->secretariat)
            ->post('/en/admin/submissions/'.$submission->id.'/decide', [
                'status' => 'returned',
                'note' => 'Attach the operator data behind the revision.',
            ]);

        Notification::assertSentTo($this->botswanaRep, SubmissionStateChanged::class);
    }

    /**
     * RP-05 is regulatory content. Event and travel notifications are
     * reserved commercial scope, and there is deliberately no shared base
     * class for someone to hang a meeting reminder off later.
     *
     * WHY THIS IS AN ALLOWLIST RATHER THAN A COUNT OF ONE. The platform now
     * has more than one regulatory notification, and a test that just
     * counted them would pass the day somebody added MeetingReminder. Every
     * name below is here because it carries regulatory content, and adding
     * a name is the deliberate act this test exists to force.
     */
    public function test_every_notification_on_the_platform_is_about_regulatory_data(): void
    {
        $allowed = [
            // A member state's data return was validated or returned.
            'SubmissionStateChanged',
            // The Secretariat told the membership something about the work.
            'AnnouncementPublished',
            // A document, instrument or comment reached the reader's own
            // committee or sector. RP-05's triggers, none of them logistical.
            'RegulatoryActivity',
        ];

        $classes = array_map(
            fn ($file) => $file->getFilenameWithoutExtension(),
            File::files(app_path('Notifications'))
        );

        sort($classes);
        sort($allowed);

        $this->assertSame(
            $allowed,
            $classes,
            'A notification class was added or removed. If it is about a meeting, a journey or a room, it does not belong in this codebase.'
        );
    }

    /* The trail is a trail ---------------------------------------------------- */

    public function test_a_submission_event_cannot_be_edited_or_deleted(): void
    {
        $submission = $this->fill($this->openReturn($this->botswana, $this->botswanaRep));
        app(SubmissionWorkflow::class)->transition($submission, SubmissionStatus::Submitted, $this->botswanaRep);

        $event = SubmissionEvent::where('submission_id', $submission->id)->sole();

        try {
            $event->update(['to_status' => 'validated']);
            $this->fail('A submission event was edited.');
        } catch (\RuntimeException) {
            // Expected.
        }

        try {
            $event->delete();
            $this->fail('A submission event was deleted.');
        } catch (\RuntimeException) {
            // Expected.
        }

        $this->assertSame('submitted', $event->fresh()->to_status);
    }

    public function test_one_return_per_member_state_per_period(): void
    {
        $first = $this->openReturn($this->botswana, $this->botswanaRep);
        $again = $this->openReturn($this->botswana, $this->botswanaRep);

        // "The 2026 return from Botswana" has to mean one thing.
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Submission::withoutGlobalScopes()->where('country_id', $this->botswana->id)->count());
    }
}
