<?php

namespace App\Policies;

use App\Enums\Clearance;
use App\Enums\Permission;
use App\Models\Document;
use App\Models\User;

/**
 * Three checks, in order: clearance, then country, then the committee seat
 * for confidential papers. All server-side. Hiding a download button is not
 * access control.
 */
class DocumentPolicy
{
    public function view(?User $user, Document $document): bool
    {
        if ($document->clearance === Clearance::Public) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        if (! $user->mayOpenClearance($document->clearance, $document->owningCommitteeId())) {
            return false;
        }

        // A member state reaches its own country's papers and no others.
        // Regional documents, which belong to no single member state, are
        // open to anyone who cleared the grade above.
        $countryId = $document->owningCountryId();

        if ($countryId === null) {
            return true;
        }

        return $user->mayActOnCountry($countryId);
    }

    public function download(?User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Document $document): bool
    {
        return $user->hasPermission(Permission::ManageContent)
            && $user->mayActOnCountry($document->owningCountryId());
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
