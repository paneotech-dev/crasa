<?php

namespace App\Policies;

use App\Enums\Clearance;
use App\Enums\Permission;
use App\Models\Meeting;
use App\Models\User;

/**
 * A meeting is readable at its clearance. Public meetings are the events
 * section; everything else is the committee workspace.
 *
 * Managing one takes ManageMeetings, which is a Secretariat permission.
 * Nothing here manages an event's operation: see the note on the model.
 */
class MeetingPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Meeting $meeting): bool
    {
        if ($meeting->clearance === Clearance::Public) {
            return true;
        }

        return $user instanceof User
            && $user->mayOpenClearance($meeting->clearance, $meeting->committee_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageMeetings);
    }

    /** Open the editor. An item In review opens read only. */
    public function edit(User $user, Meeting $meeting): bool
    {
        return $user->hasPermission(Permission::ManageMeetings);
    }

    /** Save a change. Refused while the item is In review (locked until approved or returned). */
    public function update(User $user, Meeting $meeting): bool
    {
        return $user->hasPermission(Permission::ManageMeetings) && ! \App\Support\Approvals::isLocked($meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->hasPermission(Permission::ManageMeetings);
    }
}
