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
        return $user->hasPermission(Permission::ManageContent);
    }

    public function view(User $user, NewsItem $newsItem): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function update(User $user, NewsItem $newsItem): bool
    {
        return $user->hasPermission(Permission::ManageContent);
    }

    public function publish(User $user, NewsItem $newsItem): bool
    {
        return $user->hasPermission(Permission::PublishContent);
    }

    public function delete(User $user, NewsItem $newsItem): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }

    public function restore(User $user, NewsItem $newsItem): bool
    {
        return $user->hasPermission(Permission::DeleteContent);
    }
}
