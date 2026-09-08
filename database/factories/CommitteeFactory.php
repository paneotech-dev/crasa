<?php

namespace Database\Factories;

use App\Models\Committee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Committee>
 */
class CommitteeFactory extends Factory
{
    protected $model = Committee::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'slug' => str($name)->slug()->value(),
            'name' => ucfirst($name),
            'sector_id' => null,
            'mandate' => $this->faker->sentence(12),
            'chair_authority_id' => null,
            'is_active' => true,
            'is_sample' => true,
        ];
    }

    public function crossSector(): static
    {
        return $this->state(fn () => ['sector_id' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['is_sample' => false]);
    }
}
