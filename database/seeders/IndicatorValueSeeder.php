<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use Illuminate\Database\Seeder;

/**
 * Sparse demonstration values. EVERY ROW IS is_sample = true.
 *
 * Deliberately uneven: some member states report almost everything, some
 * report a category and nothing else, some report nothing at all, and some
 * figures are years old. That unevenness is the point. A dashboard whose
 * purpose is to show gaps proves nothing against a complete grid, and a
 * demonstration where every country is at 80 per cent teaches the
 * Secretariat nothing about what the tool will look like in use.
 *
 * The numbers themselves are not real. They are plausible in magnitude so
 * the columns read correctly, and every one carries a source_note saying it
 * is demonstration data, so a figure cannot be mistaken for a return.
 */
class IndicatorValueSeeder extends Seeder
{
    /**
     * How complete each member state is, by ISO code. The shape of the
     * demonstration rather than a claim about any regulator.
     *
     * @var array<string, array{fill: float, periods: list<string>}>
     */
    private const PROFILES = [
        'za' => ['fill' => 1.00, 'periods' => ['2025']],
        'bw' => ['fill' => 1.00, 'periods' => ['2025']],
        'mu' => ['fill' => 0.90, 'periods' => ['2025']],
        'tz' => ['fill' => 0.85, 'periods' => ['2025']],
        'na' => ['fill' => 0.75, 'periods' => ['2024']],
        'zm' => ['fill' => 0.70, 'periods' => ['2024']],
        'zw' => ['fill' => 0.60, 'periods' => ['2024']],
        'mw' => ['fill' => 0.50, 'periods' => ['2023']],
        'ls' => ['fill' => 0.40, 'periods' => ['2023']],
        'sz' => ['fill' => 0.35, 'periods' => ['2023']],
        'mz' => ['fill' => 0.30, 'periods' => ['2022']],
        'ao' => ['fill' => 0.20, 'periods' => ['2022']],
        'cd' => ['fill' => 0.10, 'periods' => ['2022']],
        // Reports nothing at all. A dashboard that never shows an empty row
        // is not demonstrating the thing it exists for.
        'km' => ['fill' => 0.00, 'periods' => []],
    ];

    // No source note: a sample figure carries no source rather than a disclaimer (15 October 2026).
    private const NOTE = null;

    public function run(): void
    {
        $indicators = Indicator::active()->orderBy('display_order')->get();
        $countries = Country::pluck('id', 'iso_code');

        if ($indicators->isEmpty()) {
            $this->command?->warn('No indicators seeded. Run IndicatorSeeder first.');

            return;
        }

        foreach (self::PROFILES as $iso => $profile) {
            if (! isset($countries[$iso]) || $profile['periods'] === []) {
                continue;
            }

            $take = (int) round($indicators->count() * $profile['fill']);

            foreach ($indicators->take($take) as $index => $indicator) {
                foreach ($profile['periods'] as $period) {
                    IndicatorValue::updateOrCreate(
                        [
                            'indicator_id' => $indicator->id,
                            'country_id' => $countries[$iso],
                            'period' => $period,
                        ],
                        [
                            'value_numeric' => $this->plausibleValue($indicator, $index, $iso),
                            'source_note' => self::NOTE,
                            'confidence' => $index % 5 === 0 ? 'estimated' : 'reported',
                            'is_sample' => true,
                        ]
                    );
                }
            }
        }
    }

    /**
     * A figure of the right order of magnitude for its unit, so a column of
     * them reads correctly. Not a claim about any member state.
     *
     * The seed mixes the indicator and the country, because varying by
     * indicator alone gave every member state the same figure and a
     * comparison of four identical numbers demonstrates nothing.
     */
    private function plausibleValue(Indicator $indicator, int $index, string $iso): float
    {
        $seed = $index * 31 + (ord($iso[0]) * 7) + ord($iso[1]);

        return match ($indicator->unit) {
            'percent' => round(45 + (($seed * 7) % 50), 1),
            'subscriptions_per_100' => round(3 + (($seed * 17) % 150), 1),
            'bits_per_second_per_inhabitant' => round(1200 + (($seed * 311) % 40000)),
            'offices' => 120 + (($seed * 37) % 900),
            'operators' => 3 + (($seed * 3) % 40),
            'items' => 250000 + (($seed * 91237) % 8000000),
            'staff' => 400 + (($seed * 53) % 9000),
            'days' => 1 + ($seed % 3),
            default => round(10 + (($seed * 13) % 200), 1),
        };
    }
}
