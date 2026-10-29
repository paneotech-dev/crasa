<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Instrument;
use App\Models\NewsItem;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;

/**
 * The counts the public site and the back office both state (INC-10,
 * PE-T08).
 *
 * The home page, the members pages, the publications page and the
 * dashboard each counted for themselves: the dashboard every row including
 * drafts and samples, the site only what is published, a committee card
 * every publication it owned. The room saw 0, 1, 31 and 32 for the same
 * thing. One definition now: published items only, from the same scopes the
 * public listings use, so a number on the dashboard is the number a reader
 * can count on the site.
 */
final class PublishedCounts
{
    /** The query every publication count starts from. */
    public static function publicationsQuery(): Builder
    {
        return Publication::listed();
    }

    public static function publications(?Country $country = null): int
    {
        return self::publicationsQuery()
            ->when($country, fn ($q, $c) => $q->where('country_id', $c->id))
            ->count();
    }

    /** Published publications outside the postal sector, for the split the dashboard states. */
    public static function ictPublications(): int
    {
        return self::publicationsQuery()->whereRelation('sector', 'slug', '!=', 'postal')->count();
    }

    public static function news(): int
    {
        return NewsItem::published()->count();
    }

    public static function newsThisYear(): int
    {
        return NewsItem::published()->whereYear('published_at', LocalTime::now()->year)->count();
    }

    public static function instruments(?Country $country = null): int
    {
        return Instrument::published()
            ->when($country, fn ($q, $c) => $q->where('country_id', $c->id))
            ->count();
    }

    /** For withCount on a relation: count only what is published. */
    public static function listedScope(): \Closure
    {
        return fn ($query) => $query->where('is_public', true);
    }
}
