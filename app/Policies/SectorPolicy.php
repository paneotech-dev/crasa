<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Sector;
use App\Models\User;

/** Three sectors, seeded. A fourth is a decision, not a commit. */
class SectorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewPublishedContent);
    }

    public function view(User $user, Sector $sector): bool
    {
        return $user->hasPermission(Permission::ViewPublishedContent);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Sector $sector): bool
    {
        return $user->hasPermission(Permission::ManageSettings);
    }

    public function delete(User $user, Sector $sector): bool
    {
        return false;
    }
}
