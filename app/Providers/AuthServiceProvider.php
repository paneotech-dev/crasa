<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Sector;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\AuthorityPolicy;
use App\Policies\CommitteePolicy;
use App\Policies\CountryPolicy;
use App\Policies\SectorPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private array $policies = [
        AuditLog::class => AuditLogPolicy::class,
        Authority::class => AuthorityPolicy::class,
        Committee::class => CommitteePolicy::class,
        Country::class => CountryPolicy::class,
        Sector::class => SectorPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Every permission on screen 13's matrix is a gate, so a route or a
        // Blade view asks the same question the policies ask.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        /**
         * The country gate. Answers whether this account may act on a row
         * belonging to a given member state. Null means a regional row.
         */
        Gate::define('act-on-country', fn (User $user, ?int $countryId) => $user->mayActOnCountry($countryId));

        /**
         * The named bypass, as an ability rather than a role check, so a
         * caller asks "may I read across all member states" rather than
         * "is this an administrator".
         */
        Gate::define('read-across-all-countries', fn (User $user) => $user->seesAllCountries());

        /**
         * No Gate::before superuser shortcut. An administrator's power comes
         * from the matrix, which lets the two-factor withholding in
         * User::hasPermission() actually bite. A before() returning true for
         * administrators would silently defeat it.
         */
    }
}
