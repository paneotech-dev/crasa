<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A Secretariat announcement reaching the membership. RP-05.
 *
 * REGULATORY CONTENT ONLY, like SubmissionStateChanged. An announcement is
 * the Secretariat telling the fourteen regulators something about the
 * work: a deadline, a decision, a document. Nothing here schedules,
 * reminds about, or arranges anything, and there is deliberately no shared
 * base class between the notification types for a meeting reminder to be
 * hung off later.
 */
class AnnouncementPublished extends Notification
{
    use Queueable;

    public function __construct(public readonly Announcement $announcement) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // The database channel always, so the notice survives a mail
        // failure and can be read in the platform. Mail only where the
        // account has asked for announcements.
        $channels = ['database'];

        if (in_array('announcements', $notifiable->notification_preferences ?? [], true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->preferred_locale ?? 'en';

        return (new MailMessage)
            ->subject(__('announcements.notify_subject', [], $locale))
            ->line($this->announcement->translated('title', '', $locale))
            ->action(__('announcements.notify_action', [], $locale), route('announcements.show', [
                'locale' => $locale,
                'announcement' => $this->announcement->slug,
            ]));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement',
            'announcement_id' => $this->announcement->id,
            'slug' => $this->announcement->slug,
            'title' => $this->announcement->translated('title'),
        ];
    }
}
