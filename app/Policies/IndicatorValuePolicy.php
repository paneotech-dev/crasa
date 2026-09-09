<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\IndicatorValue;
use App\Models\User;

/**
 * A reported figure. This is the one resource in the registry that is
 * genuinely country-scoped: a member state representative reports their own
 * country's figures and nobody else's.
 *
 * The global CountryScope already keeps another country's rows out of every
 * query. This is the second half of the same rule, on the write side, so a
 * crafted request cannot do what a query could not read.
 */
class IndicatorValuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewPublishedIndicators);
    }

    public function view(User $user, IndicatorValue $value): bool
    {
        return $user->hasPermission(Permission::ViewPublishedIndicators)
            && $user->mayActOnCountry($value->country_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SubmitCountryReturn)
            || $user->hasPermission(Permission::ValidateSubmission);
    }

    public function update(User $user, IndicatorValue $value): bool
    {
        return $this->create($user) && $user->mayActOnCountry($value->country_id);
    }

    public function delete(User $user, IndicatorValue $value): bool
    {
        return $user->hasPermission(Permission::ValidateSubmission)
            && $user->mayActOnCountry($value->country_id);
    }
}
