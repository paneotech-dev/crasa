<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Page;
use App\Models\User;

/**
 * Pages are regional: no page belongs to a member state, so the country
 * scope does not apply here.
 *
 * Publishing is a separate permission from editing, because the roles are
 * separate people. An editor writes and sends for review; putting a page in
 * front of fourteen regulators is a different act.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function view(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    /** Moving a page onto or off the public site. */
    public function publish(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::PublishContent);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }

    public function restore(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
