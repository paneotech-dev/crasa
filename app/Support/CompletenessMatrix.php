<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Indicator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BUILD_SPEC 6.1. Fourteen member states against the indicator categories.
 *
 * Pure SQL over the schema. No external service, no inference: a cell is
 * complete because a value exists for it, and empty because one does not.
 *
 * Two queries regardless of how many states or indicators there are: one for
 * the counts per country and category, one for the freshness. A matrix that
 * queried per cell would fire 14 x 6 = 84 times.
 */
final class CompletenessMatrix
{
    /**
     * @return array{
     *   countries: Collection,
     *   categories: array<string, int>,
     *   cells: array<int, array<string, array{reported: int, expected: int, percent: int}>>,
     *   totals: array<int, array{reported: int, expected: int, percent: int}>,
     *   freshness: array<int, string|null>,
     *   overall: int,
     *   period: string|null
     * }
     */
    public static function build(?string $period = null): array
    {
        $countries = Country::orderBy('name')->get();

        // How many indicators each category expects.
        $expected = Indicator::active()
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->all();

        // One query for every cell in the grid.
        $reported = DB::table('indicator_values')
            ->join('indicators', 'indicators.id', '=', 'indicator_values.indicator_id')
            ->where('indicators.is_active', true)
            ->when($period, fn ($q) => $q->where('indicator_values.period', $period))
            ->selectRaw('indicator_values.country_id, indicators.category, COUNT(DISTINCT indicator_values.indicator_id) as reported')
            ->groupBy('indicator_values.country_id', 'indicators.category')
            ->get();

        // The most recent period each state has reported anything for, which
        // is what the freshness badge reads.
        $latest = DB::table('indicator_values')
            ->selectRaw('country_id, MAX(period) as latest')
            ->groupBy('country_id')
            ->pluck('latest', 'country_id')
            ->all();

        $cells = [];
        $totals = [];
        $freshness = [];

        $expectedTotal = array_sum($expected);

        foreach ($countries as $country) {
            $countryReported = 0;

            foreach (array_keys($expected) as $category) {
                $count = (int) ($reported
                    ->firstWhere(fn ($row) => $row->country_id === $country->id && $row->category === $category)
                    ?->reported ?? 0);

                $cells[$country->id][$category] = [
                    'reported' => $count,
                    'expected' => $expected[$category],
                    'percent' => $expected[$category] > 0 ? (int) round($count / $expected[$category] * 100) : 0,
                ];

                $countryReported += $count;
            }

            $totals[$country->id] = [
                'reported' => $countryReported,
                'expected' => $expectedTotal,
                'percent' => $expectedTotal > 0 ? (int) round($countryReported / $expectedTotal * 100) : 0,
            ];

            $freshness[$country->id] = $latest[$country->id] ?? null;
        }

        return [
            'countries' => $countries,
            'categories' => $expected,
            'cells' => $cells,
            'totals' => $totals,
            'freshness' => $freshness,
            'overall' => $expectedTotal > 0 && $countries->isNotEmpty()
                ? (int) round(collect($totals)->avg('percent'))
                : 0,
            'period' => $period,
        ];
    }

    /** How stale a period is, for the badge. */
    public static function freshnessOf(?string $period): string
    {
        if ($period === null || ! preg_match('/^(\d{4})/', $period, $match)) {
            return 'none';
        }

        $age = (int) now()->year - (int) $match[1];

        return match (true) {
            $age <= 1 => 'current',
            $age <= 3 => 'ageing',
            default => 'stale',
        };
    }
}
