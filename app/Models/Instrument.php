<?php

namespace App\Models;

use App\Enums\InstrumentStatus;
use App\Enums\InstrumentType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use Database\Factories\InstrumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

/**
 * A regulatory instrument: an act, regulation, licence framework, guideline,
 * directive or policy.
 *
 * Not country-scoped. The regulatory library is public and the comparator
 * exists to read across all fourteen member states, so a representative sees
 * every country's instruments. What a representative may not do is edit
 * another country's, which the policy enforces.
 */
class Instrument extends Model
{
    /** @use HasFactory<InstrumentFactory> */
    use Auditable, HasFactory, HasTranslations, MarksSampleData, SoftDeletes;

    /** @var list<string> */
    protected array $translatable = ['title', 'summary', 'body'];

    protected $fillable = [
        'country_id', 'sector_id', 'instrument_type', 'reference_number',
        'enacted_on', 'effective_from', 'status', 'superseded_by_id',
        'current_version_id', 'slug', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'instrument_type' => InstrumentType::class,
            'status' => InstrumentStatus::class,
            'enacted_on' => 'date',
            'effective_from' => 'date',
            'is_sample' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The slug is the citation permalink. Once an instrument is published, a
     * ministerial brief may cite it, so the slug is frozen. Changing it would
     * break a citation that is already in circulation.
     */
    protected static function booted(): void
    {
        static::updating(function (Instrument $instrument): void {
            if (! $instrument->isDirty('slug')) {
                return;
            }

            // getOriginal() returns the value already cast to the enum, so
            // it is normalised rather than passed straight to from().
            $original = $instrument->getOriginal('status');
            $wasPublished = ($original instanceof InstrumentStatus
                ? $original
                : InstrumentStatus::from($original))->isPublished();

            if ($wasPublished) {
                throw new RuntimeException(
                    'This instrument is published, so its slug is permanent. The slug is the citation '
                    .'permalink and changing it breaks every reference already in circulation. '
                    .'Publish a superseding instrument instead.'
                );
            }
        });
    }

    /* Relationships -------------------------------------------------------- */

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(InstrumentVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(InstrumentVersion::class, 'current_version_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    public function supersedes(): HasMany
    {
        return $this->hasMany(self::class, 'superseded_by_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /* Scopes --------------------------------------------------------------- */

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InstrumentStatus::InForce->value,
            InstrumentStatus::Amended->value,
            InstrumentStatus::Repealed->value,
        ]);
    }

    public function scopeInForce(Builder $query): Builder
    {
        return $query->where('status', InstrumentStatus::InForce->value);
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_by_id !== null;
    }
}
