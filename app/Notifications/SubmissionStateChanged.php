<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * RP-05, regulatory notifications.
 *
 * REGULATORY CONTENT ONLY. This tells a member state what happened to their
 * data return, or tells the Secretariat that one has arrived. It is
 * deliberately the only notification class on the platform: event and
 * travel notifications are reserved commercial scope, and keeping the two
 * visibly separate means there is no shared base class for someone to hang
 * a meeting reminder off later.
 */
class SubmissionStateChanged extends Notification
{
    use Queueable;

    public function __construct(public readonly Submission $submission) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // The database channel always, so the notice survives a mail
        // failure and can be read in the platform. Mail only where the
        // account has asked for submission notices.
        $channels = ['database'];

        if (in_array('submissions', $notifiable->notification_preferences ?? [], true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = 'submissions.notify_'.$this->submission->status->value;

        $mail = (new MailMessage)
            ->subject(__($key.'_subject', [
                'country' => $this->submission->country?->name,
                'period' => $this->submission->period,
            ]))
            ->line(__($key.'_body', [
                'country' => $this->submission->country?->name,
                'period' => $this->submission->period,
            ]));

        if (filled($this->submission->review_note)) {
            $mail->line($this->submission->review_note);
        }

        return $mail->action(__('submissions.notify_action'), route('submissions.edit', [
            'locale' => $notifiable->preferred_locale ?? 'en',
            'submission' => $this->submission->id,
        ]));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'submission',
            'submission_id' => $this->submission->id,
            'country' => $this->submission->country?->name,
            'period' => $this->submission->period,
            'status' => $this->submission->status->value,
        ];
    }
}
