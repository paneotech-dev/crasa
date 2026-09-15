<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The digest email. RP-05.
 *
 * A mailable rather than a bare Mail::send, so the subject is built in the
 * reader's own language in one place and so a test can assert what went
 * out rather than that something did.
 */
class NotificationDigest extends Mailable
{
    use Queueable, SerializesModels;

    /** @param Collection<int, \Illuminate\Notifications\DatabaseNotification> $items */
    public function __construct(
        public readonly User $user,
        public readonly Collection $items,
    ) {}

    public function envelope(): Envelope
    {
        $locale = $this->user->preferred_locale ?? 'en';

        return new Envelope(
            subject: __('notifications.digest_subject', ['count' => $this->items->count()], $locale),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.digest',
            with: [
                'user' => $this->user,
                'items' => $this->items,
                'locale' => $this->user->preferred_locale ?? 'en',
            ],
        );
    }
}
