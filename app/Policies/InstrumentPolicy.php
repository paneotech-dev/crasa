<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Instrument;
use App\Models\User;

/**
 * The regulatory library is public: anyone may read a published instrument
 * from any member state, which is the point of a harmonisation platform.
 *
 * Editing is where the country boundary bites. A representative may not
 * touch another member state's regulation.
 */
class InstrumentPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Instrument $instrument): bool
    {
        if ($instrument->status->isPublished()) {
            return true;
        }

        // Drafts and proposals are not public.
        return $user instanceof User && $user->hasPermission(Permission::ManageContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Instrument $instrument): bool
    {
        return $user->hasPermission(Permission::ManageContent)
            && $user->mayActOnCountry($instrument->country_id);
    }

    public function publish(User $user, Instrument $instrument): bool
    {
        return $user->hasPermission(Permission::PublishContent)
            && $user->mayActOnCountry($instrument->country_id);
    }

    public function restoreVersion(User $user, Instrument $instrument): bool
    {
        return $user->hasPermission(Permission::RestoreVersion);
    }

    public function delete(User $user, Instrument $instrument): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
