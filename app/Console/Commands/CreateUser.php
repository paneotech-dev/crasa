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
        {--locale=en : Interface language}
        {--generate : Generate the password and print it once, for scripted setup}
        {--sample : Flag the account as demonstration data, so the go-live purge removes it}
        {--length=20 : Length of a generated password}';

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

        /*
         * --generate exists so a scripted setup can create several accounts
         * at once. It does not weaken the rule above: there is still no way
         * to PASS a password in, which is the thing that lands in shell
         * history and in the process list. The command makes one, uses it,
         * and prints it once on stdout for the operator to capture.
         *
         * It refuses to generate for anything but a demonstration account.
         * A real account's password should be chosen by its holder, and a
         * generated one that is printed to a terminal has already been
         * seen by whoever was running the command.
         */
        if ($this->option('generate')) {
            if (! $this->option('sample')) {
                $this->error('--generate creates a password on a terminal, so it is only for demonstration');
                $this->error('accounts. Pass --sample as well, or set the password at the prompt.');

                return self::FAILURE;
            }

            $password = $this->generatePassword((int) $this->option('length'));
        } else {
            // secret() does not echo and does not enter shell history.
            $password = $this->secret('Password (not echoed)');
            $confirmation = $this->secret('Confirm password');

            if ($password !== $confirmation) {
                $this->error('The passwords do not match. Nothing was created.');

                return self::FAILURE;
            }
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
            'is_sample' => (bool) $this->option('sample'),
        ]);

        $user->password = Hash::make($password);
        $user->email_verified_at = now();
        $user->save();

        AuditLog::record(AuditLog::CREATED, $user, ['created_via' => 'console'], null);

        $this->info("Created {$user->email} as {$user->role->label()}.");

        if ($this->option('generate')) {
            // Printed once, on stdout, so a script can capture it. It is
            // not written to any file by this command and it is not
            // recoverable afterwards: the column holds a hash.
            $this->line('password: '.$password);
        }

        if ($this->option('sample')) {
            $this->warn('Flagged as demonstration data. `content:strip-samples` removes it before go-live.');
        }

        if ($user->role->requiresTwoFactor()) {
            $this->warn('Two-factor enrolment is mandatory for this role. The account reaches nothing');
            $this->warn('but the enrolment page until it is completed.');
        }

        return self::SUCCESS;
    }

    /**
     * A password from the cryptographic random source.
     *
     * The alphabet leaves out the characters that get transcribed wrong
     * when somebody reads one off a screen -- O and 0, l and 1 and I -- and
     * every symbol that a shell would treat as syntax, because these are
     * pasted into terminals. Length carries the strength instead: twenty
     * characters from a 60-character alphabet is about 118 bits.
     *
     * Str::password() is not used: it draws from a set including quotes and
     * backslashes, and one of those in a password meant to be pasted is a
     * support call.
     */
    private function generatePassword(int $length): string
    {
        // I, O, l and the digits they are mistaken for are all absent.
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $symbols = '-_.+=';

        $pick = fn (string $set) => $set[random_int(0, strlen($set) - 1)];

        // One of each class first, so a generated password cannot fail the
        // letters-and-numbers rule by chance.
        $characters = [$pick($upper), $pick($lower), $pick($digits), $pick($symbols)];
        $pool = $upper.$lower.$digits.$symbols;

        for ($i = count($characters); $i < max(12, $length); $i++) {
            $characters[] = $pick($pool);
        }

        // Fisher-Yates on the cryptographic source. str_shuffle draws from
        // the same seeded generator as rand(), which is not a source to
        // decide the order of a password with.
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }
}
