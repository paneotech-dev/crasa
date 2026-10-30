<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Sector;
use App\Models\User;

/**
 * Three sectors, seeded. A fourth, or one fewer, is a site setting:
 * administrators (ManageSettings) add and delete sectors under Content >
 * Taxonomies, which refuses a delete while anything points at the sector.
 * The generic data screen corrects a sector's name and sends a reader who
 * may add or delete there.
 */
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
        return $user->hasPermission(Permission::ManageSettings);
    }

    public function update(User $user, Sector $sector): bool
    {
        return $user->hasPermission(Permission::ManageSettings);
    }

    public function delete(User $user, Sector $sector): bool
    {
        return $user->hasPermission(Permission::ManageSettings);
    }
}
