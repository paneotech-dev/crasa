<?php

namespace App\Mail;

use App\Models\DelegateRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A registered delegate told that their meeting was postponed, cancelled
 * or given a new date (client item 1e). One per change, sent when the
 * change is saved; nothing schedules a second one.
 */
class MeetingChangedForDelegate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  string  $change  cancelled, postponed or new_date */
    public function __construct(public DelegateRegistration $registration, public string $change) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('m3_deleg.change_subject_'.$this->change, [
                'meeting' => $this->registration->meeting->title,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.m3-meeting-changed');
    }
}
