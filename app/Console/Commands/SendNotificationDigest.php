<?php

namespace App\Console\Commands;

use App\Mail\NotificationDigest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * The email digest. RP-05, extranet item 6.
 *
 * WHY A DIGEST AND NOT AN EMAIL PER EVENT. The platform's events are not
 * urgent to the minute. A document filed into a committee at 21:00 does not
 * need to wake anybody, and a channel that sends one email per event is a
 * channel a regulator filters into a folder they never open, which means
 * the one that mattered is missed too.
 *
 * The one exception is a decision on the reader's own return, which is sent
 * as it happens and is therefore already read by the time this runs. This
 * command sends what is still unread.
 *
 * SCHEDULED, NOT TRIGGERED. Run daily; accounts set to weekly are only
 * included on the day their week is up, which is tracked per account rather
 * than assumed to be a Monday, so fourteen regulators do not all get their
 * mail in the same minute.
 */
class SendNotificationDigest extends Command
{
    protected $signature = 'notifications:digest
                            {--dry-run : Report who would be mailed and send nothing}';

    protected $description = 'Send the daily and weekly notification digests';

    public function handle(): int
    {
        $sent = 0;
        $skipped = 0;

        foreach ($this->candidates() as $user) {
            $items = $user->unreadNotifications()
                ->where('created_at', '>=', $user->digest_sent_at ?? now()->subMonth())
                ->latest()
                ->limit(50)
                ->get();

            if ($items->isEmpty()) {
                $skipped++;

                continue;
            }

            $this->line(sprintf('  %s: %d items', $user->email, $items->count()));

            if ($this->option('dry-run')) {
                continue;
            }

            Mail::to($user->email)->send(new NotificationDigest($user, $items));

            $user->forceFill(['digest_sent_at' => now()])->save();
            $sent++;
        }

        $this->info(sprintf('%d digests sent, %d accounts had nothing to send.', $sent, $skipped));

        return self::SUCCESS;
    }

    /**
     * Accounts due a digest now.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function candidates()
    {
        return User::query()
            ->where('status', 'active')
            ->whereIn('digest_frequency', ['daily', 'weekly'])
            ->get()
            ->filter(function (User $user): bool {
                if ($user->digest_sent_at === null) {
                    return true;
                }

                // Per account rather than on a fixed weekday, so fourteen
                // regulators do not all receive mail in the same minute.
                return $user->digest_frequency === 'weekly'
                    ? $user->digest_sent_at->addWeek()->isPast()
                    : $user->digest_sent_at->addDay()->isPast();
            });
    }
}
