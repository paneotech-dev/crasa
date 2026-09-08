<?php

namespace Database\Factories;

use App\Models\Authority;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Authority>
 *
 * Factory-made authorities are demonstration data by definition, so
 * is_sample is true and stays true unless a test says otherwise.
 */
class AuthorityFactory extends Factory
{
    protected $model = Authority::class;

    public function definition(): array
    {
        $acronym = strtoupper($this->faker->unique()->lexify('????'));

        return [
            'country_id' => Country::factory(),
            'name' => $acronym.' Communications Regulatory Authority',
            'acronym' => $acronym,
            'website' => null,
            'established_year' => $this->faker->numberBetween(1994, 2015),
            'logo_path' => null,
            'is_sample' => true,
        ];
    }

    /** Only for a row a test needs to treat as confirmed by the Secretariat. */
    public function verified(): static
    {
        return $this->state(fn () => ['is_sample' => false]);
    }
}
