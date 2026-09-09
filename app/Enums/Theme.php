<?php

namespace App\Enums;

/**
 * The back office surface set. Screen 09: "The Secretariat sets it per
 * account, and it follows the operating system by default."
 *
 * Three states rather than two, because "follow the operating system" is a
 * different answer from "light", and a user who has never chosen must not
 * be recorded as having chosen.
 */
enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    /** The order the header control cycles through. */
    public function next(): self
    {
        return match ($this) {
            self::System => self::Light,
            self::Light => self::Dark,
            self::Dark => self::System,
        };
    }
}
