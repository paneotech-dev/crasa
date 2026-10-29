<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;

/**
 * Where a signed-in reader's own work is, in one place.
 *
 * The public header used to carry one padlocked "Extranet" link whether the
 * reader was signed in or not, and nothing else: a signed-in viewer had no
 * way from the public site to their own account or out again, and an
 * administrator reading a news item had no way back to the back office.
 *
 * The same answer as LoginController::destinationFor, which is deliberate:
 * where the header sends somebody and where signing in sends them are the
 * same question, and two answers to it drift.
 */
final class Workspace
{
    /**
     * Whether this reader belongs in the back office rather than the
     * extranet. Asked by layouts.workspace, which serves one URL to both.
     */
    public static function isBackOffice(?User $user): bool
    {
        return $user !== null && \Illuminate\Support\Facades\Gate::forUser($user)->allows('enter-back-office');
    }

    /**
     * The reader's workspace, or null for an account that has none.
     *
     * A viewer has none. That is not a gap: a viewer signs in to read what
     * is already public and their account page is reached from the account
     * link beside this one.
     *
     * @return array{url: string, label: string}|null
     */
    public static function for(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $locale = app()->getLocale();

        if (\Illuminate\Support\Facades\Gate::forUser($user)->allows('enter-back-office')) {
            return ['url' => route('admin.dashboard', ['locale' => $locale]), 'label' => __('nav.back_office')];
        }

        if ($user->hasPermission(Permission::ReachCommitteeWorkspace)) {
            return ['url' => route('extranet.dashboard', ['locale' => $locale]), 'label' => __('nav.extranet')];
        }

        return null;
    }
}
