<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\SubmissionFieldComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The submission workflow: draft, submitted, validated, returned.
 *
 * One class, because a transition is four things that must happen together
 * or not at all: the state changes, an event row is written, the review
 * fields are set, and on validation the figures are published. Splitting
 * that across controllers is how a platform ends up with a validated
 * submission whose figures never reached the dataset.
 */
final class SubmissionWorkflow
{
    /**
     * Move a submission to a new state.
     *
     * Two checks, deliberately separate. Whether the state follows from the
     * current one at all, which stops a draft being validated without ever
     * having been sent. And whether this person may make that particular
     * move, which is the policy's job.
     */
    public function transition(
        Submission $submission,
        SubmissionStatus $target,
        User $actor,
        ?string $note = null,
    ): Submission {
        $from = $submission->status;

        if (! in_array($target, $from->transitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('submissions.transition_invalid', [
                    'from' => __($from->labelKey()),
                    'to' => __($target->labelKey()),
                ]),
            ]);
        }

        // Returning a submission without saying why makes the member state
        // guess. DATA_MODEL says review_note is required when returning,
        // and screen 06 draws the comments as the substance of a return.
        if ($target === SubmissionStatus::Returned && blank($note) && $submission->comments()->where('resolved', false)->doesntExist()) {
            throw ValidationException::withMessages([
                'review_note' => __('submissions.return_needs_reason'),
            ]);
        }

        if ($target === SubmissionStatus::Submitted) {
            $this->assertReadyToSubmit($submission);
        }

        return DB::transaction(function () use ($submission, $from, $target, $actor, $note) {
            $submission->fill([
                'status' => $target,
                'review_note' => $target->isReviewDecision() ? $note : $submission->review_note,
            ]);

            if ($target === SubmissionStatus::Submitted) {
                $submission->submitted_at = now();
                $submission->submitted_by = $actor->id;
            }

            if ($target->isReviewDecision()) {
                $submission->reviewed_at = now();
                $submission->reviewed_by = $actor->id;
            }

            $submission->save();

            SubmissionEvent::create([
                'submission_id' => $submission->id,
                'from_status' => $from->value,
                'to_status' => $target->value,
                'actor_id' => $actor->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            if ($target === SubmissionStatus::Validated) {
                $this->publish($submission, $actor);
            }

            /*
             * Resubmitting resolves the comments it was returned with.
             * Leaving them open would mean a representative who fixed
             * everything still sees a list of complaints, and the
             * Secretariat cannot tell which round a comment belongs to.
             */
            if ($target === SubmissionStatus::Submitted && $from === SubmissionStatus::Returned) {
                $submission->comments()->where('resolved', false)->update(['resolved' => true]);
            }

            return $submission->refresh();
        });
    }

    /**
     * Validation publishes the figures.
     *
     * This is the only place a submission's figures reach
     * indicator_values, which is the table the public comparator and the
     * completeness matrix read. Before this moment a member state's return
     * is visible to that member state and the Secretariat and nobody else.
     *
     * Written with updateOrCreate on (indicator, country, period) because a
     * period may legitimately be validated twice: a correction validated
     * later should replace the figure, not add a second one.
     */
    private function publish(Submission $submission, User $actor): void
    {
        foreach ($submission->values as $value) {
            if (! $value->hasFigure()) {
                // An empty field publishes as nothing at all, not as zero.
                // Screen 06 says so in its own returned comment: "An empty
                // value is published as Not reported."
                continue;
            }

            IndicatorValue::updateOrCreate(
                [
                    'indicator_id' => $value->indicator_id,
                    'country_id' => $submission->country_id,
                    'period' => $submission->period,
                ],
                [
                    'value_numeric' => $value->value_numeric,
                    'value_text' => $value->value_text,
                    'currency' => $value->currency,
                    'source_note' => $value->source_note,
                    'submission_id' => $submission->id,
                    'confidence' => 'validated',
                    'is_sample' => $submission->is_sample,
                ]
            );
        }
    }

    /**
     * A return is not sendable while a figure carries no source.
     *
     * Screen 06 draws the empty source field with a red rule and the words
     * "Source note required", and the Secretariat's own returned comment
     * asks for the data behind a revision. Catching it here saves a round
     * trip that would otherwise cost a week.
     */
    private function assertReadyToSubmit(Submission $submission): void
    {
        $unsourced = $submission->values
            ->filter(fn ($value) => $value->hasFigure() && blank($value->source_note))
            ->map(fn ($value) => $value->indicator?->name)
            ->filter()
            ->values();

        if ($unsourced->isNotEmpty()) {
            throw ValidationException::withMessages([
                'values' => __('submissions.sources_missing', ['fields' => $unsourced->implode(', ')]),
            ]);
        }

        if ($submission->values->filter->hasFigure()->isEmpty()) {
            throw ValidationException::withMessages([
                'values' => __('submissions.nothing_to_submit'),
            ]);
        }
    }

    /**
     * Validation rules for one figure, from the indicator's own data type.
     *
     * The taxonomy says what each indicator is; the form must not be able
     * to say otherwise. A percentage above 100 is allowed, because mobile
     * penetration routinely exceeds it -- the check is that it is a number,
     * not that somebody's intuition about the range holds.
     *
     * @return array<string, mixed>
     */
    public function rulesFor(Indicator $indicator, string $key): array
    {
        $value = match ($indicator->data_type) {
            'numeric', 'percentage', 'currency' => ['nullable', 'numeric'],
            'boolean' => ['nullable', 'in:0,1'],
            'date' => ['nullable', 'date'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            default => ['nullable', 'string', 'max:500'],
        };

        return [
            $key.'.value' => $value,
            $key.'.source_note' => ['nullable', 'string', 'max:500'],
            $key.'.currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    /**
     * Open a return for a member state and period, or find the existing one.
     *
     * One per country per period, enforced by a unique index as well: two
     * returns for the same period would make "the 2026 return from Zambia"
     * ambiguous, and the ambiguity would surface at the worst moment.
     */
    public function open(int $countryId, string $period, User $actor): Submission
    {
        return Submission::firstOrCreate(
            ['country_id' => $countryId, 'period' => $period],
            ['status' => SubmissionStatus::Draft, 'submitted_by' => $actor->id]
        );
    }

    /** A field comment, and the return that carries it. */
    public function comment(Submission $submission, Indicator $indicator, User $author, string $comment): SubmissionFieldComment
    {
        return SubmissionFieldComment::create([
            'submission_id' => $submission->id,
            'indicator_id' => $indicator->id,
            'author_id' => $author->id,
            'comment' => $comment,
        ]);
    }
}
