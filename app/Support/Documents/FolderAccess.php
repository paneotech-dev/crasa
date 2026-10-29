<?php

namespace App\Support\Documents;

use App\Enums\Clearance;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use App\Support\PermissionMatrix;
use Illuminate\Support\Facades\DB;

/**
 * Who may see a folder, upload into it and validate what is in it.
 *
 * A folder holds its own rules or none. None means "as the folder above",
 * so a subfolder follows its parent until somebody changes it, and a root
 * folder with none keeps the rule it has always had: its member state, its
 * committee, or everyone signed in, with the Secretariat uploading and
 * validating.
 *
 * Each of the three abilities names one audience:
 *
 *   signed_in   everyone signed in
 *   roles       one or several roles
 *   country     the people of one member state
 *   committee   the seats on one committee
 *   users       named people
 *   secretariat nobody beyond the Secretariat (upload and validate only)
 *
 * THE COLUMNS FOLLOW THE RULE. A folder seen by one member state carries
 * that country_id, and one seen by one committee that committee_id, so the
 * global country scope (which is what keeps another member state's folders
 * out of every listing, search and URL) keeps working on rules set here.
 * sync() writes that, down the tree and onto the files.
 */
final class FolderAccess
{
    public const ABILITIES = ['view', 'upload', 'validate'];

    public const TYPES = ['signed_in', 'roles', 'country', 'committee', 'users', 'secretariat'];

    /**
     * The roles a rule may name: those that can reach the member area,
     * where folders are read. Taken from the grant behind the
     * enter-member-area gate (ReachCommitteeWorkspace) rather than listed,
     * so a viewer, moderator or auditor, who never reach a folder, is not
     * offered, and a role given that permission on the roles screen is.
     *
     * @return list<Role>
     */
    public static function roles(): array
    {
        return array_values(array_filter(
            Role::cases(),
            fn (Role $role) => PermissionMatrix::grant($role, Permission::ReachCommitteeWorkspace)->allowsAnything(),
        ));
    }

    /**
     * The rules that apply to a folder and the folder they come from: its
     * own, or the nearest folder above that has some. Null rules mean the
     * built-in rule of the folder's member state or committee.
     *
     * @return array{rules: ?array<string, array<string, mixed>>, from: ?Folder}
     */
    public static function effective(Folder $folder): array
    {
        $current = $folder;
        $guard = 0;

        while ($current !== null && $guard++ < 10) {
            if (is_array($current->access) && $current->access !== []) {
                return ['rules' => $current->access, 'from' => $current];
            }

            $current = $current->parent_id
                ? Folder::unscoped()->find($current->parent_id)
                : null;
        }

        return ['rules' => null, 'from' => null];
    }

