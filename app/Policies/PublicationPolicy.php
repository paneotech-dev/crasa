<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Publication;
use App\Models\User;

/**
 * Publications are regional documents. A publication may name a member
 * state, but it belongs to CRASA, so the country scope does not gate it.
 */
class PublicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function view(User $user, Publication $publication): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Publication $publication): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function publish(User $user, Publication $publication): bool
    {
        return $user->hasPermission(Permission::PublishContent);
    }

    public function delete(User $user, Publication $publication): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }

    public function restore(User $user, Publication $publication): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
