<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * User management is administrator only. Screen 13 puts invite, suspend and
 * role assignment in the Users and security group, granted to Administrator
 * and to nobody else. An editor is a content role and never reaches it.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageUsers);
    }

    public function view(User $user, User $subject): bool
    {
        // Anyone may read their own account.
        return $user->is($subject) || $user->hasPermission(Permission::ManageUsers);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageUsers);
    }

    public function update(User $user, User $subject): bool
    {
        return $user->is($subject) || $user->hasPermission(Permission::ManageUsers);
    }

    /**
     * Changing a role is separate from editing an account, because it is the
     * action that grants power. Screen 13 lists it as its own permission and
     * the audit log records it as its own event.
     */
    public function assignRole(User $user, User $subject): bool
    {
        if (! $user->hasPermission(Permission::AssignRoles)) {
            return false;
        }

        // An administrator does not change their own role. Removing the last
        // administrator's own access is a support call, not a click.
        return ! $user->is($subject);
    }

    public function delete(User $user, User $subject): bool
    {
        return $user->hasPermission(Permission::ManageUsers) && ! $user->is($subject);
    }
}
