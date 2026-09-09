<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Authority;
use App\Models\Country;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Creates an account. The password is prompted for, never passed as an
 * argument.
 *
 * An argument lands in shell history, in the process list while the command
 * runs, and in any terminal capture. That is precisely how a demonstration
 * password reached a circulated PDF on this project, so the command has no
 * password option at all: there is nothing to pass by accident.
 *
 * There is no seeder that creates accounts. Real accounts are made here, by
 * the Secretariat, at handover.
 */
class CreateUser extends Command
{
    /*
     * user:create, not crasa:user.
     *
     * Renamed 9 September 2026. Every other command on this platform is
     * namespace:verb — content:strip-samples, security:harden-uploads,
     * images:derivatives — and this one was the odd one out.
     *
     * It also matters more than tidiness: docs/DEMO_SCRIPT.md and the
     * demonstration seeder both told the operator to run `user:create`, and
     * that command did not exist. On the day of the workshop, the
     * instruction for creating the four demonstration accounts would have
     * failed at the prompt.
     */
    protected $signature = 'user:create
        {--email= : The account address}
        {--name= : The account holder\'s name}
        {--role= : administrator, editor, member_state_rep, committee_member or viewer}
        {--country= : ISO code of the member state, for country-scoped roles}
        {--authority= : Acronym of the authority, which must belong to that country}
        {--locale=en : Interface language}';

    protected $description = 'Create a user account, prompting for the password';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email address');
        $name = $this->option('name') ?: $this->ask('Full name');
        $role = $this->option('role') ?: $this->choice(
            'Role',
            array_column(Role::cases(), 'value'),
            Role::Viewer->value
        );

        $countryId = null;
        $authorityId = null;

        if ($iso = $this->option('country')) {
            $country = Country::where('iso_code', strtolower($iso))->first();

            if ($country === null) {
                $this->error("No member state with ISO code [{$iso}].");

                return self::FAILURE;
            }

            $countryId = $country->id;
        }

        if ($acronym = $this->option('authority')) {
            $authority = Authority::where('acronym', strtoupper($acronym))->first();

            if ($authority === null) {
                $this->error("No authority with acronym [{$acronym}].");

                return self::FAILURE;
            }

            $authorityId = $authority->id;
            $countryId ??= $authority->country_id;
        }

        // secret() does not echo and does not enter shell history.
        $password = $this->secret('Password (not echoed)');
        $confirmation = $this->secret('Confirm password');

        if ($password !== $confirmation) {
            $this->error('The passwords do not match. Nothing was created.');

            return self::FAILURE;
        }

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'password' => $password,
            'locale' => $this->option('locale'),
        ], [
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:'.implode(',', array_column(Role::cases(), 'value'))],
            'password' => ['required', PasswordRule::min(12)->letters()->numbers()],
            'locale' => ['required', 'in:'.implode(',', Locales::SUPPORTED)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'role' => Role::from($role),
            'status' => UserStatus::Active,
            'country_id' => $countryId,
            'authority_id' => $authorityId,
            'preferred_locale' => $this->option('locale'),
        ]);

        $user->password = Hash::make($password);
        $user->email_verified_at = now();
        $user->save();

        AuditLog::record(AuditLog::CREATED, $user, ['created_via' => 'console'], null);

        $this->info("Created {$user->email} as {$user->role->label()}.");

        if ($user->role->requiresTwoFactor()) {
            $this->warn('Two-factor enrolment is mandatory for this role. The account reaches nothing');
            $this->warn('but the enrolment page until it is completed.');
        }

        return self::SUCCESS;
    }
}
