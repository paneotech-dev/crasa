<?php

namespace App\Models\Concerns;

use App\Models\Country;
use App\Models\Scopes\CountryScope;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applies CountryScope to a model. Every model carrying country_id that a
 * member state may not read across should use this.
 */
trait CountryScoped
{
    public static function bootCountryScoped(): void
    {
        static::addGlobalScope(new CountryScope);
    }

    public function countryScopeColumn(): string
    {
        return 'country_id';
    }

    /** Whether a null country means regional and is visible to everyone. */
    public function countryScopeIncludesShared(): bool
    {
        return false;
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * The named, deliberate bypass.
     *
     * Reading across every member state is a real operation: the comparator,
     * the completeness dashboard and Secretariat reporting all need it. It
     * is spelled out at the call site rather than happening silently, and it
     * refuses anyone not entitled to it instead of widening the query.
     *
     * @throws AuthorizationException
     */
    public function scopeAcrossAllCountries(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user instanceof User && ! $user->seesAllCountries()) {
            throw new AuthorizationException(
                'This account may only read its own member state. Reading across all '
                .'member states requires an administrator or editor.'
            );
        }

        return $query->withoutGlobalScope(CountryScope::class);
    }
}
