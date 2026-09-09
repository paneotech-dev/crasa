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
            /*
             * unique() is unique to this factory instance, and the fourteen
             * real member states are already in the table when a test seeds
             * them. Two letters is 676 codes, so a collision with a seeded
             * one is a one-in-fifty flake that only shows up in CI. The
             * taken codes are excluded explicitly.
             */
            'iso_code' => $this->unusedIsoCode(),
            'name' => $this->faker->unique()->country(),
            'official_languages' => $this->faker->randomElement(['en', 'fr', 'pt', 'en,fr']),
            'joined_at' => null,
            'flag_path' => null,
            'hero_image_path' => null,
        ];
    }

    private function unusedIsoCode(): string
    {
        $taken = Country::query()->pluck('iso_code')->map('strtolower')->all();

        do {
            $code = $this->faker->lexify('??');
        } while (in_array($code, $taken, true));

        return $code;
    }
}
