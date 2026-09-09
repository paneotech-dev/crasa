<?php

namespace App\Support;

use App\Models\Committee;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Instrument;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Publication;
use App\Models\Sector;

/**
 * How much is in each section, stated where the reader arrives.
 *
 * A reader should never have to scroll a listing to find out how big it is.
 * This is the figure that goes in the section band, and every one of them is
 * a link to what it counts, because a count a reader cannot follow is a fact
 * about the site rather than a way into it.
 *
 * EVERY FIGURE IS A QUERY. Nothing here is written down. A hardcoded count
 * is wrong the first time anything is published and stays wrong silently,
 * which on a platform whose whole claim is that its figures are sourced is
 * the one kind of error that costs the most.
 *
 * A count of zero is returned and shown. Zero is information: a section that
 * is empty and says nothing about being empty reads as a section that failed
 * to load.
 */
final class SectionCounts
{
    /**
     * The counts for a composed page, by slug.
     *
     * A page with nothing to count returns none, and the band renders
     * without the row rather than with an empty rule under it.
     *
     * @return array<int, array{value: string, label: string, url: string|null}>
     */
    public static function forPage(string $slug): array
    {
        return match ($slug) {
            'members' => self::members(),
            'committees' => self::committees(),
            'about' => self::about(),
            default => [],
        };
    }

    /** @return array<int, array{value: string, label: string, url: string|null}> */
    public static function publications(): array
    {
        $locale = app()->getLocale();

        return [
            self::count(Publication::listed()->count(), 'publications.count_documents', route('publications', ['locale' => $locale])),
            self::count(
                Publication::listed()->whereNotNull('country_id')->distinct()->count('country_id'),
                'publications.count_states',
                route('members', ['locale' => $locale])
            ),
            self::count(Instrument::published()->count(), 'publications.count_instruments', null),
        ];
    }

    /** @return array<int, array{value: string, label: string, url: string|null}> */
    public static function members(): array
    {
        $locale = app()->getLocale();

        return [
            self::count(Country::count(), 'blocks.hero_count_members', null),
            self::count(Committee::where('is_active', true)->count(), 'members.count_committees', route('committees', ['locale' => $locale])),
            self::count(Indicator::active()->count(), 'blocks.hero_count_indicators', route('comparator', ['locale' => $locale])),
            self::count(Publication::listed()->count(), 'blocks.hero_count_publications', route('publications', ['locale' => $locale])),
        ];
    }

    /** @return array<int, array{value: string, label: string, url: string|null}> */
    public static function committees(): array
    {
        $locale = app()->getLocale();

        return [
            self::count(Committee::where('is_active', true)->count(), 'members.count_committees', null),
            self::count(
                Committee::where('is_active', true)->withCount('countries')->get()->sum('countries_count'),
                'committees.count_seats',
                route('members', ['locale' => $locale])
            ),
            self::count(Meeting::count(), 'committees.count_meetings', route('events', ['locale' => $locale])),
        ];
    }

    /** @return array<int, array{value: string, label: string, url: string|null}> */
    public static function newsroom(): array
    {
        return [
            self::count(NewsItem::published()->count(), 'news.count_items', null),
            self::count(
                NewsItem::published()->whereNotNull('sector_id')->distinct()->count('sector_id'),
                'news.count_sectors',
                null
            ),
        ];
    }

    /** @return array<int, array{value: string, label: string, url: string|null}> */
    public static function events(): array
    {
        return [
            self::count(Meeting::upcoming()->count(), 'events.count_upcoming', null),
            self::count(Meeting::past()->count(), 'events.count_past', null),
        ];
    }

    /** @return array<int, array{value: string, label: string, url: string|null}> */
    public static function comparator(): array
    {
        $locale = app()->getLocale();

        return [
            self::count(Indicator::active()->count(), 'blocks.hero_count_indicators', null),
            self::count(Country::count(), 'blocks.hero_count_members', route('members', ['locale' => $locale])),
            self::count(Indicator::active()->distinct()->count('category'), 'comparator.count_categories', null),
        ];
    }

    /** @return array<int, array{value: string, label: string, url: string|null}> */
    public static function about(): array
    {
        $locale = app()->getLocale();

        return [
            self::count(Country::count(), 'blocks.hero_count_members', route('members', ['locale' => $locale])),
            self::count(Committee::where('is_active', true)->count(), 'members.count_committees', route('committees', ['locale' => $locale])),
            self::count(Sector::count(), 'about.count_sectors', null),
        ];
    }

    /**
     * The searchable corpus, stated before a reader types anything. It is
     * the honest answer to "is it worth searching here".
     *
     * @return array<int, array{value: string, label: string, url: string|null}>
     */
    public static function search(): array
    {
        $locale = app()->getLocale();

        return [
            self::count(Publication::listed()->count(), 'publications.count_documents', route('publications', ['locale' => $locale])),
            self::count(NewsItem::published()->count(), 'news.count_items', route('newsroom', ['locale' => $locale])),
            self::count(Instrument::published()->count(), 'publications.count_instruments', null),
        ];
    }

    /**
     * How many documents a member state has on the platform, for its card
     * and its page.
     *
     * @return array<string, int>
     */
    public static function forCountry(Country $country): array
    {
        return [
            'instruments' => Instrument::published()->where('country_id', $country->id)->count(),
            'publications' => Publication::listed()->where('country_id', $country->id)->count(),
            'committees' => $country->committees()->count(),
            'indicators' => IndicatorValue::query()
                ->where('country_id', $country->id)
                ->whereHas('indicator', fn ($q) => $q->where('is_active', true))
                ->distinct()
                ->count('indicator_id'),
        ];
    }

    /**
     * @return array{value: string, label: string, url: string|null}
     */
    private static function count(int $value, string $key, ?string $url): array
    {
        return [
            'value' => number_format($value),
            'label' => trans_choice($key, $value, ['count' => $value]),
            'url' => $url,
        ];
    }
}
