<?php

namespace Database\Factories;

use App\Enums\Clearance;
use App\Models\Document;
use App\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->slug(3).'.pdf';

        return [
            'documentable_type' => Instrument::class,
            'documentable_id' => Instrument::factory(),
            'file_path' => 'documents/'.$name,
            'original_filename' => $name,
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(80_000, 4_000_000),
            'locale' => 'en',
            'clearance' => Clearance::Member,
            'is_sample' => true,
        ];
    }

    public function clearance(Clearance $clearance): static
    {
        return $this->state(fn () => ['clearance' => $clearance]);
    }

    public function publicAccess(): static
    {
        return $this->state(fn () => ['clearance' => Clearance::Public]);
    }

    public function forCommittee(int $committeeId): static
    {
        return $this->state(fn () => ['committee_id' => $committeeId]);
    }
}
