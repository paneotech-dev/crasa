<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Page;
use App\Models\User;

/**
 * Pages are regional: no page belongs to a member state, so the country
 * scope does not apply here.
 *
 * Publishing is a separate permission from editing, because the roles are
 * separate people. An editor writes and sends for review; putting a page in
 * front of fourteen regulators is a different act.
 */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([Permission::ManageContent, Permission::ReadContent, Permission::ApproveContent]);
    }

    public function view(User $user, Page $page): bool
    {
        return $user->hasAnyPermission([Permission::ManageContent, Permission::ReadContent, Permission::ApproveContent]);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    /** Moving a page onto or off the public site. */
    public function sendForApproval(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function publish(User $user, Page $page): bool
    {
        return \App\Support\Approvals::mayPublishDirectly($user, $page);
    }

    /**
     * Recording that CRASA has signed off a page's text.
     *
     * Not publish. Screen 13's matrix grants publishing to editors as well
     * as administrators, and an editor putting a page on the site is an
     * editorial act. Recording that the client has approved its own legal
     * text is not: it is a statement about what CRASA has decided, and it
     * is the thing the privacy notice's outstanding blanks are waiting on.
     * ManageSettings is the administrator-only grant in the same matrix.
     */
    public function clientReview(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::ManageSettings);
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }

    public function restore(User $user, Page $page): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
