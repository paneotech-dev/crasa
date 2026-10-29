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
        return $user->hasAnyPermission([Permission::ManageContent, Permission::ReadContent, Permission::ApproveContent]);
    }

    public function view(User $user, Publication $publication): bool
    {
        return $user->hasAnyPermission([Permission::ManageContent, Permission::ReadContent, Permission::ApproveContent]);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    /** Open the editor. An item In review opens read only. */
    public function edit(User $user, Publication $publication): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    /** Save a change. Refused while the item is In review (locked until approved or returned). */
    public function update(User $user, Publication $publication): bool
    {
        return $user->hasPermission(Permission::ManageContent) && ! \App\Support\Approvals::isLocked($publication);
    }

    public function sendForApproval(User $user, Publication $publication): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function publish(User $user, Publication $publication): bool
    {
        return \App\Support\Approvals::mayPublishDirectly($user, $publication);
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
