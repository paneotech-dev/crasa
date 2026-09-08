<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 *
 * For tests only. The fourteen real member states come from CountrySeeder;
 * this factory invents states so a test can work with a country that is not
 * one of them.
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'iso_code' => $this->faker->unique()->lexify('??'),
            'name' => $this->faker->unique()->country(),
            'official_languages' => $this->faker->randomElement(['en', 'fr', 'pt', 'en,fr']),
            'joined_at' => null,
            'flag_path' => null,
            'hero_image_path' => null,
        ];
    }
}
