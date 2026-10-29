<?php

namespace App\Models\Concerns;

use App\Support\PublicCache;
use App\Support\RequestMemo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * A term in one of the lists maintained under Content > Taxonomies: a news
 * category or a publication type. A slug, a name in three languages in the
 * translations table, and an order.
 *
 * The whole list is read once per request: a newsroom page of twelve cards
 * asks for a category label twelve times, and five rows with their
 * translations are one query. A save or delete forgets the list, so the
 * screen that changed it shows the change.
 */
trait IsTaxonomyTerm
{
    public static function bootIsTaxonomyTerm(): void
    {
        // The homepage cards carry category and type names in the public
        // cache, so a rename shows there at once too.
        static::saved(function () {
            static::forgetTerms();
            PublicCache::flush();
        });
        static::deleted(function () {
            static::forgetTerms();
            PublicCache::flush();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The slug. The back-office form compares a field's current value as
     * a string, and a term held on a record is printed as what is stored.
     */
    public function __toString(): string
    {
        return (string) $this->slug;
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('slug');
    }

    /** The name in the page language, then English, then the slug. */
    public function label(?string $locale = null): string
    {
        return $this->translated('name', $this->slug, $locale);
    }

    /** @return Collection<int, static> */
    public static function terms(): Collection
    {
        return RequestMemo::remember('taxonomy.'.static::class, fn () => static::query()->with('translations')->ordered()->get());
    }

    public static function bySlug(?string $slug): ?static
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return static::terms()->firstWhere('slug', $slug);
    }

    public static function forgetTerms(): void
    {
        if (app()->bound('request')) {
            RequestMemo::forget('taxonomy.'.static::class);
        }
    }
}
