<?php

namespace App\Support;

use App\Enums\Clearance;
use App\Enums\NotificationTrigger;
use App\Enums\Permission;
use App\Models\DelegateRegistration;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\RegulatoryActivity;
use Closure;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;

/**
 * The one way a notice reaches the bell (client brief, item 7).
 *
 * Every source goes through send(): approvals, returns and approvals of
 * content, validation results, documents added to a folder, meeting changes
 * and cancellations, confirmed registrations, announcements and the
 * administrator notices. Three rules hold here and nowhere else:
 *
 * NOBODY IS TOLD ABOUT WHAT THEY CANNOT OPEN. Each source passes the check
 * for its own item (the folder's clearance and country, the submission's
 * country, the approval's type), and a recipient who fails it is skipped
 * rather than sent a link that answers 403.
 *
 * NO DUPLICATES. A notice with a dedupe key is written once per person: an
 * upload in the member area and the DocumentAdded event for that same
 * document are one notice, not two. The same person listed twice in one
 * audience (a committee seat and a registration) is told once.
 *
 * IN-APP ONLY. Every notification class is on the database channel; mail
 * stays off.
 */
final class Notifier
{
    /** The filters on the notifications page, in the order they are offered. */
    public const CATEGORIES = [
        'approval_request', 'decision', 'validation', 'document',
        'meeting', 'registration', 'announcement', 'other',
    ];

    /**
     * Send one notice to everyone in the audience who may open its item.
     *
     * @param  iterable<User|null>  $recipients
     * @param  Notification|Closure(User): Notification  $notice  a closure builds a notice per recipient (their language, their link)
     * @param  (Closure(User): bool)|null  $mayOpen
     * @return int how many people were told
     */
    public static function send(
        iterable $recipients,
        Notification|Closure $notice,
        string $category,
        ?string $dedupe = null,
        ?Closure $mayOpen = null,
        ?User $except = null,
    ): int {
        $sent = 0;
        $seen = [];

        foreach ($recipients as $user) {
            if (! $user instanceof User || isset($seen[$user->id])) {
                continue;
            }

            $seen[$user->id] = true;

            if ($except !== null && $user->is($except)) {
                continue;
            }

            if (! $user->mayUsePlatform() || ($mayOpen !== null && ! $mayOpen($user))) {
                continue;
            }

            if ($dedupe !== null && self::alreadyTold($user, $dedupe)) {
                continue;
            }

            $built = $notice instanceof Closure ? $notice($user) : clone $notice;

            if (method_exists($built, 'inApp')) {
                $built->inApp($category, $dedupe);
            }

            $user->notify($built);
            $sent++;
        }

        return $sent;
    }

    public static function alreadyTold(User $user, string $dedupe): bool
    {
        return $user->notifications()->where('data->dedupe', $dedupe)->exists();
    }

    /* ------------------------------------------------------------------ */
    /* Sources that are not a single controller                             */
    /* ------------------------------------------------------------------ */

    /**
     * A document added to a folder: everyone who may see that folder and
     * that document, and may reach the member area where the folder opens,
     * except the person who uploaded it. Called by the member-area upload
     * and by the App\Events\DocumentAdded listener; the dedupe key makes
     * the two one notice.
     */
    public static function documentAdded(Document $document, ?Folder $folder = null, ?User $uploader = null): int
    {
        $folder ??= $document->folder_id !== null ? Folder::find($document->folder_id) : null;

        if ($folder === null) {
            return 0;
        }

        $uploader ??= $document->uploaded_by !== null ? User::find($document->uploaded_by) : null;
        $name = (string) ($document->original_filename ?: $folder->name);

        return self::send(
            self::activeUsers(),
            fn (User $user) => new RegulatoryActivity(
                NotificationTrigger::CommitteeDocument,
                __('d3_notify.document_added', ['file' => $name, 'folder' => $folder->name], self::localeOf($user)),
                route('extranet.folder', ['locale' => self::localeOf($user), 'folder' => $folder->id]),
                [
                    'document_id' => $document->id,
                    'title_key' => 'd3_notify.document_added',
                    'params' => ['file' => $name, 'folder' => $folder->name],
                ],
            ),
            'document',
            'document:'.$document->id.':added',
            fn (User $user) => Gate::forUser($user)->allows('enter-member-area')
                && Gate::forUser($user)->allows('view', $folder)
                && Gate::forUser($user)->allows('view', $document),
            $uploader,
        );
    }

