<?php

namespace App\Models;

use App\Enums\NewsCategory;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use Database\Factories\NewsItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NewsItem extends Model
{
    /** @use HasFactory<NewsItemFactory> */
    use Auditable, HasFactory, HasTranslations, MarksSampleData, SoftDeletes;

    /** @var list<string> */
    protected array $translatable = ['title', 'summary', 'body'];

    protected $fillable = [
        'slug', 'category', 'sector_id', 'published_at',
        'hero_image_path', 'author_name', 'status', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'category' => NewsCategory::class,
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

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Related items: same sector first, then same category, never itself.
     * Ordered so a reader gets the closest match rather than the newest.
     */
    public function related(int $limit = 3)
    {
        return static::published()
            ->whereKeyNot($this->getKey())
            ->with(['translations', 'sector'])
            ->orderByRaw('CASE WHEN sector_id = ? THEN 0 ELSE 1 END', [$this->sector_id])
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$this->category->value])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * The image slot this item falls back to when it has no hero of its own.
     *
     * Differentiated by sector so a listing of four untouched articles does
     * not show the same placeholder four times in a row, which is what makes
     * a grid look generated.
     */
    public function fallbackImageSlot(): ?string
    {
        return match ($this->sector?->slug) {
            'telecommunications' => 'sector-telecom',
            'postal' => 'sector-postal',
            // Broadcasting has no supplied image. Substituting one from
            // another sector would caption a telecoms lab as broadcasting,
            // so the designed empty state renders instead and the gap is on
            // the Secretariat list.
            'broadcasting' => null,
            default => 'institutional',
        };
    }
}
