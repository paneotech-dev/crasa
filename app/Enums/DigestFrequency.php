<?php

namespace App\Enums;

/**
 * How often a member wants the email. RP-05.
 *
 * Immediate is deliberately not offered for everything. A daily digest is
 * the default because the platform's events are not urgent to the minute:
 * a document filed into a committee at 21:00 does not need to wake anybody,
 * and a channel that sends one email per event is a channel people filter
 * into a folder they never open.
 *
 * A submission decision is the exception and is sent as it happens, because
 * it is the only notification that blocks the reader's own work.
 */
enum DigestFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Never = 'never';

    public function labelKey(): string
    {
        return 'notifications.digest_'.$this->value;
    }
}
