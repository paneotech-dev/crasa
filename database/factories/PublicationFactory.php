<?php

namespace Database\Factories;

use App\Enums\PublicationType;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Publication> */
class PublicationFactory extends Factory
{
    protected $model = Publication::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->unique()->words(5, true)),
            'publication_type' => PublicationType::Guideline,
            'sector_id' => null,
            'committee_id' => null,
            'country_id' => null,
            'published_on' => now()->subYears($this->faker->numberBetween(0, 8)),
            'is_public' => true,
            'is_sample' => true,
        ];
    }

    public function unlisted(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }
}
