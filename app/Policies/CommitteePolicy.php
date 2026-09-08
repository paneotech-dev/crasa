<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Committee;
use App\Models\User;

class CommitteePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewPublishedContent);
    }

    public function view(User $user, Committee $committee): bool
    {
        return $user->hasPermission(Permission::ViewPublishedContent);
    }

    /**
     * The committee workspace, as distinct from the public committee page.
     * Conditional for a representative or a committee member: they reach the
     * workspaces of committees their own member state sits on.
     */
    public function viewWorkspace(User $user, Committee $committee): bool
    {
        if (! $user->hasPermission(Permission::ReachCommitteeWorkspace)) {
            return false;
        }

        if (! $user->permissionIsConditional(Permission::ReachCommitteeWorkspace)) {
            return true;
        }

        return $user->country_id !== null
            && $committee->countries()->whereKey($user->country_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Committee $committee): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function delete(User $user, Committee $committee): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
