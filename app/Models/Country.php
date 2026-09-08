<?php

namespace App\Models;

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
    use HasFactory;

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

    public function committees(): BelongsToMany
    {
        return $this->belongsToMany(Committee::class)
            ->withPivot(['role', 'is_sample'])
            ->withTimestamps();
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
