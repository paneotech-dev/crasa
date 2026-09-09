<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Enquiry;
use App\Models\User;

/**
 * A message somebody sent to the Secretariat.
 *
 * Read and answered, never edited: rewriting what a regulator wrote while
 * keeping their name on it is not a feature. Deleting takes the settings
 * permission, because an enquiry may be the only record of a request, and
 * removing one is an administrative act rather than an editorial one.
 */
class EnquiryPolicy
{
    public function viewAny(User $user): bool
    {
        // An enquiry carries a named person's contact details and whatever
        // they chose to write to the Secretariat. That is not editorial
        // material, so it takes the settings permission rather than the
        // content one: fewer people, and the ones accountable for it.
        return $user->hasPermission(Permission::ManageSettings);
    }

    public function view(User $user, Enquiry $enquiry): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Enquiry $enquiry): bool
    {
        return false;
    }

    public function delete(User $user, Enquiry $enquiry): bool
    {
        return $user->hasPermission(Permission::ManageSettings);
    }
}
