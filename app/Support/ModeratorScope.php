<?php

namespace App\Support;

use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item 1j: a moderator limited to member states and/or committees.
 *
 * The scope is a set of rows in moderator_scopes. No rows: the moderator
 * sees and decides every item, as before. With rows, an item is in scope
 * when its own country_id is one of the member states, or its own
 * committee_id one of the committees. An item with neither (news, pages,
 * social posts, and publications or events tied to nothing) is regional:
 * only unscoped moderators see it.
 */
final class ModeratorScope
{
    /** @return array{countries: list<int>, committees: list<int>} */
    public static function of(User $user): array
    {
        if (! self::ready()) {
            return ['countries' => [], 'committees' => []];
        }

        $rows = DB::table('moderator_scopes')->where('user_id', $user->id)->get(['country_id', 'committee_id']);

        return [
            'countries' => $rows->pluck('country_id')->filter()->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'committees' => $rows->pluck('committee_id')->filter()->map(fn ($id) => (int) $id)->sort()->values()->all(),
        ];
    }

    public static function isScoped(User $user): bool
    {
        return self::ready() && DB::table('moderator_scopes')->where('user_id', $user->id)->exists();
    }

    /**
     * Replace the scope and audit the change on the account.
     *
     * @param  list<int>  $countries
     * @param  list<int>  $committees
     */
    public static function set(User $user, array $countries, array $committees, User $by): void
    {
        $countries = collect($countries)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $committees = collect($committees)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $before = self::of($user);

        if ($before === ['countries' => $countries, 'committees' => $committees]) {
            return;
        }

        DB::transaction(function () use ($user, $countries, $committees, $before, $by) {
            DB::table('moderator_scopes')->where('user_id', $user->id)->delete();
            $now = now();
            $rows = array_merge(
                array_map(fn ($id) => ['user_id' => $user->id, 'country_id' => $id, 'committee_id' => null, 'created_at' => $now, 'updated_at' => $now], $countries),
                array_map(fn ($id) => ['user_id' => $user->id, 'country_id' => null, 'committee_id' => $id, 'created_at' => $now, 'updated_at' => $now], $committees),
            );

            if ($rows !== []) {
                DB::table('moderator_scopes')->insert($rows);
            }

            AuditLog::record(AuditLog::UPDATED, $user, [
                'moderation_scope' => ['from' => $before, 'to' => ['countries' => $countries, 'committees' => $committees]],
            ], $by);
        });
    }

    /** "Botswana, Spectrum committee", or null for an unscoped moderator. */
    public static function label(User $user): ?string
    {
        $scope = self::of($user);

        if ($scope['countries'] === [] && $scope['committees'] === []) {
            return null;
        }

        $names = Country::whereIn('id', $scope['countries'])->get()->map(fn (Country $c) => $c->displayName())->sort()->values()
            ->merge(Committee::withoutGlobalScopes()->whereIn('id', $scope['committees'])->get()->map(fn (Committee $c) => $c->displayName())->sort()->values());

        return $names->implode(', ');
    }

    /** Narrow a query on approvals to the items in this person's scope. */
    public static function constrain(Builder $query, User $user): Builder
    {
        $scope = self::of($user);

        if ($scope['countries'] === [] && $scope['committees'] === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($scope) {
            $any = false;

            foreach (Approvals::TYPES as $class) {
                $model = new $class;
                $match = self::matcher($model->getTable(), $scope);

                if ($match === null) {
                    continue;
                }

                $any = true;
                $q->orWhere(fn (Builder $w) => $w->where('approvable_type', $model->getMorphClass())
                    ->whereIn('approvable_id', fn ($sub) => $match($sub->select('id')->from($model->getTable()))));
            }

            if (! $any) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    /** Whether this approval's item lies in the person's scope. Always true for an unscoped moderator. */
    public static function covers(User $user, Approval $approval): bool
    {
        if (! self::isScoped($user)) {
            return true;
        }

        return self::constrain(Approval::query()->whereKey($approval->getKey()), $user)->exists();
    }

    /**
     * A where-clause builder for one item table, from the columns it has:
     * country_id, committee_id, or both. Null when the table has neither or
     * the scope names nothing it could match.
     */
    private static function matcher(string $table, array $scope): ?\Closure
    {
        $countries = $scope['countries'] !== [] && self::hasColumn($table, 'country_id') ? $scope['countries'] : [];
        $committees = $scope['committees'] !== [] && self::hasColumn($table, 'committee_id') ? $scope['committees'] : [];

        if ($countries === [] && $committees === []) {
            return null;
        }

        return fn ($q) => $q->where(function ($w) use ($countries, $committees) {
            if ($countries !== []) {
                $w->orWhereIn('country_id', $countries);
            }

            if ($committees !== []) {
                $w->orWhereIn('committee_id', $committees);
            }
        });
    }

    private static function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = spl_object_id(app()).':'.$table.'.'.$column;

        return $cache[$key] ??= Schema::hasColumn($table, $column);
    }

    private static function ready(): bool
    {
        static $ready = [];
        $key = spl_object_id(app());

        return $ready[$key] ??= Schema::hasTable('moderator_scopes');
    }
}
