<?php

namespace App\Models;

use App\Enums\PublicationType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use Database\Factories\PublicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class Publication extends Model
{
    /** @use HasFactory<PublicationFactory> */
    use Auditable, HasFactory, HasTranslations, MarksSampleData, SoftDeletes;

    /** @var list<string> */
    protected array $translatable = ['title', 'summary'];

    protected $fillable = [
        'slug', 'publication_type', 'sector_id', 'committee_id', 'country_id',
        'published_on', 'supersedes_id', 'is_public', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'publication_type' => PublicationType::class,
            'published_on' => 'date',
            'is_public' => 'boolean',
            'is_sample' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** The slug is the citation permalink and is permanent once created. */
    protected static function booted(): void
    {
        static::updating(function (Publication $publication): void {
            if ($publication->isDirty('slug')) {
                throw new RuntimeException(
                    'A publication slug is permanent. It is the citation permalink, and changing it '
                    .'breaks every reference already in circulation.'
                );
            }
        });
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** What this publication replaced. */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** What replaced this publication. */
    public function supersededBy(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_id');
    }

    public function isSuperseded(): bool
    {
        return $this->supersededBy()->exists();
    }

    /**
     * The whole lineage, oldest first, including this one.
     *
     * A reader looking at a superseded document must be able to see what
     * replaced it and what it replaced, or the library is a set of dead ends.
     *
     * @return Collection<int, self>
     */
    public function lineage(): Collection
    {
        $chain = collect([$this]);

        $earlier = $this->supersedes;
        $guard = 0;
        while ($earlier !== null && $guard++ < 20) {
            $chain->prepend($earlier);
            $earlier = $earlier->supersedes;
        }

        $later = $this->supersededBy()->first();
        $guard = 0;
        while ($later !== null && $guard++ < 20) {
            $chain->push($later);
            $later = $later->supersededBy()->first();
        }

        return new Collection($chain->all());
    }

    public function scopeListed(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereDoesntHave('supersededBy');
    }

    public function downloadCount(): int
    {
        return (int) $this->documents()->sum('download_count');
    }

    public function primaryDocument(): ?Document
    {
        return $this->documents->first() ?? $this->documents()->first();
    }
}
