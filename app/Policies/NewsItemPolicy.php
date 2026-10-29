<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\NewsItem;
use App\Models\User;

/** News is regional. Nothing in the newsroom belongs to a member state. */
class NewsItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([Permission::ManageContent, Permission::ReadContent, Permission::ApproveContent]);
    }

    public function view(User $user, NewsItem $newsItem): bool
    {
        return $user->hasAnyPermission([Permission::ManageContent, Permission::ReadContent, Permission::ApproveContent]);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    /** Open the editor. An item In review opens read only. */
    public function edit(User $user, NewsItem $newsItem): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    /** Save a change. Refused while the item is In review (locked until approved or returned). */
    public function update(User $user, NewsItem $newsItem): bool
    {
        return $user->hasPermission(Permission::ManageContent) && ! \App\Support\Approvals::isLocked($newsItem);
    }

    public function sendForApproval(User $user, NewsItem $newsItem): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function publish(User $user, NewsItem $newsItem): bool
    {
        return \App\Support\Approvals::mayPublishDirectly($user, $newsItem);
    }

    /*
     * PM-05 (re-run 29 October 2026): an editor creates, edits AND deletes a
     * news item. Screen 13's matrix keeps DeleteContent for administrators,
     * and that stays true for pages, media, publications and folders. A
     * news item is the editor's own daily material, its delete is soft and
     * the editor can restore it, so ManageContent is enough here.
     */
    public function delete(User $user, NewsItem $newsItem): bool
    {
        return $user->hasPermission(Permission::DeleteContent) || $user->hasPermission(Permission::ManageContent);
    }

    public function restore(User $user, NewsItem $newsItem): bool
    {
        return $user->hasPermission(Permission::DeleteContent) || $user->hasPermission(Permission::ManageContent);
    }
}
