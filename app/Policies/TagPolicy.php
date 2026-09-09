<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Tag;
use App\Models\User;

/** Tags are content vocabulary. Whoever writes the content maintains them. */
class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
