<?php

namespace App\Notifications;

use App\Enums\NotificationTrigger;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
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
 * THE DATABASE ROW ALWAYS, THE EMAIL ONLY WHEN ASKED. The in-app centre is
 * the record, and it is written whatever the account's email preference is,
 * so turning email off does not mean losing the notice. Mail goes out only
 * for accounts subscribed to this trigger and set to receive it as it
 * happens; the rest is swept up by the digest.
 *
 * REGULATORY CONTENT ONLY. There is deliberately no shared base class
 * between this and SubmissionStateChanged for a meeting reminder to be hung
 * off later, and the trigger enum has no case for one.
 */
class RegulatoryActivity extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly NotificationTrigger $trigger,
        public readonly string $title,
        public readonly string $url,
        public readonly array $payload = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Only the decision on this reader's own return interrupts. Every
        // other trigger waits for the digest, because a channel that sends
        // one email per event becomes a channel nobody opens.
        if ($this->trigger === NotificationTrigger::SubmissionDecision
            && $this->wants($notifiable)
            && ($notifiable->digest_frequency ?? 'daily') !== 'never') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->preferred_locale ?? 'en';

        return (new MailMessage)
            ->subject(__($this->trigger->labelKey(), [], $locale))
            ->line($this->title)
            ->action(__('notifications.open', [], $locale), $this->url);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->trigger->value,
            'title' => $this->title,
            'url' => $this->url,
        ] + $this->payload;
    }

    private function wants(object $notifiable): bool
    {
        return in_array($this->trigger->value, $notifiable->notification_preferences ?? [], true);
    }
}
