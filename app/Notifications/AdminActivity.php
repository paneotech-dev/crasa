<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Another administrator acted on an account, a role or a publication (F10). */
class AdminActivity extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $line,
        public readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'admin_activity', 'event' => $this->event, 'title' => $this->line, 'url' => $this->url];
    }
}
