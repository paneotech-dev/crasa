<?php

namespace App\Notifications;

use App\Models\Submission;
use App\Notifications\Concerns\InApp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * RP-05, regulatory notifications.
 *
 * REGULATORY CONTENT ONLY. This tells a member state what happened to their
 * data return, or tells the Secretariat that one has arrived. Event and
 * travel notifications are reserved commercial scope, and keeping the two
 * visibly separate means there is no shared base class for someone to hang
 * a meeting reminder off later.
 *
 * In-app only, like every notice: mail stays off.
 */
class SubmissionStateChanged extends Notification
{
    use InApp, Queueable;

    public function __construct(public readonly Submission $submission) {}

    protected function defaultCategory(): string
    {
        return $this->submission->status->value === 'submitted' ? 'approval_request' : 'validation';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable->preferred_locale ?? 'en';
        $status = $this->submission->status->value;
        $params = ['country' => (string) $this->submission->country?->name, 'period' => (string) $this->submission->period];

        // The Secretariat opens a filed return in the review screen; the
        // member state opens its own return in the member area.
        $url = $status === 'submitted'
            ? route('admin.submissions.show', ['locale' => $locale, 'submission' => $this->submission->id])
            : route('submissions.edit', ['locale' => $locale, 'submission' => $this->submission->id]);

        return $this->withInApp([
            'type' => 'submission',
            'submission_id' => $this->submission->id,
            'country' => $this->submission->country?->name,
            'period' => $this->submission->period,
            'status' => $status,
            'title' => __('d3_notify.submission_'.$status, $params, $locale),
            'title_key' => 'd3_notify.submission_'.$status,
            'params' => $params,
            'url' => $url,
        ]);
    }
}
