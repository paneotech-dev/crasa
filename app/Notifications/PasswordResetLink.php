<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The password reset mail, in the account holder's own language.
 *
 * Replaces the framework's ResetPassword notification, whose wording is
 * English-only JSON strings this project does not carry ("Hello!",
 * "Reset Password", "Regards,"), so a reset requested from /fr or /pt
 * arrived in English. The link opens in the holder's preferred language.
 */
class PasswordResetLink extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $locale = Locales::isSupported($notifiable->preferred_locale) ? $notifiable->preferred_locale : Locales::FALLBACK;

        $url = route('password.reset', [
            'locale' => $locale,
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        $minutes = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject(__('auth.reset_mail_subject'))
            ->view(['mail.account-link', 'mail.account-link-text'], [
                'greeting' => __('auth.mail_greeting', ['name' => $notifiable->name]),
                'lines' => [__('auth.reset_mail_body')],
                'action' => __('auth.reset_mail_action'),
                'url' => $url,
                'after' => [
                    __('auth.reset_mail_expiry', ['minutes' => $minutes]),
                    __('auth.reset_mail_ignore'),
                ],
                'signature' => __('auth.mail_signature'),
                'fallback' => __('auth.mail_link_fallback'),
            ]);
    }
}
