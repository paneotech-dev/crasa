<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;
use App\Notifications\AdminActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Telling the other administrators (F10).
 *
 * When an administrator suspends, reactivates or deletes an account,
 * changes a role, or publishes without an approval, every other active
 * administrator gets an in-app notification in the bell. Mail is off on
 * this platform, so the e-mail each of them would receive is written to
 * held_mail with status "held": a record of what to send once mail is on,
 * never sent from here.
 */
final class AdminAlerts
{
    /** @param array<string, string> $context */
    public static function send(User $actor, string $event, array $context, ?string $url = null): int
    {
        $url ??= route('admin.audit', ['locale' => app()->getLocale()]);
        $sent = 0;

        $recipients = User::query()->withoutGlobalScopes()
            ->where('status', 'active')
            ->where('id', '!=', $actor->id)
            ->get()
            ->filter(fn (User $u) => $u->hasPermission(Permission::ManageUsers) || $u->isAdministrator());

        foreach ($recipients as $admin) {
            $locale = $admin->preferredLocale();
            $line = __('fa.alert_'.$event, $context + ['actor' => $actor->name], $locale);

            Notifier::send([$admin], new AdminActivity($event, $line, $url), 'other');

            if (Schema::hasTable('held_mail')) {
                DB::table('held_mail')->insert([
                    'user_id' => $admin->id,
                    'recipient' => $admin->email,
                    'subject' => __('fa.alert_subject', [], $locale),
                    'body' => $line."\n\n".$url,
                    'status' => 'held',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $sent++;
        }

        return $sent;
    }

    /**
     * The latest administrator notices for one reader, for a dashboard
     * block. Each row: title, url, read (bool), at (Carbon).
     *
     * @return \Illuminate\Support\Collection<int, array{title: string, url: ?string, read: bool, at: \Illuminate\Support\Carbon}>
     */
    public static function recentFor(User $user, int $limit = 5): \Illuminate\Support\Collection
    {
        return $user->notifications()
            ->where('type', AdminActivity::class)
            ->latest()->limit($limit)->get()
            ->map(fn ($n) => [
                'title' => (string) ($n->data['title'] ?? ''),
                'url' => $n->data['url'] ?? null,
                'read' => $n->read_at !== null,
                'at' => $n->created_at,
            ]);
    }
}