    /**
     * A meeting moved or cancelled: that committee's seats and everyone with
     * a registration for it who has an account. A public meeting opens on
     * its public page; any other opens in the member-area calendar.
     *
     * @param  'changed'|'cancelled'  $change
     */
    public static function meetingChanged(Meeting $meeting, string $change, string $version): int
    {
        $seats = $meeting->committee?->users()->get() ?? collect();
        $registered = User::query()->whereIn('id', DelegateRegistration::query()->withoutGlobalScopes()
            ->where('meeting_id', $meeting->id)
            ->where('status', '!=', 'declined')
            ->whereNotNull('user_id')
            ->pluck('user_id'))->get();

        $date = $meeting->starts_on?->isoFormat('D MMMM YYYY') ?? '';
        $key = 'd3_notify.meeting_'.$change;

        return self::send(
            $seats->concat($registered),
            fn (User $user) => new RegulatoryActivity(
                NotificationTrigger::MeetingChanged,
                __($key, ['title' => $meeting->title, 'date' => $date], self::localeOf($user)),
                self::meetingUrl($meeting, $user),
                ['meeting_id' => $meeting->id, 'title_key' => $key, 'params' => ['title' => $meeting->title, 'date' => $date]],
            ),
            'meeting',
            'meeting:'.$meeting->id.':'.$change.':'.$version,
            fn (User $user) => self::mayOpenMeeting($meeting, $user),
        );
    }

    /**
     * A registration confirmed: the registered person's own account, if
     * there is one, and their member state's representatives.
     */
    public static function registrationConfirmed(DelegateRegistration $registration): int
    {
        $meeting = Meeting::query()->find($registration->meeting_id);

        if ($meeting === null) {
            return 0;
        }

        $own = $registration->user_id !== null ? User::query()->whereKey($registration->user_id)->get() : collect();
        $params = ['name' => (string) $registration->delegate_name, 'title' => (string) $meeting->title];

        return self::send(
            $own->concat(self::representativesOf($registration->country_id)),
            fn (User $user) => new RegulatoryActivity(
                NotificationTrigger::RegistrationConfirmed,
                __('d3_notify.registration_confirmed', $params, self::localeOf($user)),
                self::meetingUrl($meeting, $user),
                ['registration_id' => $registration->id, 'meeting_id' => $meeting->id, 'title_key' => 'd3_notify.registration_confirmed', 'params' => $params],
            ),
            'registration',
            'registration:'.$registration->id.':confirmed',
            fn (User $user) => self::mayOpenMeeting($meeting, $user),
        );
    }

