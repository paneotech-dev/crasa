<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Holds a country-scoped user to their own member state.
 *
 * This is a global scope rather than a controller check because a controller
 * check has to be remembered. A member state representative who queries a
 * country-scoped model gets their own country's rows and no others, whether
 * the query is written in a controller, a job, a report or a console command.
 *
 * It fails closed. A user whose role is country-scoped but who has no
 * country_id sees nothing, because a misconfigured account should return no
 * rows rather than every row.
 *
 * An administrator or editor is not constrained. Any other bypass must go
 * through the named acrossAllCountries() scope, which checks authorisation
 * and throws rather than quietly widening the query.
 */
class CountryScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        // No authenticated user: console commands, queued jobs, seeders and
        // guest requests. Policies and route middleware govern those; the
        // scope has no user to scope to.
        if (! $user instanceof User) {
            return;
        }

        if ($user->seesAllCountries()) {
            return;
        }

        $column = $model->getTable().'.'.$model->countryScopeColumn();

        if ($user->country_id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where(function (Builder $query) use ($column, $user, $model): void {
            $query->where($column, $user->country_id);

            // Some tables use a null country to mean regional, visible to
            // everyone. A model opts into that; it is not the default.
            if ($model->countryScopeIncludesShared()) {
                $query->orWhereNull($column);
            }
        });
    }
}
