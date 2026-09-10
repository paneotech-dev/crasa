<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A CRASA project. WS-12's no-content-loss clause.
 *
 * Current and past are the same records in two states, not two tables. A
 * project that finishes keeps its page, its documents and its URL; only
 * `state` changes. That is what the legacy site's two menu sections
 * actually are, and modelling them as separate things would break the
 * permalink on the day a project ends.
 */
class Project extends Model
{
    use Auditable, HasFactory, HasTranslations, MarksSampleData, SoftDeletes;

    /** @var list<string> */
    protected array $translatable = ['title', 'summary', 'body'];

    protected $fillable = [
        'slug', 'state', 'sector_id', 'partner', 'starts_on', 'ends_on',
        'hero_image_path', 'status', 'published_at', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'published_at' => 'datetime',
            'is_sample' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function legacyRecord(): MorphOne
    {
        return $this->morphOne(LegacyRecord::class, 'recordable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isCurrent(): bool
    {
        return $this->state === 'current';
    }
}
