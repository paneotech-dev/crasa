<?php

namespace Database\Factories;

use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sector>
 */
class SectorFactory extends Factory
{
    protected $model = Sector::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->randomElement(Sector::SLUGS);

        return [
            'slug' => $slug,
            'name' => ucfirst($slug),
            'icon' => 'circle',
            'accent_token' => '--c-navy',
        ];
    }
}
