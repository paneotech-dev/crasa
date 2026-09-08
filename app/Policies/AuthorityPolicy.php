<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Authority;
use App\Models\User;

/**
 * Authorities are country-scoped. The global scope already hides other
 * member states' rows from a country-scoped account; this policy is the
 * second layer, so a row reached by id rather than by query is still refused.
 */
class AuthorityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewPublishedContent);
    }

    public function view(User $user, Authority $authority): bool
    {
        return $user->hasPermission(Permission::ViewPublishedContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Authority $authority): bool
    {
        return $user->hasPermission(Permission::ManageContent)
            && $user->mayActOnCountry($authority->country_id);
    }

    public function delete(User $user, Authority $authority): bool
    {
        return $user->hasPermission(Permission::DeleteContent)
            && $user->mayActOnCountry($authority->country_id);
    }
}
