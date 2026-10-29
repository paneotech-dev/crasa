<?php

namespace App\Events;

use App\Models\DelegateRegistration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A delegate filed (or amended) an arrival and departure on an event's
 * form. Dispatched once the row is stored; the notify side listens.
 */
class RegistrationReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly DelegateRegistration $registration) {}
}
