<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Moderation news, in the bell (F2): an item waiting for a decision, or the
 * decision on an item the reader sent. Database channel only; mail stays off
 * and a held copy is written by AdminAlerts where an e-mail is wanted.
 */
class ApprovalActivity extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $subject,
        public readonly string $url,
        public readonly string $actor,
        public readonly ?string $comment = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable->preferred_locale ?? 'en';

        return [
            'type' => 'approval',
            'event' => $this->event,
            'title' => __('fa.notify_'.$this->event, ['subject' => $this->subject, 'actor' => $this->actor], $locale),
            'comment' => $this->comment,
            'url' => $this->url,
        ];
    }
}
