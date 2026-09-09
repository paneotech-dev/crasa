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
 * The delegate's own copy of what they filed.
 *
 * Queued, on the same terms as the enquiry notification: the registration
 * is already stored by the time this runs, so a mail outage delays the
 * confirmation rather than losing the filing.
 *
 * IT CONFIRMS AND IT DOES NOT REMIND. This is sent once, when the form is
 * filed, and nothing schedules a second one. A reminder before the meeting,
 * a chase for missing details or a request for feedback afterwards are the
 * managing half and belong to the Event Management Platform.
 */
class DelegateRegistrationFiled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public DelegateRegistration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('delegates.confirmation_subject', [
                'meeting' => $this->registration->meeting->title,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.delegate-registration-filed');
    }
}
