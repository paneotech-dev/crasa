<?php

namespace App\Models;

use Database\Factories\IndicatorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicator extends Model
{
    /** @use HasFactory<IndicatorFactory> */
    use HasFactory;

    /** @var list<string> */
    public const CATEGORIES = [
        'licensing', 'spectrum', 'interconnection', 'market', 'quality', 'universal_access',
    ];

    protected $fillable = [
        'category', 'slug', 'name', 'definition', 'unit', 'data_type',
        'periodicity', 'sector_id', 'source_standard', 'source_url',
        'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(IndicatorValue::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function categoryLabel(): string
    {
        return __('indicators.category_'.$this->category);
    }
}
