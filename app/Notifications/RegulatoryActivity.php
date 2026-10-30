<?php

namespace App\Notifications;

use App\Enums\NotificationTrigger;
use App\Notifications\Concerns\InApp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Something regulatory happened that this reader asked to hear about.
 *
 * ONE CLASS FOR FIVE TRIGGERS. A new guideline, a document filed into a
 * committee, a meeting moved, a reply on a thread: they differ in what they
 * point at and not in how they behave. Five classes would be five places to
 * change the digest rule, and the trigger is already a closed enum that the
 * preferences screen and the audience resolver both read.
 *
 * IN-APP ONLY. The bell and the notifications page are the record. Mail
 * stays off on this platform; the digest command is the only mail path and
 * it reads these rows.
 *
 * REGULATORY CONTENT ONLY. There is deliberately no shared base class
 * between this and SubmissionStateChanged for a meeting reminder to be hung
 * off later, and the trigger enum has no case for one.
 */
class RegulatoryActivity extends Notification
{
    use InApp, Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly NotificationTrigger $trigger,
        public readonly string $title,
        public readonly string $url,
        public readonly array $payload = [],
    ) {}

    protected function defaultCategory(): string
    {
        return \App\Support\Notifier::categoryOfTrigger($this->trigger);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->withInApp([
            'type' => $this->trigger->value,
            'title' => $this->title,
            'url' => $this->url,
        ] + $this->payload);
    }
}
