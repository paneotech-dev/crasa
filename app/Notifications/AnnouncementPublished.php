<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Concerns\InApp;
use Illuminate\Bus\Queueable;
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
    use InApp, Queueable;

    public function __construct(public readonly Announcement $announcement) {}

    protected function defaultCategory(): string
    {
        return 'announcement';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->withInApp([
            'type' => 'announcement',
            'announcement_id' => $this->announcement->id,
            'slug' => $this->announcement->slug,
            'title' => $this->announcement->translated('title', '', $notifiable->preferred_locale ?? 'en'),
            'url' => route('announcements.show', ['locale' => $notifiable->preferred_locale ?? 'en', 'announcement' => $this->announcement->slug]),
        ]);
    }
}
