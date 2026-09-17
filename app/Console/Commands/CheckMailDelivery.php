<?php

namespace App\Console\Commands;

use App\Support\DeliveryCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One real message, sent now, to prove the mail settings work.
 *
 * The dashboard banner says mail is off while the driver is a silent one.
 * It cannot say the reverse: an SMTP driver with a wrong key accepts the
 * configuration and fails on the first send, which on this project would be
 * a delegate's password reset. This sends synchronously, outside the queue,
 * so the answer, or the relay's refusal, is on the screen.
 *
 * Arrival still has to be checked in the inbox: an accepted message can be
 * filed as spam. The subject carries a timestamp so it can be found.
 */
class CheckMailDelivery extends Command
{
    protected $signature = 'mail:check {to : Address to send the test message to}';

    protected $description = 'Send one test message now and report whether the relay accepted it';

    public function handle(): int
    {
        $to = (string) $this->argument('to');

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Not an email address: '.$to);

            return self::FAILURE;
        }

        if (DeliveryCheck::mailIsSilent()) {
            $this->error('MAIL_MAILER is "'.config('mail.default').'", which delivers nothing. Set the SMTP settings first: see docs/MAIL_ACTION.md.');

            return self::FAILURE;
        }

        if (DeliveryCheck::fromAddressIsUnset()) {
            $this->error('MAIL_FROM_ADDRESS is "'.config('mail.from.address').'". Set it to an address on the sending domain.');

            return self::FAILURE;
        }

        $subject = config('app.name').' mail check '.now()->toIso8601String();

        try {
            Mail::raw(
                'This message was sent by `php artisan mail:check` on '.config('app.url').".\n"
                .'Mailer: '.config('mail.default').', from: '.config('mail.from.address').".\n"
                .'If it arrived, the platform can send password resets, enquiry receipts and notifications.',
                fn ($message) => $message->to($to)->subject($subject)
            );
        } catch (Throwable $e) {
            $this->error('The relay refused the message: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Accepted by '.config('mail.default').' ('.config('mail.mailers.'.config('mail.default').'.host', 'no host').') from '.config('mail.from.address').'.');
        $this->line('Now check the inbox of '.$to.' for "'.$subject.'", including the spam folder.');

        return self::SUCCESS;
    }
}
