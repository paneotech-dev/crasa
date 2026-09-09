<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MediaAsset;
use App\Models\User;

/**
 * Media is regional. No asset belongs to a member state, so the country
 * scope does not apply.
 *
 * Deleting takes the delete permission rather than the manage one, in line
 * with pages: an editor adds and corrects images, and removing one that
 * fourteen regulators may be looking at is a different act.
 */
class MediaAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }

    public function restore(User $user, MediaAsset $asset): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
