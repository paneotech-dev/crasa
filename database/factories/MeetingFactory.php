<?php

namespace Database\Factories;

use App\Models\Committee;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Meeting>
 *
 * Factory-made meetings are demonstration data by definition, so is_sample
 * is true and stays true unless a test says otherwise.
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function definition(): array
    {
        $title = ucfirst($this->faker->words(4, true));
        $starts = $this->faker->dateTimeBetween('-2 months', '+6 months');

        return [
            'committee_id' => Committee::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'kind' => 'committee',
            'starts_on' => $starts,
            'ends_on' => $starts,
            'location' => $this->faker->city().', Botswana',
            'clearance' => 'member',
            'is_sample' => true,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->addDays($this->faker->numberBetween(2, 60)),
        ]);
    }
}
