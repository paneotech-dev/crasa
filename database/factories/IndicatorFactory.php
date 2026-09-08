<?php

namespace Database\Factories;

use App\Models\Indicator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Indicator> */
class IndicatorFactory extends Factory
{
    protected $model = Indicator::class;

    public function definition(): array
    {
        return [
            'category' => 'market',
            'slug' => Str::slug($this->faker->unique()->words(3, true)),
            'name' => ucfirst($this->faker->words(4, true)),
            'definition' => $this->faker->sentence(20),
            'unit' => 'subscriptions_per_100',
            'data_type' => 'numeric',
            'periodicity' => 'annual',
            'source_standard' => 'Test fixture',
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}
