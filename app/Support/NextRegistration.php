<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * "Your next meeting" for the member area: the next meeting this account
 * may see whose registration is open. Shared by the next-meeting card and
 * the member dashboard, which draws its own meeting block when there is
 * none.
 */
final class NextRegistration
{
    public static function for(?User $user): ?Meeting
    {
        if ($user === null) {
            return null;
        }

        // The same meetings the member dashboard calls yours: your
        // committees' and the CRASA-wide ones; the Secretariat sees every one.
        $seats = $user->committees()->pluck('committees.id');

        return Meeting::upcoming()->with('committee')
            ->when(! $user->seesAllCountries(), fn ($q) => $q->where(fn ($w) => $w->whereIn('committee_id', $seats)->orWhereNull('committee_id')))
            ->get()
            ->first(fn (Meeting $m) => $m->registrationIsOpen() && Gate::forUser($user)->allows('view', $m));
    }
}
