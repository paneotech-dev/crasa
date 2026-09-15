<?php

namespace App\Services;

use App\Enums\NotificationTrigger;
use App\Models\Committee;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\RegulatoryActivity;
use Illuminate\Support\Collection;

/**
 * Who hears about what. RP-05, extranet item 6.
 *
 * THE AUDIENCE IS DERIVED, NEVER STORED. A subscription list is a thing
 * that rots: somebody leaves an authority and their row stays behind, a
 * committee is reconstituted and half the list is wrong. Every audience
 * here is worked out at send time from the seats and mandates the platform
 * already holds, so it is right by construction.
 *
 * THE COUNTRY BOUNDARY HOLDS HERE TOO. Botswana is never notified about
 * anything of Mozambique's, and that is not a filter applied afterwards: a
 * sector audience is the states whose own authority holds that mandate, a
 * committee audience is the people holding a seat on that committee. There
 * is no trigger whose audience is "everybody".
 *
 * NOTHING HERE IS LOGISTICAL. The meeting trigger fires on a meeting being
 * scheduled, moved or cancelled, which is a fact about the committee's
 * calendar. It does not remind anybody to travel to one.
 */
class NotifiesMembers
{
    /**
     * Tell everyone who asked to hear about this.
     *
     * @param  array<string, mixed>  $payload
     * @return int  how many accounts were notified
     */
    public function fire(
        NotificationTrigger $trigger,
        string $title,
        string $url,
        ?Sector $sector = null,
        ?Committee $committee = null,
        ?User $except = null,
        array $payload = [],
    ): int {
        $audience = $this->audience($trigger, $sector, $committee)
            ->reject(fn (User $user) => $except !== null && $user->is($except))
            ->filter(fn (User $user) => $this->subscribed($user, $trigger));

        foreach ($audience as $user) {
            $user->notify(new RegulatoryActivity($trigger, $title, $url, $payload));
        }

        return $audience->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function audience(NotificationTrigger $trigger, ?Sector $sector, ?Committee $committee): Collection
    {
        $members = User::query()
            ->where('status', 'active')
            ->whereIn('role', ['member_state_rep', 'committee_member']);

        return match ($trigger) {
            NotificationTrigger::CommitteeDocument,
            NotificationTrigger::ThreadComment,
            NotificationTrigger::MeetingChanged => $committee === null
                ? collect()
                : $members->whereHas('committees', fn ($q) => $q->whereKey($committee->getKey()))->get(),

            NotificationTrigger::SectorInstrument => $sector === null
                ? collect()
                : $members->whereHas('authority.sectors', fn ($q) => $q->whereKey($sector->getKey()))->get(),

            // These two are fired by their own senders, which already know
            // exactly who to tell: the state that filed the return, and the
            // audience the announcement was addressed to.
            NotificationTrigger::SubmissionDecision,
            NotificationTrigger::Announcement => collect(),
        };
    }

    /**
     * Whether this account asked for this trigger.
     *
     * An account that has never opened the preferences screen has null
     * here, which means the defaults rather than nothing: an account that
     * hears about nothing until it opts in is an account that misses its
     * first returned submission.
     */
    public function subscribed(User $user, NotificationTrigger $trigger): bool
    {
        $preferences = $user->notification_preferences ?? NotificationTrigger::defaults();

        return in_array($trigger->value, $preferences, true);
    }
}
