<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\User;

/**
 * The trail is readable by administrators and writable by nobody. The model
 * refuses updates and deletes outright; this policy stops the UI offering
 * them and stops a controller trying.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ReadAuditLog);
    }

    public function view(User $user, AuditLog $entry): bool
    {
        return $user->hasPermission(Permission::ReadAuditLog);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $entry): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $entry): bool
    {
        return false;
    }
}
