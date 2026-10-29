<?php

namespace App\Support;

use App\Models\Country;
use App\Models\IndicatorValue;

/**
 * The figures beside the statement, screen 02 line 386.
 *
 * The screen draws a four-column grid of value-and-label cells with a
 * placeholder count of twelve. What goes in them is ours to decide, and the
 * only honest answer on a platform whose claim is that its register is
 * shared is the register itself: one cell per member state, showing how
 * many indicators that state has reported.
 *
 * Fourteen cells in a four-column grid, which is the membership and not a
 * round number chosen to fill a layout. Every figure is a query.
 */
final class StatementGrid
{
    /** @return array<int, array{value: string, label: string}> */
    public static function build(): array
    {
        return PublicCache::remember('statement-grid', fn () => self::compute());
    }

    /** @return array<int, array{value: string, label: string}> */
    private static function compute(): array
    {
        $counts = IndicatorValue::query()
            ->selectRaw('country_id, count(*) as filed')
            ->whereHas('indicator', fn ($q) => $q->where('is_active', true))
            ->groupBy('country_id')
            ->pluck('filed', 'country_id');

        return Country::orderBy('name')->get()
            ->map(fn (Country $country) => [
                // A state that has reported nothing shows a zero. Leaving
                // the cell blank would read as a rendering fault, and a
                // gap in the register is the thing this grid exists to
                // make visible.
                'value' => (string) ($counts[$country->id] ?? 0),
                'label' => $country->name,
            ])
            ->all();
    }
}
