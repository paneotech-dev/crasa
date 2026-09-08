<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IndicatorValue> */
class IndicatorValueFactory extends Factory
{
    protected $model = IndicatorValue::class;

    public function definition(): array
    {
        return [
            'indicator_id' => Indicator::factory(),
            'country_id' => Country::factory(),
            'period' => (string) now()->year,
            'value_numeric' => $this->faker->randomFloat(2, 1, 200),
            'source_note' => 'Test fixture.',
            'confidence' => 'reported',
            'is_sample' => true,
        ];
    }
}
