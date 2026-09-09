<?php

namespace App\Enums;

/**
 * draft, submitted, validated, returned. CLAUDE.md fixes these four and
 * says changing them is a decision, not a commit.
 *
 * Screen 06 draws all four as a strip across the top of the e-Form, so a
 * representative can see where their return sits without reading a status
 * word out of a table.
 */
enum SubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Validated = 'validated';
    case Returned = 'returned';

    public function labelKey(): string
    {
        return 'submissions.status_'.$this->value;
    }

    /** Whether the member state can still change the figures. */
    public function isEditable(): bool
    {
        // A returned submission is editable again: that is the point of
        // returning it rather than rejecting it.
        return $this === self::Draft || $this === self::Returned;
    }

    /**
     * The states this one may move to.
     *
     * Validated is terminal. A figure that has been published into the
     * regional dataset is not un-validated by editing the form it came
     * from; a correction is a new return for the next period, or a
     * Secretariat edit to the value itself, and both leave a trail.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::Validated, self::Returned],
            self::Returned => [self::Submitted],
            self::Validated => [],
        };
    }

    /** Whether reaching this state is the Secretariat's decision. */
    public function isReviewDecision(): bool
    {
        return $this === self::Validated || $this === self::Returned;
    }
}