    /**
     * A registration received: the registered person's own account, if
     * there is one (the meeting's page), and the Secretariat accounts that
     * manage delegate registrations (that meeting's delegate list).
     */
    public static function registrationReceived(DelegateRegistration $registration): int
    {
        $meeting = Meeting::query()->find($registration->meeting_id);

        if ($meeting === null) {
            return 0;
        }

        $dedupe = 'registration:'.$registration->id.':received';
        $params = ['name' => (string) $registration->delegate_name, 'title' => (string) $meeting->title];
        $own = $registration->user_id !== null ? User::query()->whereKey($registration->user_id)->get() : collect();

        $sent = self::send(
            $own,
            fn (User $user) => new RegulatoryActivity(
                NotificationTrigger::RegistrationConfirmed,
                __('d3_notify.registration_received_own', $params, self::localeOf($user)),
                self::meetingUrl($meeting, $user),
                ['registration_id' => $registration->id, 'meeting_id' => $meeting->id, 'title_key' => 'd3_notify.registration_received_own', 'params' => $params],
            ),
            'registration',
            $dedupe,
            fn (User $user) => self::mayOpenMeeting($meeting, $user),
        );

        return $sent + self::send(
            self::activeUsers(),
            fn (User $user) => new RegulatoryActivity(
                NotificationTrigger::RegistrationConfirmed,
                __('d3_notify.registration_received', $params, self::localeOf($user)),
                route('admin.delegates', ['locale' => self::localeOf($user), 'meeting' => $meeting->slug]),
                ['registration_id' => $registration->id, 'meeting_id' => $meeting->id, 'title_key' => 'd3_notify.registration_received', 'params' => $params],
            ),
            'registration',
            $dedupe,
            fn (User $user) => $user->hasPermission(Permission::ManageMeetings)
                && Gate::forUser($user)->allows('enter-back-office'),
            $own->first(),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Audiences                                                         */
    /* ------------------------------------------------------------------ */

    /** @return Collection<int, User> */
    public static function activeUsers(): Collection
    {
        return User::query()->withoutGlobalScopes()->where('status', 'active')->get();
    }

    /**
     * A member state's representatives: the active accounts of that country
     * that file its returns.
     *
     * @return Collection<int, User>
     */
    public static function representativesOf(?int $countryId): Collection
    {
        if ($countryId === null) {
            return collect();
        }

        return User::query()->withoutGlobalScopes()
            ->where('status', 'active')
            ->where('country_id', $countryId)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission(Permission::SubmitCountryReturn))
            ->values();
    }

    /* ------------------------------------------------------------------ */
    /* Reading a stored notice                                              */
    /* ------------------------------------------------------------------ */

    /** The notice's line, in the reader's current language where it can be. */
    public static function titleOf(DatabaseNotification $notification): string
    {
        $data = $notification->data;
        $key = $data['title_key'] ?? null;

        if (is_string($key) && Lang::has($key)) {
            return (string) __($key, is_array($data['params'] ?? null) ? $data['params'] : []);
        }

        if (filled($data['title'] ?? null)) {
            return (string) $data['title'];
        }

        if (($data['type'] ?? null) === 'submission' && Lang::has('d3_notify.submission_'.($data['status'] ?? ''))) {
            return (string) __('d3_notify.submission_'.$data['status'], ['country' => $data['country'] ?? '', 'period' => $data['period'] ?? '']);
        }

        return (string) __('notifications.untitled');
    }

    /**
     * The category of a stored notice. Rows written before the category was
     * stored are read from their type, as the backfill migration does.
     *
     * @param  array<string, mixed>  $data
     */
    public static function categoryOf(array $data): string
    {
        $stored = $data['category'] ?? null;

        if (is_string($stored) && in_array($stored, self::CATEGORIES, true)) {
            return $stored;
        }

        $type = (string) ($data['type'] ?? '');

        return match ($type) {
            'approval' => ($data['event'] ?? null) === 'approval_submitted' ? 'approval_request' : 'decision',
            'submission' => ($data['status'] ?? null) === 'submitted' ? 'approval_request' : 'validation',
            'announcement' => 'announcement',
            default => NotificationTrigger::tryFrom($type) !== null
                ? self::categoryOfTrigger(NotificationTrigger::from($type))
                : 'other',
        };
    }

    public static function categoryOfTrigger(NotificationTrigger $trigger): string
    {
        return match ($trigger) {
            NotificationTrigger::SubmissionDecision => 'decision',
            NotificationTrigger::CommitteeDocument => 'document',
            NotificationTrigger::MeetingChanged => 'meeting',
            NotificationTrigger::RegistrationConfirmed => 'registration',
            NotificationTrigger::Announcement => 'announcement',
            NotificationTrigger::SectorInstrument,
            NotificationTrigger::ThreadComment => 'other',
        };
    }

    /* ------------------------------------------------------------------ */

    private static function localeOf(User $user): string
    {
        return Locales::isSupported($user->preferred_locale) ? $user->preferred_locale : Locales::FALLBACK;
    }

    private static function mayOpenMeeting(Meeting $meeting, User $user): bool
    {
        if (! Gate::forUser($user)->allows('view', $meeting)) {
            return false;
        }

        return $meeting->clearance === Clearance::Public || Gate::forUser($user)->allows('enter-member-area');
    }

    private static function meetingUrl(Meeting $meeting, User $user): string
    {
        $locale = self::localeOf($user);

        return $meeting->clearance === Clearance::Public && filled($meeting->slug)
            ? route('events.show', ['locale' => $locale, 'meeting' => $meeting->slug])
            : route('extranet.calendar', ['locale' => $locale]);
    }
}
