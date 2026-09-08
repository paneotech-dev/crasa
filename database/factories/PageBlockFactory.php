<?php

namespace Database\Factories;

use App\Enums\BlockType;
use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageBlock> */
class PageBlockFactory extends Factory
{
    protected $model = PageBlock::class;

    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'block_type' => BlockType::RichText,
            'position' => 0,
            'is_visible' => true,
            'settings' => [],
            'is_sample' => true,
        ];
    }

    public function ofType(BlockType $type, array $settings = []): static
    {
        return $this->state(fn () => [
            'block_type' => $type,
            'settings' => $settings === [] ? $type->defaultSettings() : $settings,
        ]);
    }
}
