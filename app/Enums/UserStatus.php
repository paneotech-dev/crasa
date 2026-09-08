<?php

namespace App\Enums;

/**
 * Screen 13 grants "invite or suspend a user", so both states need storage.
 *
 * A suspended account still authenticates. It is denied everything except
 * the page telling the holder who to contact, because an account that fails
 * at the password prompt is indistinguishable from a forgotten password and
 * generates a support call rather than resolving one.
 */
enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    public function mayUsePlatform(): bool
    {
        return $this === self::Active;
    }
}
