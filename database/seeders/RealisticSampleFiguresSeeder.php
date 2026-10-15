<?php

namespace Database\Seeders;

use App\Models\IndicatorValue;
use Illuminate\Database\Seeder;

/**
 * Bring the sample figures into plausible regional ranges (15 October 2026).
 *
 * Only figures flagged is_sample and not filed through a return
 * (submission_id null): a figure that came through the return workflow,
 * sample or not, keeps the value that was filed. Safe to run twice.
 *
 *   php artisan db:seed --class=RealisticSampleFiguresSeeder --force
 */
class RealisticSampleFiguresSeeder extends Seeder
{
    public function run(): void
    {
        $changed = 0;

        foreach (IndicatorValue::where('is_sample', true)->whereNull('submission_id')->with(['indicator', 'country'])->get() as $value) {
            $figure = IndicatorValueSeeder::figureFor((string) $value->indicator?->slug, (string) $value->country?->iso_code, (string) $value->period);
            if ($figure !== null && (float) $value->value_numeric !== $figure) {
                $value->forceFill(['value_numeric' => $figure])->save();
                $changed++;
            }
        }

        $this->command?->info("Sample figures brought into range: {$changed}");
    }
}
