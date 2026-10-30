<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Support\DeliveryCheck;
use App\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates an administrator, or sends an invited one a new invitation.
 *
 * The account is made the way screen 13 invites one: status Invited, a
 * random password nobody sees (not even the operator: it is never printed),
 * and an invitation from the seven-day invitations broker, through which the
 * holder chooses their own password. Choosing it makes the account active.
 *
 * Where mail is off (MAIL_MAILER log, array or null) the invitation would go
 * nowhere, so the one-time link is printed instead, with a line saying why.
 * Give it to the holder only.
 */
class CreateAdministrator extends Command
{
    protected $signature = 'crasa:create-admin
        {--name= : The administrator\'s full name}
        {--email= : The administrator\'s address}
        {--locale=en : Language of the invitation and the interface}
        {--reinvite : For an address that exists: send the invited administrator a new invitation}';

    protected $description = 'Create an administrator (or re-invite one) who sets their own password through the invitation link';

    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        $email = mb_strtolower(trim((string) $this->option('email')));
        $locale = (string) $this->option('locale');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'locale' => $locale],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:160'],
                'locale' => ['required', 'in:'.implode(',', Locales::SUPPORTED)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::query()->withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing !== null && ! $this->option('reinvite')) {
            $this->error("An account with the address {$email} exists. Nothing was changed.");
            $this->error('Pass --reinvite to send an invited administrator a new invitation.');

            return self::FAILURE;
        }

        if ($existing !== null) {
            if ($existing->role !== Role::Administrator || $existing->custom_role_id !== null) {
                $this->error("{$email} is not an administrator. Change its role in the back office instead. Nothing was changed.");

                return self::FAILURE;
            }

            if ($existing->status !== UserStatus::Invited) {
                $this->error("{$email} has already set a password (status: {$existing->status->value}). Nothing was changed.");
                $this->error('An active administrator uses "Forgotten password" on the sign-in page.');

                return self::FAILURE;
            }
        }

        $user = DB::transaction(function () use ($existing, $name, $email, $locale) {
            if ($existing !== null) {
                // Whatever the earlier invitation's holder might have known
                // is replaced, and the old link stops working when the new
                // token is written over it.
                $existing->forceFill([
                    'name' => $name,
                    'password' => Str::random(64),
                    'remember_token' => null,
                    'preferred_locale' => $locale,
                ])->save();

                AuditLog::record('invitation_resent', $existing, ['role' => Role::Administrator->value, 'via' => 'console'], null);

                return $existing;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'role' => Role::Administrator,
                'status' => UserStatus::Invited,
                'preferred_locale' => $locale,
                'password' => Str::random(64),
            ]);

            AuditLog::record(AuditLog::CREATED, $user, ['invited_as' => Role::Administrator->value, 'created_via' => 'console'], null);

            return $user;
        });

        $token = Password::broker('invitations')->createToken($user);
        $days = intdiv((int) config('auth.passwords.invitations.expire'), 1440);

        $this->info(($existing ? 'Re-invited ' : 'Created ').$user->email.' as an administrator. No password was set or shown: the holder chooses one through the invitation.');

        if (DeliveryCheck::mailIsSilent()) {
            $this->warn('Mail is off (MAIL_MAILER is "'.config('mail.default').'"), so no invitation was sent.');
            $this->warn('Give this one-time link to the administrator, and to nobody else. It works once and expires in '.$days.' day(s):');
            // On APP_URL, the host the administrator will sign in on.
            $this->line(rtrim((string) config('app.url'), '/').route('password.reset', ['locale' => $locale, 'token' => $token, 'email' => $user->email], false));

            return self::SUCCESS;
        }

        $user->notify(new AccountInvitation($token));
        $this->info('Invitation sent to '.$user->email.'. The link expires in '.$days.' day(s).');

        return self::SUCCESS;
    }
}
