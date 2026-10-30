<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;

/** Projects in the back office (S8 remainder): content people edit them; deleting is soft. */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([Permission::ManageContent, Permission::ReadContent]);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasAnyPermission([Permission::ManageContent, Permission::ReadContent]);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
