<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Secretariat announcement to the membership. RP-05.
 *
 * Members-only by construction: nothing public routes to this model, and
 * `visibleTo()` is the only way a controller reads it.
 */
class Announcement extends Model
{
    use Auditable, HasFactory, HasTranslations, MarksSampleData, SoftDeletes;

    /** @var list<string> */
    protected array $translatable = ['title', 'body'];

    protected $fillable = [
        'author_id', 'sector_id', 'committee_id', 'slug',
        'status', 'published_at', 'expires_on', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_on' => 'date',
            'is_sample' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', now()->toDateString()));
    }

    /**
     * What one reader may see.
     *
     * An announcement narrowed to a committee reaches the people who hold a
     * seat on it. An announcement narrowed to a sector reaches the states
     * whose authority regulates that sector, because a postal notice sent
     * to a broadcasting-only regulator is noise that trains people to stop
     * reading.
     *
     * The Secretariat sees everything, because it wrote it.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->seesAllCountries()) {
            return $query;
        }

        $committees = $user->committees()->pluck('committees.id');
        $sectors = $user->authority?->sectors()->pluck('sectors.id') ?? collect();

        return $query->where(function (Builder $q) use ($committees, $sectors): void {
            $q->where(fn (Builder $general) => $general->whereNull('sector_id')->whereNull('committee_id'))
                ->orWhereIn('committee_id', $committees)
                ->orWhereIn('sector_id', $sectors);
        });
    }
}
