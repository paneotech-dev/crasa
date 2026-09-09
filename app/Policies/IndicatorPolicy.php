<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Indicator;
use App\Models\User;

/**
 * The indicator taxonomy is reference data with a source attached. Editing
 * one changes what fourteen member states are asked to report, so it takes
 * the settings permission rather than the content one, and it is never
 * deleted: an indicator that is no longer collected is made inactive, and
 * the values already reported against it stay readable.
 */
class IndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewPublishedIndicators);
    }

    public function view(User $user, Indicator $indicator): bool
    {
        return $user->hasPermission(Permission::ViewPublishedIndicators);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageSettings);
    }

    public function update(User $user, Indicator $indicator): bool
    {
        return $user->hasPermission(Permission::ManageSettings);
    }

    public function delete(User $user, Indicator $indicator): bool
    {
        return false;
    }
}
