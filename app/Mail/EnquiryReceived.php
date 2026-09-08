<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the Secretariat. Queued: the enquiry is already stored by the
 * time this runs, so a mail outage delays the notification rather than
 * losing the enquiry.
 */
class EnquiryReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Website enquiry: '.$this->enquiry->subject,
            replyTo: [$this->enquiry->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.enquiry-received');
    }
}
