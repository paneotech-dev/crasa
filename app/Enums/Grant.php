<?php

namespace App\Enums;

/**
 * The three states in screen 13's permission matrix. Every cell states its
 * state as a character as well as a colour, so the matrix is readable
 * without relying on colour alone.
 */
enum Grant: string
{
    case Granted = 'granted';
    /** Granted, but only for the user's own country or authority. */
    case Conditional = 'conditional';
    case Denied = 'denied';

    public function mark(): string
    {
        return match ($this) {
            self::Granted => 'Y',
            self::Conditional => 'C',
            self::Denied => 'N',
        };
    }

    public function allowsAnything(): bool
    {
        return $this !== self::Denied;
    }
}
