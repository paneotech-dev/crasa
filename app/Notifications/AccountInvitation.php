<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation an administrator sends from screen 13.
 *
 * It used to be the framework's password reset mail, so a person invited to
 * the platform was told they had asked to reset a password they never had.
 * This says what it is: who invited them, to what, and how long the link
 * lasts. It goes out in the invitee's language (User::preferredLocale), not
 * in the language of the administrator who pressed the button.
 *
 * The token comes from the "invitations" broker, which lasts seven days: an
 * invitation crosses fourteen countries and several inboxes, and the reset
 * broker's sixty minutes expired before most people opened it.
 */
class AccountInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly ?string $invitedBy = null,
    ) {}

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

        $days = intdiv((int) config('auth.passwords.invitations.expire'), 1440);

        return (new MailMessage)
            ->subject(__('auth.invite_mail_subject'))
            ->view(['mail.account-link', 'mail.account-link-text'], [
                'greeting' => __('auth.mail_greeting', ['name' => $notifiable->name]),
                'lines' => [
                    $this->invitedBy
                        ? __('auth.invite_mail_body_by', ['inviter' => $this->invitedBy, 'role' => $notifiable->role->label()])
                        : __('auth.invite_mail_body', ['role' => $notifiable->role->label()]),
                    __('auth.invite_mail_choose'),
                ],
                'action' => __('auth.invite_mail_action'),
                'url' => $url,
                'after' => [
                    trans_choice('auth.invite_mail_expiry', $days, ['days' => $days]),
                    __('auth.invite_mail_ignore'),
                ],
                'signature' => __('auth.mail_signature'),
                'fallback' => __('auth.mail_link_fallback'),
            ]);
    }
}
