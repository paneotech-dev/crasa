<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\MarksSampleData;
use Database\Factories\IndicatorValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorValue extends Model
{
    /** @use HasFactory<IndicatorValueFactory> */
    use Auditable, HasFactory, MarksSampleData;

    protected $fillable = [
        'indicator_id', 'country_id', 'period', 'value_numeric', 'value_text',
        'currency', 'source_note', 'submission_id', 'confidence', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'value_numeric' => 'decimal:4',
            'is_sample' => 'boolean',
        ];
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function hasValue(): bool
    {
        return $this->value_numeric !== null || filled($this->value_text);
    }

    /**
     * How stale this figure is, for the freshness badge.
     *
     * Reported against a period rather than a timestamp, because a 2023
     * figure entered yesterday is still a 2023 figure.
     */
    public function ageInYears(): ?int
    {
        if (! preg_match('/^(\d{4})/', $this->period, $match)) {
            return null;
        }

        return max(0, (int) now()->year - (int) $match[1]);
    }

    public function freshness(): string
    {
        $age = $this->ageInYears();

        return match (true) {
            $age === null => 'unknown',
            $age <= 1 => 'current',
            $age <= 3 => 'ageing',
            default => 'stale',
        };
    }
}
