<?php

namespace App\Models;

use App\Enums\Clearance;
use App\Enums\CommitteeRole;
use App\Enums\Grant;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\Theme;
use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\MarksSampleData;
use App\Support\PermissionMatrix;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use RuntimeException;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, MarksSampleData, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'country_id', 'authority_id', 'role',
        'status', 'theme', 'dismissed_hints', 'preferred_locale', 'notification_preferences', 'last_seen_at',
        'is_sample',
    ];

    /**
     * two_factor_secret is hidden as well as encrypted. It should never
     * reach a response, a log line or a queue payload.
     */
    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    /**
     * The column default alone is not enough: it is applied by the database
     * and the model in memory would carry null until it is reloaded, so a
     * freshly created user would render with no theme at all.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'theme' => 'system',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'status' => UserStatus::class,
            'is_sample' => 'boolean',
            'theme' => Theme::class,
            'dismissed_hints' => 'array',
            // AES-256-CBC with the application key. Contractual: sensitive
            // fields are encrypted at rest, and this is structural rather
            // than a call someone has to remember to make.
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            // Encrypted, like the secret: a hash list is still a secret.
            'two_factor_recovery_codes' => 'encrypted',
            'last_login_at' => 'datetime',
            'notification_preferences' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Ruling 5. An account's authority must belong to its country. A
     * representative pointed at another member state's regulator would pass
     * every permission check and still be wrong, so the pairing is refused
     * at the model rather than left to whoever writes the admin form.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $user->assertAuthorityMatchesCountry();
        });
    }

    public function assertAuthorityMatchesCountry(): void
    {
        if ($this->authority_id === null) {
            return;
        }

        $authorityCountryId = Authority::withoutGlobalScopes()
            ->whereKey($this->authority_id)
            ->value('country_id');

        if ($authorityCountryId === null) {
            return;
        }

        if ($this->country_id === null) {
            throw new RuntimeException(
                'This account has an authority but no country. An authority belongs to a member state, '
                .'so set the country to the authority\'s country or clear the authority.'
            );
        }

        if ((int) $authorityCountryId !== (int) $this->country_id) {
            throw new RuntimeException(
                'This account\'s authority belongs to a different member state than the account. '
                .'Set the country to the authority\'s country, or choose an authority in this country.'
            );
        }
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** The committees this person sits on, and in what seat. */
    public function committees(): BelongsToMany
    {
        return $this->belongsToMany(Committee::class)
            ->withPivot(['role', 'is_sample'])
            ->withTimestamps();
    }

    /** Whether this account holds a seat on a committee. */
    public function sitsOn(Committee $committee): bool
    {
        return $this->committees()->whereKey($committee->getKey())->exists();
    }

    public function authority(): BelongsTo
    {
        return $this->belongsTo(Authority::class);
    }

    /* Access ------------------------------------------------------------ */

    public function seesAllCountries(): bool
    {
        return $this->role->seesAllCountries();
    }

    public function isAdministrator(): bool
    {
        return $this->role === Role::Administrator;
    }

    /** Secretariat accounts carry no country. Screen 13 shows this as a category. */
    public function isSecretariat(): bool
    {
        return $this->country_id === null;
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * 2FA is mandatory for administrators. An administrator who has not
     * enrolled is not yet a working administrator.
     */
    public function mustEnrolInTwoFactor(): bool
    {
        return $this->role->requiresTwoFactor() && ! $this->hasConfirmedTwoFactor();
    }

    public function wantsNotification(string $key): bool
    {
        return in_array($key, $this->notification_preferences ?? [], true);
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function mayUsePlatform(): bool
    {
        return $this->status->mayUsePlatform();
    }

    /** The seat this person holds on a committee, if any. */
    public function committeeRoleFor(int $committeeId): ?CommitteeRole
    {
        $seat = $this->committees->firstWhere('id', $committeeId)
            ?? $this->committees()->whereKey($committeeId)->first();

        return $seat === null ? null : CommitteeRole::from($seat->pivot->role);
    }

    /**
     * Whether this account may open a document at a given clearance.
     *
     * Ruling 2: Confidential is answered by the seat the person holds on the
     * committee that owns the document, not by a per-user grant.
     */
    public function mayOpenClearance(Clearance $clearance, ?int $owningCommitteeId = null): bool
    {
        if ($clearance === Clearance::Public) {
            return true;
        }

        if (! $this->mayUsePlatform()) {
            return false;
        }

        $permission = $clearance->permission();

        if ($permission !== null && $this->hasPermission($permission)) {
            return true;
        }

        if ($clearance === Clearance::Confidential && $owningCommitteeId !== null) {
            return $this->committeeRoleFor($owningCommitteeId)?->opensConfidentialDocuments() ?? false;
        }

        return false;
    }

    public function grantFor(Permission $permission): Grant
    {
        return PermissionMatrix::grant($this->role, $permission);
    }

    /**
     * Whether this account holds a permission at all, before asking which
     * rows it holds it over. A conditional grant answers true here and is
     * narrowed to the user's own country by the policy and the global scope.
     *
     * An administrator who has not confirmed two-factor enrolment loses the
     * privileged abilities, per screen 13: read access is kept, publishing,
     * validating a submission and changing a role are not.
     */
    public function hasPermission(Permission $permission): bool
    {
        // A suspended account holds nothing. Ruling 3: it authenticates, and
        // then reaches only the page telling the holder who to contact.
        if (! $this->mayUsePlatform()) {
            return false;
        }

        if ($this->mustEnrolInTwoFactor()
            && in_array($permission, Permission::withheldUntilTwoFactorConfirmed(), true)) {
            return false;
        }

        return $this->grantFor($permission)->allowsAnything();
    }

    /** Whether this account has already dismissed a piece of guidance. */
    public function hasDismissed(string $hint): bool
    {
        return in_array($hint, $this->dismissed_hints ?? [], true);
    }

    public function dismiss(string $hint): void
    {
        $dismissed = $this->dismissed_hints ?? [];

        if (! in_array($hint, $dismissed, true)) {
            $dismissed[] = $hint;
            $this->update(['dismissed_hints' => $dismissed]);
        }
    }

    /**
     * Whether any of these permissions is held. Used where a surface exists
     * because there is something to do on it, rather than because of one
     * specific ability.
     *
     * @param  list<Permission>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** Whether the permission is held only over the user's own country. */
    public function permissionIsConditional(Permission $permission): bool
    {
        return $this->grantFor($permission) === Grant::Conditional;
    }

    /**
     * Whether this account may act on a row belonging to a given country.
     * Null means the row is regional and belongs to no single member state.
     */
    public function mayActOnCountry(?int $countryId): bool
    {
        if ($this->seesAllCountries()) {
            return true;
        }

        return $countryId !== null && $countryId === $this->country_id;
    }
}
