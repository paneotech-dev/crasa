<?php

namespace Database\Factories;

use App\Enums\NewsCategory;
use App\Models\NewsItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NewsItem> */
class NewsItemFactory extends Factory
{
    protected $model = NewsItem::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->unique()->words(5, true)),
            'category' => NewsCategory::News,
            'sector_id' => null,
            'published_at' => now()->subDays($this->faker->numberBetween(1, 300)),
            'hero_image_path' => null,
            'author_name' => 'CRASA Secretariat',
            'status' => 'published',
            'is_sample' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft', 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()->addWeek()]);
    }
}
