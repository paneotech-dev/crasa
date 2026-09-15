<?php

namespace App\Services;

use App\Enums\ContributionState;
use App\Enums\NotificationTrigger;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Models\User;
use App\Notifications\RegulatoryActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A member state's contribution moving through review. RP-03, RP-06.
 *
 * ONE CLASS, FOR THE SAME REASON AS SubmissionWorkflow. A transition is
 * several things that have to happen together or not at all: the state
 * changes, a version is written, the review fields are set, and on
 * validation the instrument enters the public register. Splitting that
 * across controllers is how a register ends up with a validated instrument
 * that nobody can find.
 *
 * VERSION HISTORY FROM THE FIRST SAVE. DATA_MODEL: never update an
 * instrument's text in place. That rule does not start at validation, it
 * starts at the first draft, because the useful question later is what the
 * contributor changed after the Secretariat sent it back.
 *
 * RETURNING WITHOUT A REASON IS REFUSED. A contributor who gets their work
 * back with no note has to guess, and guessing is how a contribution comes
 * back a second time with the same fault.
 */
final class ContributionWorkflow
{
    public function __construct(private readonly NotifiesMembers $notifier) {}

    /**
     * Write a version of whatever the instrument now says.
     *
     * Called on every save, including the first, so the history is complete
     * rather than starting at whichever edit somebody thought was worth
     * keeping.
     */
    public function recordVersion(Instrument $instrument, User $actor, string $summary): InstrumentVersion
    {
        return DB::transaction(function () use ($instrument, $actor, $summary): InstrumentVersion {
            $number = (int) $instrument->versions()->max('version_number') + 1;

            $version = $instrument->versions()->create([
                'version_number' => $number,
                'changed_by' => $actor->id,
                'change_summary' => $summary,
                'payload' => $this->snapshot($instrument),
            ]);

            $instrument->forceFill(['current_version_id' => $version->id])->save();

            return $version;
        });
    }

    public function transition(
        Instrument $instrument,
        ContributionState $target,
        User $actor,
        ?string $note = null,
    ): Instrument {
        $from = $instrument->contribution_state ?? ContributionState::Draft;

        if (! in_array($target, $from->transitions(), true)) {
            throw ValidationException::withMessages([
                'contribution_state' => __('contributions.transition_invalid', [
                    'from' => __($from->labelKey()),
                    'to' => __($target->labelKey()),
                ]),
            ]);
        }

        if ($target === ContributionState::Returned && blank($note)) {
            throw ValidationException::withMessages([
                'review_note' => __('contributions.return_needs_reason'),
            ]);
        }

        return DB::transaction(function () use ($instrument, $target, $actor, $note, $from): Instrument {
            $instrument->forceFill(array_filter([
                'contribution_state' => $target->value,
                'submitted_at' => $target === ContributionState::Submitted ? now() : $instrument->submitted_at,
                'reviewed_by' => in_array($target, [ContributionState::Validated, ContributionState::Returned], true) ? $actor->id : $instrument->reviewed_by,
                'reviewed_at' => in_array($target, [ContributionState::Validated, ContributionState::Returned], true) ? now() : $instrument->reviewed_at,
                'review_note' => $note ?? $instrument->review_note,
            ], fn ($value) => $value !== null))->save();

            $this->recordVersion(
                $instrument,
                $actor,
                __('contributions.version_'.$target->value, ['from' => __($from->labelKey())])
            );

            $this->announce($instrument, $target, $actor);

            return $instrument->refresh();
        });
    }

    /**
     * Tell the people the decision is about.
     *
     * The contributor hears every decision on their own work, because it is
     * the only notification that blocks them. The region hears about a
     * validated instrument in its own sector, because that is what the
     * register is for.
     */
    private function announce(Instrument $instrument, ContributionState $target, User $actor): void
    {
        $contributor = $instrument->contributor;

        if ($contributor !== null && ! $contributor->is($actor)) {
            $contributor->notify(new RegulatoryActivity(
                NotificationTrigger::SubmissionDecision,
                __('contributions.notify_'.$target->value, [
                    'title' => $instrument->translated('title', $instrument->reference_number ?? $instrument->slug),
                ]),
                route('contributions.edit', [
                    'locale' => $contributor->preferred_locale ?? 'en',
                    'instrument' => $instrument->slug,
                ]),
                ['instrument_id' => $instrument->id]
            ));
        }

        if ($target !== ContributionState::Validated) {
            return;
        }

        $this->notifier->fire(
            NotificationTrigger::SectorInstrument,
            __('contributions.notify_register', [
                'title' => $instrument->translated('title', $instrument->reference_number ?? $instrument->slug),
                'country' => $instrument->country?->name ?? '',
            ]),
            route('register.show', ['locale' => 'en', 'instrument' => $instrument->slug]),
            sector: $instrument->sector,
            except: $contributor,
            payload: ['instrument_id' => $instrument->id],
        );
    }

    /** @return array<string, mixed> */
    private function snapshot(Instrument $instrument): array
    {
        $text = [];

        foreach (['title', 'summary', 'body'] as $field) {
            foreach (\App\Support\Locales::SUPPORTED as $locale) {
                $value = $instrument->translated($field, '', $locale);

                if ($value !== '') {
                    $text[$field][$locale] = $value;
                }
            }
        }

        return $text + [
            'reference_number' => $instrument->reference_number,
            'instrument_type' => $instrument->instrument_type?->value,
            'status' => $instrument->status?->value,
            'enacted_on' => $instrument->enacted_on?->toDateString(),
            'effective_from' => $instrument->effective_from?->toDateString(),
        ];
    }
}
