<?php

namespace App\Enums;

/**
 * Where a member state's contribution stands with the Secretariat.
 *
 * The same four words as the data-return workflow, deliberately: draft,
 * submitted, validated, returned. CLAUDE.md fixes that vocabulary, the
 * Secretariat reviews both in the same queue, and a regulator who has filed
 * one return already knows what these mean.
 */
enum ContributionState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Validated = 'validated';
    case Returned = 'returned';

    public function labelKey(): string
    {
        return 'contributions.state_'.$this->value;
    }

    /** Whether the contributor may still edit it. */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }

    /** Whether it appears in the public register. */
    public function isPublic(): bool
    {
        return $this === self::Validated;
    }

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft, self::Returned => [self::Submitted],
            self::Submitted => [self::Validated, self::Returned],
            self::Validated => [],
        };
    }
}
