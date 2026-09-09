<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A CRASA member state. Fourteen rows, seeded, not user-editable.
 */
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'iso_code',
        'name',
        'official_languages',
        'joined_at',
        'flag_path',
        'hero_image_path',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'iso_code';
    }

    /** Every regulator this state has had, newest first. */
    public function authorities(): HasMany
    {
        return $this->hasMany(Authority::class)->orderByDesc('id');
    }

    /** The regulator in place now. A country may restructure its regulator. */
    public function authority(): HasOne
    {
        return $this->hasOne(Authority::class)->latestOfMany();
    }

    /**
     * Every figure reported against this member state. Used by the
     * dashboard tile that counts how many of the fourteen have filed
     * anything at all, which is the number that says whether the
     * comparator has a region in it or a demonstration.
     */
    public function indicatorValues(): HasMany
    {
        return $this->hasMany(IndicatorValue::class);
    }

    public function committees(): BelongsToMany
    {
        return $this->belongsToMany(Committee::class)
            ->withPivot(['role', 'is_sample'])
            ->withTimestamps();
    }

    /**
     * How much room this member state's card needs.
     *
     * Ruling 2: an authority sitting on three sector mandates with a
     * fifty-character name does not belong in the same column width as ICTA
     * with one. Returns 1 or 2 grid columns, decided from the content that
     * actually exists rather than from a formula applied to every card.
     */
    public function cardSpan(): int
    {
        $authority = $this->relationLoaded('authority') ? $this->authority : null;

        if ($authority === null) {
            return 1;
        }

        $nameLength = mb_strlen($authority->name);
        $sectorCount = $authority->relationLoaded('sectors') ? $authority->sectors->count() : 0;

        // A long name needs the width to avoid four-word wrapping; three
        // mandates need the width to keep the list on one or two lines.
        return ($nameLength >= 42 || $sectorCount >= 3) ? 2 : 1;
    }

    /**
     * The platform locales this member state is served in.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        return array_values(array_filter(array_map(
            trim(...),
            explode(',', $this->official_languages)
        )));
    }
}
