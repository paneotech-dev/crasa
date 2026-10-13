<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Is outbound mail actually going anywhere?
 *
 * MAIL_MAILER=log is the correct default for local work and a silent
 * failure everywhere else. Nothing throws, no queue job fails, no page shows
 * an error: a member state representative submits data, the platform tells
 * them the Secretariat has been notified, and the notification is written to
 * storage/logs/laravel.log where nobody will look for it.
 *
 * That failure is invisible for exactly as long as nobody thinks to check,
 * which on this project would be until a validation workshop where somebody
 * asks why they never received the email.
 *
 * So the platform says so, loudly, once per boot: in the log at error level,
 * and on every administration screen as a banner the Secretariat cannot miss.
 *
 * This is a check, not a guard. It never blocks a request and never throws.
 * A platform that refused to serve pages because mail was misconfigured
 * would be a worse outcome than one that delivers no mail.
 */
final class DeliveryCheck
{
    /** Drivers that accept a message and deliver it nowhere. */
    private const SILENT = ['log', 'array', 'null'];

    private static bool $warned = false;

    /** Whether mail is being swallowed rather than sent. */
    public static function mailIsSilent(): bool
    {
        return in_array((string) config('mail.default'), self::SILENT, true);
    }

    /**
     * Whether that matters here. It does not in local development, which is
     * the whole reason the log driver exists.
     */
    public static function mailIsSilentInProduction(): bool
    {
        return self::mailIsSilent() && ! app()->environment('local', 'testing');
    }

    /** The from address the framework ships with, still in place. */
    public static function fromAddressIsUnset(): bool
    {
        $from = (string) config('mail.from.address');

        return $from === '' || str_ends_with($from, '@example.com');
    }

    /**
     * Written once per boot, at error level, naming the file to change and
     * the document that says how. A warning a reader cannot act on is a
     * warning they learn to scroll past.
     */
    public static function report(): void
    {
        // CRASA_MAIL_LOG_WARNING=false stops the log entry while mail is off
        // on purpose (it was written on every scheduler tick, about 1,700 a
        // day). The banner on the administration screens is unaffected.
        if (self::$warned || app()->environment('testing') || ! (bool) config('crasa.mail_log_warning', true)) {
            return;
        }

        self::$warned = true;

        if (self::mailIsSilentInProduction()) {
            Log::error('Mail is not being delivered. MAIL_MAILER is "'.config('mail.default').'" and APP_ENV is "'.app()->environment().'", so every notification this platform sends is written to the log and goes nowhere. Submission notifications, enquiry receipts and password resets are all affected. Set the mail driver in .env: see docs/MAIL_SETUP.md.');
        }

        if (! self::mailIsSilent() && self::fromAddressIsUnset()) {
            Log::error('Mail is configured to send but MAIL_FROM_ADDRESS is still "'.config('mail.from.address').'". Mail from an example.com address is rejected or filed as spam by most receiving servers. Set it to an address on CRASA\'s own domain: see docs/MAIL_SETUP.md.');
        }
    }
}
