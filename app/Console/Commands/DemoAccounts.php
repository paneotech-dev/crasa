<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The workshop's demonstration accounts, one or two per role (F6).
 *
 * Creates only the accounts that do not exist. An account that exists is
 * not touched: not its password, not its role, not its seats. Each new
 * account gets a four-word password, which is appended to the credentials
 * file under "Day 2 accounts" and never printed: the terminal shows emails
 * and roles only.
 *
 * A role this build does not have (Moderator, Auditor before the roles work
 * is merged) is skipped with a message, and the command can be run again
 * after the merge to add them. Two-factor stays off for every account.
 */
class DemoAccounts extends Command
{
    protected $signature = 'crasa:demo-accounts
        {--file= : The credentials file to append the new passwords to (required)}
        {--dry-run : List what would be created, create nothing}';

    protected $description = 'Create the missing demonstration accounts for every role and append their passwords to the credentials file';

    /**
     * email, role value, display name, country ISO, authority acronym, committee slug.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: ?string, 4: ?string, 5: ?string}>
     */
    public const ACCOUNTS = [
        ['admin@crasa.org', 'administrator', 'Demo Administrator', null, null, null],
        ['admin2@crasadev.invalid', 'administrator', 'Demo Administrator 2', null, null, null],
        ['demo.moderator@crasadev.invalid', 'moderator', 'Demo Moderator', null, null, null],
        ['demo.moderator2@crasadev.invalid', 'moderator', 'Demo Moderator 2', null, null, null],
        ['demo.editor@crasadev.invalid', 'editor', 'Demo Editor', null, null, null],
        ['demo.editor2@crasadev.invalid', 'editor', 'Demo Editor 2', null, null, null],
        ['demo.member@crasadev.invalid', 'member_state_rep', 'Demo Member State Representative (ICASA)', 'za', 'ICASA', null],
        ['demo.member.mw@crasadev.invalid', 'member_state_rep', 'Demo Member State Representative (MACRA)', 'mw', 'MACRA', null],
        ['demo.member.na@crasadev.invalid', 'member_state_rep', 'Demo Member State Representative (CRAN)', 'na', 'CRAN', null],
        ['demo.committee@crasadev.invalid', 'committee_member', 'Demo Committee Member', null, null, 'spectrum-and-frequency-management'],
        ['demo.committee2@crasadev.invalid', 'committee_member', 'Demo Committee Member 2', null, null, 'postal-and-logistics'],
        ['demo.viewer@crasadev.invalid', 'viewer', 'Demo Viewer', null, null, null],
        ['demo.viewer2@crasadev.invalid', 'viewer', 'Demo Viewer 2', null, null, null],
        ['demo.auditor@crasadev.invalid', 'auditor', 'Demo Auditor', null, null, null],
    ];

    /** Short, common, unambiguous words, easy to read out in a room. */
    private const WORDS = [
        'acorn', 'amber', 'anchor', 'apple', 'arrow', 'aspen', 'badge', 'baker', 'banjo', 'basil',
        'beacon', 'birch', 'bison', 'blossom', 'bonfire', 'border', 'bridge', 'brook', 'bucket', 'button',
        'cabin', 'cactus', 'camel', 'candle', 'canoe', 'canyon', 'carbon', 'cargo', 'castle', 'cedar',
        'cello', 'chalk', 'cherry', 'cider', 'cinder', 'citrus', 'clover', 'cobalt', 'comet', 'copper',
        'coral', 'cotton', 'crane', 'crayon', 'cricket', 'crystal', 'daisy', 'delta', 'desert', 'dolphin',
        'dragon', 'drum', 'eagle', 'ember', 'falcon', 'fern', 'fiddle', 'field', 'flint', 'forest',
        'fossil', 'fountain', 'garden', 'garnet', 'giraffe', 'glacier', 'granite', 'gravel', 'harbour', 'hazel',
        'heron', 'hollow', 'honey', 'island', 'ivory', 'jasmine', 'juniper', 'kettle', 'kiwi', 'lagoon',
        'lantern', 'lemon', 'lilac', 'linen', 'lizard', 'lotus', 'magnet', 'mango', 'maple', 'marble',
        'meadow', 'melon', 'meteor', 'mint', 'monsoon', 'mosaic', 'nectar', 'nutmeg', 'oasis', 'olive',
        'onyx', 'orbit', 'orchid', 'otter', 'paddle', 'panda', 'paper', 'pebble', 'pepper', 'pillow',
        'pine', 'planet', 'plum', 'pocket', 'pollen', 'pony', 'prairie', 'pumpkin', 'quartz', 'quill',
        'rabbit', 'radar', 'raven', 'reef', 'ribbon', 'river', 'rocket', 'saddle', 'saffron', 'salmon',
        'savanna', 'scarf', 'shell', 'signal', 'silver', 'sparrow', 'spruce', 'stone', 'summit', 'sunset',
        'tango', 'thistle', 'thunder', 'timber', 'tulip', 'tundra', 'valley', 'velvet', 'violin', 'walnut',
        'willow', 'window', 'winter', 'yellow', 'zebra',
    ];

