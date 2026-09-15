<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Announcement;
use App\Models\User;

/**
 * Announcements are the Secretariat's own channel to the membership.
 *
 * Writing one is content management. Publishing one is a separate ability,
 * because publishing notifies fourteen regulators and that is not the same
 * decision as saving a draft.
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function publish(User $user, Announcement $announcement): bool
    {
        return $user->hasPermission(Permission::PublishContent);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
