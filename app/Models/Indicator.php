<?php

namespace App\Models;

use App\Models\Concerns\HasLocalisedNames;
use Database\Factories\IndicatorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicator extends Model
{
    /** @use HasFactory<IndicatorFactory> */
    use HasFactory, HasLocalisedNames;

    /** French and Portuguese names: lang/{fr,pt}/names.php. */
    protected function localisedNames(): array
    {
        return ['group' => 'indicators', 'key' => 'slug', 'fields' => ['name', 'definition']];
    }

    /** @var list<string> */
    public const CATEGORIES = [
        'licensing', 'spectrum', 'interconnection', 'market', 'quality', 'universal_access',
    ];

    protected $fillable = [
        'category', 'slug', 'name', 'definition', 'unit', 'data_type',
        'periodicity', 'sector_id', 'source_standard', 'source_url',
        'display_order', 'is_active', 'may_exceed_hundred',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'may_exceed_hundred' => 'boolean'];
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

    /**
     * Whether figures above 100 are normal for this indicator, so the
     * reading note "values above 100 are normal" goes with it wherever it
     * is shown: comparator, comparison table, home comparator block.
     */
    public function canExceedHundred(): bool
    {
        return (bool) $this->may_exceed_hundred;
    }

    public function categoryLabel(): string
    {
        return __('indicators.category_'.$this->category);
    }

    /**
     * The unit as a reader sees it.
     *
     * unit is stored as a code because it is matched on import and compared
     * across member states, and "subscriptions_per_100" printed under a
     * figure in a ministerial brief is a database showing through. Anything
     * without a translation falls back to its own words rather than to a
     * slug, so a unit the Secretariat adds later still reads as English.
     */
    public function unitLabel(): ?string
    {
        if (! filled($this->unit)) {
            return null;
        }

        $key = 'indicators.unit_'.$this->unit;
        $label = __($key);

        return $label === $key ? str_replace('_', ' ', $this->unit) : $label;
    }
}
