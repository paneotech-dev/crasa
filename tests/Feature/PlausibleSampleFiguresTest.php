<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\IndicatorValue;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\RealisticSampleFiguresSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sample figures a regulator would believe (15 October 2026): 131 fixed
 * telephone subscriptions per 100 inhabitants was on Tanzania's page.
 */
class PlausibleSampleFiguresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(IndicatorSeeder::class);
        $this->seed(IndicatorValueSeeder::class);
    }

    public function test_every_sample_figure_is_within_its_indicators_range(): void
    {
        $values = IndicatorValue::where('is_sample', true)->with('indicator')->get();
        $this->assertNotEmpty($values);

        foreach ($values as $value) {
            [$low, $high] = IndicatorValueSeeder::RANGES[$value->indicator->slug] ?? [null, null];
            if ($low === null) {
                continue;
            }
            $this->assertGreaterThanOrEqual($low, (float) $value->value_numeric, $value->indicator->slug);
            $this->assertLessThanOrEqual($high, (float) $value->value_numeric, $value->indicator->slug);
        }
    }

    public function test_letters_outnumber_parcels_everywhere(): void
    {
        foreach (Country::pluck('iso_code') as $iso) {
            $this->assertGreaterThan(
                IndicatorValueSeeder::figureFor('postal-parcels-domestic', $iso, '2025'),
                IndicatorValueSeeder::figureFor('letter-post-items-domestic', $iso, '2025'),
            );
        }
    }

    public function test_the_live_repair_leaves_filed_figures_alone(): void
    {
        $filed = IndicatorValue::where('is_sample', true)->first();
        $filed->forceFill(['submission_id' => null, 'value_numeric' => 131])->save();
        $kept = IndicatorValue::where('is_sample', true)->whereKeyNot($filed->id)->first();
        $kept->forceFill(['value_numeric' => 999])->save();
        DB::table('indicator_values')->where('id', $kept->id)->update(['submission_id' => 1]);

        $this->seed(RealisticSampleFiguresSeeder::class);

        $this->assertNotEquals(131.0, (float) $filed->fresh()->value_numeric);
        $this->assertEquals(999.0, (float) $kept->fresh()->value_numeric);
    }
}
