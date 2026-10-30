<?php

namespace App\Support;

/**
 * Is outbound mail switched on?
 *
 * The one place that decides it. Every "e-mail is switched off" badge,
 * notice and line of copy reads this, so production (MAIL_MAILER=brevo)
 * drops them all at once and staging (MAIL_MAILER=log) keeps them.
 *
 * On means the default mailer hands messages to something that delivers
 * them. The log, array and null mailers accept a message and deliver it
 * nowhere, and an empty mailer is treated the same way.
 */
final class MailStatus
{
    /** Mailers that accept a message and deliver it nowhere. */
    public const SILENT = ['log', 'array', 'null', ''];

    public static function isOn(): bool
    {
        return ! in_array(self::mailer(), self::SILENT, true);
    }

    public static function isOff(): bool
    {
        return ! self::isOn();
    }

    /**
     * Off where it matters. In local development and in the test suite the
     * log mailer is the intended setting, so nothing is shown for it there.
     */
    public static function isOffInProduction(): bool
    {
        return self::isOff() && ! app()->environment('local', 'testing');
    }

    /** The default mailer's name, as configured. */
    public static function mailer(): string
    {
        return (string) config('mail.default');
    }
}
