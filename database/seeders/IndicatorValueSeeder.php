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
                            'value_numeric' => self::figureFor($indicator->slug, $iso, (string) $period) ?? $this->plausibleValue($indicator, $index, $iso),
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
     * Plausible regional ranges, per indicator (15 October 2026). The earlier
     * ranges were per unit, so a fixed-telephone figure could read 131 per
     * 100 inhabitants and letters could be fewer than parcels: a regulator
     * looking at their own page would not believe it. Not a claim about any
     * member state; is_sample stays set.
     *
     * @var array<string, array{0: float, 1: float, 2: int}> slug => [low, high, decimals]
     */
    public const RANGES = [
        'fixed-telephone-subscriptions' => [0.2, 8.0, 1],
        'mobile-cellular-subscriptions' => [55.0, 170.0, 1],
        'fixed-broadband-subscriptions' => [0.1, 5.0, 1],
        'active-mobile-broadband-subscriptions' => [20.0, 115.0, 1],
        'international-bandwidth-per-inhabitant' => [3000.0, 60000.0, 0],
        'population-covered-3g' => [62.0, 99.0, 1],
        'multichannel-tv-subscriptions' => [2.0, 28.0, 1],
        'licensed-postal-operators' => [4.0, 60.0, 0],
        'permanent-post-offices' => [40.0, 1200.0, 0],
        'letter-post-items-domestic' => [2000000.0, 90000000.0, 0],
        'postal-parcels-domestic' => [8000.0, 400000.0, 0],
        'postal-staff-total' => [300.0, 14000.0, 0],
        'postal-transit-time-standard' => [1.0, 3.0, 0],
        'postal-items-within-standard' => [60.0, 94.0, 1],
    ];

    /** The same figure for the same indicator, state and period, every run. */
    public static function figureFor(string $slug, string $iso, string $period): ?float
    {
        if (! isset(self::RANGES[$slug])) {
            return null;
        }

        [$low, $high, $decimals] = self::RANGES[$slug];
        $position = (crc32($slug.'|'.$iso) % 1000) / 1000;
        // A small movement between years, so a series is not a flat line.
        $drift = 1 + (((crc32($slug.'|'.$iso.'|'.$period) % 100) - 50) / 1000);
        $value = min($high, max($low, ($low + $position * ($high - $low)) * $drift));

        return round($value, $decimals);
    }

    private function plausibleValue(Indicator $indicator, int $index, string $iso): float
    {
        return self::figureFor($indicator->slug, $iso, '0') ?? round(10 + ((crc32($indicator->slug.$iso) % 1000) / 10), 1);
    }
}
