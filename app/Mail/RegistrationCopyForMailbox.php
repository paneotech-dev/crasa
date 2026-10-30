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
 * The CRASA registrations mailbox's copy of a filing (client item 1e).
 *
 * Queued like the delegate's own copy. The address is a setting
 * (RegistrationsMailbox); while outbound mail is off the copy is written to
 * the mail log and the filing is on the back office list either way.
 */
class RegistrationCopyForMailbox extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public DelegateRegistration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('m3_deleg.copy_subject', [
                'name' => $this->registration->delegate_name,
                'meeting' => $this->registration->meeting->title,
            ]),
            replyTo: [$this->registration->delegate_email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.m3-registration-copy');
    }
}
