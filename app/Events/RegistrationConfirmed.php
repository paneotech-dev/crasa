<?php

namespace App\Events;

use App\Models\DelegateRegistration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A delegate registration moved to confirmed. Dispatched by the back office
 * when the Secretariat confirms one; what follows (the delegate's notice)
 * is the listener's business, not the screen's.
 */
class RegistrationConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly DelegateRegistration $registration) {}
}