    public function handle(): int
    {
        $file = (string) $this->option('file');
        $dry = (bool) $this->option('dry-run');

        if ($file === '') {
            $this->error('Give the credentials file: --file=/path/to/credentials.txt');

            return self::INVALID;
        }

        if (! $dry && file_exists($file) && ! is_writable($file)) {
            $this->error("The credentials file is not writable: {$file}. Nothing was created.");

            return self::FAILURE;
        }

        if (! $dry && ! file_exists($file) && ! is_writable(dirname($file))) {
            $this->error('The folder for the credentials file is not writable. Nothing was created.');

            return self::FAILURE;
        }

        $created = [];
        $rows = [];

        foreach (self::ACCOUNTS as [$email, $roleValue, $name, $iso, $acronym, $committeeSlug]) {
            $role = Role::tryFrom($roleValue);

            if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
                $rows[] = [$email, $role?->label() ?? $roleValue, 'exists, left as it is'];

                continue;
            }

            if ($role === null) {
                $rows[] = [$email, $roleValue, 'skipped: this build has no '.$roleValue.' role yet'];

                continue;
            }

            $country = $iso ? Country::whereRaw('LOWER(iso_code) = ?', [$iso])->first() : null;
            $authority = $acronym && $country
                ? Authority::withoutGlobalScopes()->where('country_id', $country->id)->where('acronym', $acronym)->first()
                : null;
            $committee = $committeeSlug ? Committee::where('slug', $committeeSlug)->first() : null;

            if (($iso && ! $country) || ($acronym && ! $authority) || ($committeeSlug && ! $committee)) {
                $rows[] = [$email, $role->label(), 'skipped: its country, authority or committee is not in this database'];

                continue;
            }

            if ($dry) {
                $rows[] = [$email, $role->label(), 'would be created'];

                continue;
            }

            $password = $this->passphrase();

            DB::transaction(function () use ($email, $name, $role, $country, $authority, $committee, $password): void {
                $user = new User([
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'status' => UserStatus::Active,
                    'country_id' => $country?->id,
                    'authority_id' => $authority?->id,
                    'preferred_locale' => 'en',
                    'is_sample' => true,
                ]);
                $user->password = Hash::make($password);
                $user->email_verified_at = now();
                $user->save();

                if ($committee) {
                    $user->committees()->attach($committee->id, ['role' => 'member', 'is_sample' => true]);
                }
            });

            $created[] = [$email, $role->label(), $password];
            $rows[] = [$email, $role->label(), 'created'];
        }

        if ($created !== []) {
            $lines = ['', 'Day 2 accounts ('.now()->timezone(config('crasa.timezone', 'Africa/Gaborone'))->format('Y-m-d H:i').')'];
            // The layout of the lines already in the file: email, role,
            // password last.
            foreach ($created as [$email, $roleLabel, $password]) {
                $lines[] = str_pad($email, 34).str_pad($roleLabel, 32).$password;
            }
            file_put_contents($file, implode(PHP_EOL, $lines).PHP_EOL, FILE_APPEND | LOCK_EX);
            @chmod($file, 0600);
        }

        // Emails and roles only. The passwords are in the file.
        $this->table(['Email', 'Role', 'Result'], $rows);
        $this->info(count($created).' account(s) created'.($created !== [] ? '; passwords appended to the credentials file.' : '.'));

        return self::SUCCESS;
    }

    /**
     * Four capitalised words and two digits, from a cryptographic source:
     * the same shape as the Day 1 passwords, and it meets the password
     * rule (letters and numbers, twelve characters or more).
     */
    private function passphrase(): string
    {
        $words = [];
        for ($i = 0; $i < 4; $i++) {
            $words[] = ucfirst(self::WORDS[random_int(0, count(self::WORDS) - 1)]);
        }

        return implode('', $words).str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
    }
}
