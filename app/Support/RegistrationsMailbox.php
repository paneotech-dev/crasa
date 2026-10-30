<?php

namespace App\Support;

use App\Mail\MeetingChangedForDelegate;
use App\Mail\RegistrationCopyForMailbox;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mail around delegate registrations that is not the delegate's own copy
 * (client item 1e): the CRASA mailbox's copy of each filing, and the notice
 * to every registered delegate when their meeting changes.
 *
 * Everything is queued. While outbound mail is off it lands in the mail
 * log; a failure to queue is logged and never stops the filing or the edit.
 */
final class RegistrationsMailbox
{
    /** The mailbox set in Settings, or the Secretariat's contact address. */
    public static function address(): string
    {
        return SiteSetting::get('registrations_mailbox') ?? SiteContact::email();
    }

    public static function copy(DelegateRegistration $registration): void
    {
        try {
            Mail::to(self::address())->queue(new RegistrationCopyForMailbox($registration->fresh(['meeting', 'country', 'authority'])));
        } catch (Throwable $e) {
            Log::error('A delegate registration copy for the CRASA mailbox could not be queued.', [
                'registration' => $registration->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * What the change is, as a delegate reads it, or null when nothing a
     * delegate needs to hear about changed.
     */
    public static function changeOf(Meeting $meeting): ?string
    {
        if ($meeting->wasChanged('status') && $meeting->status === 'cancelled') {
            return 'cancelled';
        }
        if ($meeting->isCancelled()) {
            return null;
        }
        if ($meeting->wasChanged('status') && $meeting->status === 'postponed') {
            return 'postponed';
        }
        if ($meeting->wasChanged(['starts_on', 'ends_on'])) {
            return 'new_date';
        }

        return null;
    }

    /**
     * One mail to every delegate registered for the meeting (filed or
     * confirmed; a declined filing is not a registration). Returns how many
     * were queued.
     */
    public static function noticeDelegates(Meeting $meeting, string $change): int
    {
        $queued = 0;

        DelegateRegistration::query()->withoutGlobalScopes()
            ->where('meeting_id', $meeting->id)
            ->where(fn ($q) => $q->where('status', '!=', 'declined')->orWhereNull('status'))
            ->get()
            ->each(function (DelegateRegistration $registration) use ($meeting, $change, &$queued) {
                $registration->setRelation('meeting', $meeting);
                $locale = in_array($registration->preferred_language, DelegateRegistration::LANGUAGES, true)
                    ? $registration->preferred_language : config('app.fallback_locale', 'en');

                try {
                    Mail::to($registration->delegate_email)
                        ->locale($locale)
                        ->queue(new MeetingChangedForDelegate($registration, $change));
                    $queued++;
                } catch (Throwable $e) {
                    Log::error('A meeting change notice to a delegate could not be queued.', [
                        'registration' => $registration->id, 'error' => $e->getMessage(),
                    ]);
                }
            });

        return $queued;
    }
}
