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

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Country $country): bool
    {
        return $user->hasPermission(Permission::ManageSettings);
    }

    public function delete(User $user, Country $country): bool
    {
        return false;
    }
}