    /**
     * The rule for one ability, written out, built-in rules included. Used
     * to fill the form and to say it in words.
     *
     * @return array{type: string, roles: list<string>, country_id: ?int, committee_id: ?int, user_ids: list<int>}
     */
    public static function ruleFor(Folder $folder, string $ability): array
    {
        $rules = self::effective($folder)['rules'];

        if ($rules !== null && isset($rules[$ability])) {
            return self::clean($rules[$ability]);
        }

        // The built-in rule of a folder nobody has set rules on.
        if ($ability === 'validate') {
            return self::clean(['type' => 'secretariat']);
        }

        if ($folder->country_id !== null) {
            return self::clean(['type' => 'country', 'country_id' => $folder->country_id]);
        }

        // Representatives upload into regional and committee folders they
        // can open (FolderPolicy::upload, unchanged since September).
        if ($ability === 'upload') {
            return self::clean(['type' => 'roles', 'roles' => [Role::MemberStateRep->value]]);
        }

        // A committee folder is the committee's when it is confidential;
        // below that every signed-in member reads it.
        if ($folder->committee_id !== null && $folder->clearance->rank() >= Clearance::Confidential->rank()) {
            return self::clean(['type' => 'committee', 'committee_id' => $folder->committee_id]);
        }

        return self::clean(['type' => 'signed_in']);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array{type: string, roles: list<string>, country_id: ?int, committee_id: ?int, user_ids: list<int>}
     */
    public static function clean(array $rule): array
    {
        $type = in_array($rule['type'] ?? null, self::TYPES, true) ? $rule['type'] : 'secretariat';

        return [
            'type' => $type,
            'roles' => $type === 'roles' ? array_values(array_intersect(array_map('strval', (array) ($rule['roles'] ?? [])), array_map(fn (Role $r) => $r->value, self::roles()))) : [],
            'country_id' => $type === 'country' && ! empty($rule['country_id']) ? (int) $rule['country_id'] : null,
            'committee_id' => $type === 'committee' && ! empty($rule['committee_id']) ? (int) $rule['committee_id'] : null,
            'user_ids' => $type === 'users' ? array_values(array_unique(array_map('intval', array_filter((array) ($rule['user_ids'] ?? []))))) : [],
        ];
    }

    /** Whether a person is in the audience a rule names. */
    public static function matches(User $user, array $rule): bool
    {
        $rule = self::clean($rule);

        return match ($rule['type']) {
            'signed_in' => true,
            'roles' => in_array($user->role->value, $rule['roles'], true),
            'country' => $rule['country_id'] !== null && $user->country_id === $rule['country_id'],
            'committee' => $rule['committee_id'] !== null && $user->committees()->whereKey($rule['committee_id'])->exists(),
            'users' => in_array($user->getKey(), $rule['user_ids'], true),
            default => false,
        };
    }

    /** The Secretariat: reads, uploads and validates everywhere its clearance reaches. */
    public static function isSecretariat(User $user): bool
    {
        return $user->seesAllCountries() && $user->hasPermission(Permission::ManageContent);
    }

    /* Saying it in words ------------------------------------------------- */

    /** The audience of one rule, in plain words ("BOCRA representatives"). */
    public static function audience(array $rule): string
    {
        $rule = self::clean($rule);

        return match ($rule['type']) {
            'signed_in' => __('d3_docs.who_signed_in'),
            'roles' => __('d3_docs.who_roles', ['roles' => implode(', ', array_map(fn (string $r) => Role::from($r)->label(), $rule['roles'])) ?: '-']),
            'country' => self::countryAudience($rule['country_id']),
            'committee' => __('d3_docs.who_committee', ['committee' => Committee::find($rule['committee_id'])?->name ?? '-']),
            'users' => __('d3_docs.who_users', ['names' => User::whereKey($rule['user_ids'])->orderBy('name')->pluck('name')->implode(', ') ?: '-']),
            default => __('d3_docs.who_secretariat_only'),
        };
    }

    private static function countryAudience(?int $countryId): string
    {
        $country = $countryId ? Country::with('authorities')->find($countryId) : null;

        return __('d3_docs.who_country', [
            'authority' => $country?->authorities->first()?->acronym ?? $country?->name ?? '-',
        ]);
    }

    /**
     * The folder page's "Who can see this folder" lines, in the words the
     * page has always used: "BOCRA representatives, upload and download".
     *
     * @return list<string>
     */
    public static function summary(Folder $folder): array
    {
        $view = self::ruleFor($folder, 'view');
        $upload = self::ruleFor($folder, 'upload');
        $validate = self::ruleFor($folder, 'validate');
        $lines = [];

        // A Secretariat folder is the Secretariat's whatever the rule says:
        // the clearance closes it to everybody else.
        if ($folder->clearance === Clearance::Secretariat) {
            return [__('extranet.access_secretariat')];
        }

        if ($view['type'] !== 'secretariat') {
            $lines[] = $view == $upload
                ? __('d3_docs.line_upload_download', ['who' => self::audience($view)])
                : __('d3_docs.line_download', ['who' => self::audience($view)]);
        }

        if ($upload['type'] !== 'secretariat' && $view != $upload) {
            $lines[] = __('d3_docs.line_upload', ['who' => self::audience($upload)]);
        }

        if ($validate['type'] !== 'secretariat') {
            $lines[] = __('d3_docs.line_validate', ['who' => self::audience($validate)]);
        }

        $lines[] = __('extranet.access_secretariat');

        return $lines;
    }

    /* Writing it down ------------------------------------------------------ */

    /**
     * Writes the folder's member state and committee from the rule that
     * applies to it, for it, every folder below it and every file in them,
     * so the country scope and the document policy agree with the rule.
     */
    public static function sync(Folder $folder): void
    {
        DB::transaction(function () use ($folder): void {
            self::syncOne($folder, 0);
        });
    }

    private static function syncOne(Folder $folder, int $depth): void
    {
        if ($depth > 5) {
            return;
        }

        $own = is_array($folder->access) && $folder->access !== [];
        $parent = $folder->parent_id ? Folder::unscoped()->find($folder->parent_id) : null;

        if ($own) {
            $view = self::clean($folder->access['view'] ?? []);
            $country = $view['type'] === 'country' ? $view['country_id'] : null;
            $committee = $view['type'] === 'committee' ? $view['committee_id'] : null;
        } elseif ($parent !== null) {
            $country = $parent->country_id;
            $committee = $parent->committee_id;
        } else {
            $country = $folder->country_id;
            $committee = $folder->committee_id;
        }

        Folder::unscoped()->whereKey($folder->getKey())->toBase()
            ->update(['country_id' => $country, 'committee_id' => $committee]);
        $folder->forceFill(['country_id' => $country, 'committee_id' => $committee])->syncOriginal();

        // The files follow the folder: owner, committee, and never a
        // clearance below the folder's.
        $documents = Document::withoutGlobalScopes()->withTrashed()->where('folder_id', $folder->getKey());
        (clone $documents)->toBase()->update(['country_id' => $country, 'committee_id' => $committee]);

        foreach ((clone $documents)->get(['id', 'clearance']) as $document) {
            if ($document->clearance->rank() < $folder->clearance->rank()) {
                Document::withoutGlobalScopes()->withTrashed()->whereKey($document->id)->toBase()
                    ->update(['clearance' => $folder->clearance->value]);
            }
        }

        foreach (Folder::unscoped()->where('parent_id', $folder->getKey())->get() as $child) {
            self::syncOne($child, $depth + 1);
        }
    }
}
