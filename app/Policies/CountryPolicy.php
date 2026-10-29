<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Country;
use App\Models\User;

/**
 * The fourteen member states are reference data. Everyone who can see
 * published content can read them, because the comparator and the members
 * page cover all fourteen regardless of who is looking. Nobody edits them
 * through the platform: they are seeded.
 */
class CountryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewPublishedContent);
    }

    public function view(User $user, Country $country): bool
    {
        return $user->hasPermission(Permission::ViewPublishedContent);
    }

    /** Day 3: an administrator adds a member state, as SADC admits one. */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageSettings);
    }

    public function update(User $user, Country $country): bool
    {
        return $user->hasPermission(Permission::ManageSettings);
    }

    /**
     * Day 3: and removes one nothing points at, which in practice is one
     * added by mistake. The resource refuses the rest by name.
     */
    public function delete(User $user, Country $country): bool
    {
        return $user->hasPermission(Permission::ManageSettings)
            && $user->hasPermission(Permission::DeleteContent);
    }
}
