<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PageBlock;
use App\Models\User;

/**
 * A block is part of its page, so it takes the page's answer. It has its
 * own policy rather than borrowing the page's because the route binds the
 * block, and a policy that has to be asked about a different model than the
 * one in front of it is where authorisation bugs live.
 */
class PageBlockPolicy
{
    public function view(User $user, PageBlock $block): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, PageBlock $block): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function delete(User $user, PageBlock $block): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
