<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The provenance of one imported record, and one row of the 301 map.
 *
 * A legacy URL that a regulator has cited in a ministerial brief has to
 * keep working. That is the whole reason this table exists: the redirect
 * map is `select legacy_url, new_path from legacy_records`, and the test
 * that walks the manifest reads the same rows the middleware does, so a
 * mapping that stops redirecting fails the suite rather than the reader.
 */
class LegacyRecord extends Model
{
    protected $fillable = [
        'legacy_url', 'legacy_path', 'legacy_category', 'recordable_type', 'recordable_id',
        'new_path', 'needs_review', 'review_note', 'date_source', 'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'needs_review' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            $record->legacy_path = self::normalise($record->legacy_url);
        });
    }

    public function recordable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The path a legacy URL maps to, matched on path alone.
     *
     * The legacy site is reachable as crasa.org and www.crasa.org, and a
     * citation may carry either, with or without a trailing slash. Matching
     * on the stored path rather than the whole URL means one row answers
     * all four forms.
     */
    public static function pathFor(string $path): ?self
    {
        return static::query()->where('legacy_path', self::normalise($path))->first();
    }

    /** The stored path, which is what the redirect matches on. */
    public function legacyPath(): string
    {
        return $this->legacy_path ?? self::normalise($this->legacy_url);
    }

    /** No host, no query, no trailing slash, always one leading slash. */
    public static function normalise(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return '/'.trim(is_string($path) ? rawurldecode($path) : '', '/');
    }

    public function scopeNeedingReview(Builder $query): Builder
    {
        return $query->where('needs_review', true);
    }
}
