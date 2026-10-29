<?php

namespace App\Notifications;

use App\Notifications\Concerns\InApp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Moderation news, in the bell (F2): an item waiting for a decision, or the
 * decision on an item the reader sent. Database channel only; mail stays off
 * and a held copy is written by AdminAlerts where an e-mail is wanted.
 */
class ApprovalActivity extends Notification
{
    use InApp, Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $subject,
        public readonly string $url,
        public readonly string $actor,
        public readonly ?string $comment = null,
    ) {}

    protected function defaultCategory(): string
    {
        return $this->event === 'approval_submitted' ? 'approval_request' : 'decision';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable->preferred_locale ?? 'en';
        $params = ['subject' => $this->subject, 'actor' => $this->actor];

        return $this->withInApp([
            'type' => 'approval',
            'event' => $this->event,
            'title' => __('fa.notify_'.$this->event, $params, $locale),
            'title_key' => 'fa.notify_'.$this->event,
            'params' => $params,
            'comment' => $this->comment,
            'url' => $this->url,
        ]);
    }
}
