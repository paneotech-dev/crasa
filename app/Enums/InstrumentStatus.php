<?php

namespace App\Enums;

/**
 * Status pill colours are fixed by CLAUDE.md: green for in force, amber for
 * draft and proposed, red for repealed, slate for superseded and amended.
 * No screen invents a new status colour, so the mapping lives here.
 */
enum InstrumentStatus: string
{
    case InForce = 'in_force';
    case Amended = 'amended';
    case Repealed = 'repealed';
    case Draft = 'draft';
    case Proposed = 'proposed';

    public function label(): string
    {
        return match ($this) {
            self::InForce => 'In force',
            self::Amended => 'Amended',
            self::Repealed => 'Repealed',
            self::Draft => 'Draft',
            self::Proposed => 'Proposed',
        };
    }

    /** The CSS modifier for the status pill. Tokens only. */
    public function pillModifier(): string
    {
        return match ($this) {
            self::InForce => 'c-pill--validated',
            self::Draft, self::Proposed => 'c-pill--pending',
            self::Repealed => 'c-pill--error',
            self::Amended => 'c-pill--archived',
        };
    }

    public function isPublished(): bool
    {
        return in_array($this, [self::InForce, self::Amended, self::Repealed], true);
    }
}
