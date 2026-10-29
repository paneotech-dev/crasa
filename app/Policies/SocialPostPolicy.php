<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\Approvals;

/** Social posts follow the approval flow like news (evening pass). */
class SocialPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function view(User $user, SocialPost $post): bool
    {
        return $user->hasAnyPermission([Permission::ManageContent, Permission::ApproveSocial]);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    /** Locked while In review. */
    public function update(User $user, SocialPost $post): bool
    {
        return $user->hasPermission(Permission::ManageContent) && ! Approvals::isLocked($post);
    }

    public function publish(User $user, SocialPost $post): bool
    {
        return Approvals::mayPublishDirectly($user, $post);
    }

    public function delete(User $user, SocialPost $post): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }
}
