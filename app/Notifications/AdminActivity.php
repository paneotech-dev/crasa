<?php

namespace App\Notifications;

use App\Notifications\Concerns\InApp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Another administrator acted on an account, a role or a publication (F10). */
class AdminActivity extends Notification
{
    use InApp, Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $line,
        public readonly string $url,
    ) {}

    protected function defaultCategory(): string
    {
        return 'other';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->withInApp(['type' => 'admin_activity', 'event' => $this->event, 'title' => $this->line, 'url' => $this->url]);
    }
}
