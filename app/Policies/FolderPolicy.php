<?php

namespace App\Policies;

use App\Enums\Clearance;
use App\Enums\Permission;
use App\Models\Folder;
use App\Models\User;

/**
 * Who may open a folder, and who may put something in it.
 *
 * Two gates, and both have to pass.
 *
 * The clearance gate asks whether this account may open documents at this
 * grade at all, and for Confidential that answer comes from the account's
 * role in the owning committee rather than from a per-user grant list.
 * That was the earlier ruling and it is what stops grants outliving the
 * person who held them.
 *
 * The country gate asks whether this folder is theirs. The global scope has
 * already made another member state's folders unfindable; this is the same
 * rule stated again on the write path, because a global scope protects
 * reads and says nothing about a POST.
 */
class FolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->mayOpenClearance(Clearance::Member, null);
    }

    public function view(User $user, Folder $folder): bool
    {
        if (! $user->mayOpenClearance($folder->clearance, $folder->committee_id)) {
            return false;
        }

        /*
         * A null country means the folder is regional: a committee's
         * working papers, or something CRASA shares with everyone. It
         * belongs to no single member state, so the country gate has
         * nothing to compare and the clearance gate above is the whole
         * answer. This mirrors countryScopeIncludesShared() on the model
         * and DocumentPolicy, so the three cannot disagree.
         */
        if ($folder->country_id === null) {
            return true;
        }

        return $user->mayActOnCountry($folder->country_id);
    }

    /**
     * Uploading into a folder.
     *
     * A member state representative uploads into their own country's
     * folders: that is the point of the extranet. The Secretariat uploads
     * anywhere it can read.
     */
    public function upload(User $user, Folder $folder): bool
    {
        if (! $this->view($user, $folder)) {
            return false;
        }

        if ($folder->country_id !== null) {
            return $user->hasPermission(Permission::UploadToCountryFolder)
                && $user->mayActOnCountry($folder->country_id);
        }

        return $user->hasPermission(Permission::ManageContent)
            || $user->hasPermission(Permission::UploadToCountryFolder);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Folder $folder): bool
    {
        return $user->hasPermission(Permission::ManageContent) && $this->view($user, $folder);
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $user->hasPermission(Permission::DeleteContent) && $this->view($user, $folder);
    }
}
