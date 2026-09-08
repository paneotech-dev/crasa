<?php

namespace Database\Factories;

use App\Enums\InstrumentStatus;
use App\Enums\InstrumentType;
use App\Models\Country;
use App\Models\Instrument;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Instrument>
 */
class InstrumentFactory extends Factory
{
    protected $model = Instrument::class;

    public function definition(): array
    {
        $slug = Str::slug($this->faker->unique()->words(4, true));

        return [
            'country_id' => Country::query()->inRandomOrder()->value('id') ?? Country::factory(),
            // Reuse a seeded sector where one exists: the slug is unique
            // and there are only ever three.
            'sector_id' => Sector::query()->inRandomOrder()->value('id') ?? Sector::factory(),
            'instrument_type' => $this->faker->randomElement(InstrumentType::cases()),
            'reference_number' => null,
            'enacted_on' => null,
            'effective_from' => null,
            'status' => InstrumentStatus::Draft,
            'slug' => $slug,
            'is_sample' => true,
        ];
    }

    public function inForce(): static
    {
        return $this->state(fn () => ['status' => InstrumentStatus::InForce]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => InstrumentStatus::Draft]);
    }

    public function repealed(): static
    {
        return $this->state(fn () => ['status' => InstrumentStatus::Repealed]);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['is_sample' => false]);
    }
}
