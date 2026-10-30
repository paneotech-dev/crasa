<?php

namespace App\Support;

use App\Models\Committee;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Publication;
use Illuminate\Support\Facades\Storage;

/**
 * The data the Concept 3 header, mega menu and home page draw (29 October
 * 2026).
 *
 * Every value is a plain array and goes through PublicCache, so the header
 * on every page and the home page add no queries once warm. Publications
 * without a file or an external link never appear here.
 */
final class HomeData
{
    /**
     * The mega menu panels for the header's items, keyed by item key.
     *
     * @param  list<array<string, mixed>>  $primary
     * @return array<string, array<string, mixed>>
     */
    public static function panels(array $primary): array
    {
        $config = config('megamenu', []);
        $panels = [];

        foreach ($primary as $item) {
            $section = self::section($item['url'] ?? null);

            if ($section === null || ! isset($config[$section])) {
                continue;
            }

            $panel = $config[$section];
            $keep = fn ($l) => self::anchorExists($l['path'])
                && (! isset($l['page']) || self::pageIsPublished($l['page']))
                && ! Navigation::pointsAtUnwrittenSection(Navigation::url($l['path']));
            $links = array_values(array_map(fn ($l) => [
                'label' => __($l['label']),
                'desc' => isset($l['desc']) ? __($l['desc']) : null,
                'url' => Navigation::url($l['path']),
            ], array_filter($panel['links'] ?? [], $keep)));

            // Children a Secretariat editor adds to the menu record are
            // listed after the configured ones, so the record still wins.
            foreach ($item['children'] ?? [] as $child) {
                $links[] = ['label' => $child['label'], 'desc' => null, 'url' => $child['url']];
            }

            $panels[$item['key']] = [
                'section' => $section,
                'links' => $links,
                'related' => array_values(array_map(fn ($l) => [
                    'label' => __($l['label']),
                    'url' => Navigation::url($l['path']),
                ], array_filter($panel['related'] ?? [], $keep))),
                'feature' => self::feature($panel['feature'] ?? 'comparator'),
            ];
        }

        return $panels;
    }

    /**
     * A link to a page record is kept only while the page is published, so
     * an unpublished or deleted page leaves the panel rather than a 404.
     */
    private static function pageIsPublished(string $slug): bool
    {
        $slugs = PublicCache::remember('k-pages', fn () => \App\Models\Page::published()->pluck('slug')->all());

        return in_array($slug, $slugs, true);
    }

    /**
     * A section link ("about#governance") is kept only while a visible
     * block on the site carries that anchor, so a panel never links to a
     * section that is not there.
     */
    private static function anchorExists(string $path): bool
    {
        $fragment = parse_url($path, PHP_URL_FRAGMENT);

        if ($fragment === null || $fragment === false) {
            return true;
        }

        $anchors = PublicCache::remember('k-anchors', fn () => \App\Models\PageBlock::whereNotNull('anchor')
            ->where('is_visible', true)->pluck('anchor')->unique()->values()->all());

        // Sections a page template draws itself rather than a block: the
        // newsroom's social feed, the events page's upcoming and past lists.
        return in_array($fragment, $anchors, true) || in_array($fragment, ['social', 'upcoming', 'past'], true);
    }

    /** The last path segment of a header address: "about" for /en/about. */
    public static function section(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $parts = explode('/', $path);

        return $parts === [''] ? null : end($parts);
    }

    /** @return array<string, mixed>|null */
    public static function feature(string $kind): ?array
    {
        $features = self::features();

        return $features[$kind] ?? $features['comparator'];
    }

    /** @return array<string, array<string, mixed>|null> */
    public static function features(): array
    {
        return PublicCache::remember('k-features', function () {
            $locale = app()->getLocale();
            $publication = self::guidelines(1)[0] ?? null;
            $news = self::latestNews(1)[0] ?? null;
            $event = Meeting::public()->upcoming()->first();

            return [
                'publication' => $publication ? [
                    'label' => __('chome.feature_publication'),
                    'title' => $publication['title'],
                    'meta' => trim($publication['type'].' '.($publication['year'] ? '· '.$publication['year'] : '')),
                    'url' => $publication['url'],
                    'thumb' => $publication['thumb'],
                    'kind' => 'publication',
                ] : null,
                'news' => $news ? [
                    'label' => __('chome.feature_news'),
                    'title' => $news['title'],
                    'meta' => $news['date'],
                    'url' => $news['url'],
                    'thumb' => $news['image']['src'] ?? null,
                    'kind' => 'news',
                ] : null,
                'event' => $event ? [
                    'label' => __('chome.feature_event'),
                    'title' => $event->title,
                    'meta' => collect([
                        $event->starts_on?->translatedFormat('j F Y'),
                        $event->location,
                    ])->filter()->implode(' · '),
                    'url' => route('events.show', ['locale' => $locale, 'meeting' => $event->slug]),
                    'thumb' => null,
                    'kind' => 'event',
                    'day' => $event->starts_on?->format('j'),
                    'month' => $event->starts_on?->translatedFormat('M'),
                ] : null,
                'comparator' => [
                    'label' => __('chome.feature_comparator'),
                    'title' => __('chome.feature_comparator_title'),
                    'meta' => __('chome.feature_comparator_body'),
                    'url' => Navigation::url('comparator'),
                    'thumb' => null,
                    'kind' => 'comparator',
                ],
            ];
        });
    }

    /**
     * The latest guidelines (C9): a file or an external link, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function guidelines(int $limit = 3): array
    {
        return PublicCache::remember('k-guidelines:'.$limit, function () use ($limit) {
            return Publication::listed()
                ->where(fn ($q) => $q->whereHas('documents')->orWhere(fn ($e) => $e->whereNotNull('external_url')->where('external_url', '!=', '')))
                ->with(['translations', 'sector', 'documents'])
                ->orderByDesc('published_on')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(function (Publication $p) {
                    $document = $p->primaryDocument();
                    $thumb = $document?->thumbnail_path && Storage::disk('public')->exists($document->thumbnail_path)
                        ? Storage::disk('public')->url($document->thumbnail_path)
                        : null;

                    return [
                        'title' => $p->translated('title', $p->slug),
                        'type' => $p->typeLabel(),
                        'type_key' => $p->typeSlug(),
                        'sector' => $p->sector?->name,
                        'year' => $p->published_on?->format('Y'),
                        'format' => $document ? strtoupper(pathinfo((string) $document->original_filename, PATHINFO_EXTENSION)) : null,
                        'size' => $document?->readableSize(),
                        'locale' => $document?->locale ? strtoupper($document->locale) : null,
                        'thumb' => $thumb,
                        'url' => route('publications.show', ['locale' => app()->getLocale(), 'slug' => $p->slug]),
                        'download' => $document
                            ? route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id])
                            : $p->external_url,
                        'external' => $document === null,
                    ];
                })
                ->values()
                ->all();
        });
    }

    /** @return list<array<string, mixed>> */
    public static function latestNews(int $limit = 3): array
    {
        return PublicCache::remember('k-news:'.$limit, function () use ($limit) {
            return NewsItem::published()
                ->with(['translations', 'sector'])
                ->orderBy('date_unknown')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get()
                ->map(fn (NewsItem $n) => [
                    'id' => $n->id,
                    'title' => $n->translated('title', $n->slug),
                    'summary' => NewsFormat::excerpt($n->translated('summary', '') ?: strip_tags($n->translated('body', ''))),
                    'type' => $n->categoryLabel(),
                    'type_key' => $n->categorySlug(),
                    'date' => $n->date_unknown ? null : $n->published_at?->translatedFormat('j F Y'),
                    'iso' => $n->date_unknown ? null : $n->published_at?->toDateString(),
                    'image' => $n->hero_image_path ? ResizedImage::attributes($n->hero_image_path, 'card') : null,
                    'placeholder' => NewsFormat::placeholder($n->categorySlug()),
                    // Null unless an editor set a focal point: the card keeps its framing.
                    'position' => $n->hero_focal_x !== null ? $n->heroPosition() : null,
                    'url' => route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $n->slug]),
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Events for the news column: upcoming first, then the most recent past.
     *
     * @return list<array<string, mixed>>
     */
    public static function events(int $limit = 2): array
    {
        return PublicCache::remember('k-events:'.$limit, function () use ($limit) {
            $upcoming = Meeting::public()->upcoming()->limit($limit)->get();
            $past = $upcoming->count() < $limit
                ? Meeting::public()->past()->where('date_unknown', false)->limit($limit - $upcoming->count())->get()
                : collect();

            return $upcoming->map(fn ($m) => self::event($m, false))
                ->concat($past->map(fn ($m) => self::event($m, true)))
                ->values()
                ->all();
        });
    }

    /** @return array<string, mixed> */
    private static function event(Meeting $m, bool $past): array
    {
        return [
            'title' => $m->title,
            'month' => $m->starts_on?->translatedFormat('M'),
            'day' => $m->starts_on && $m->ends_on && ! $m->ends_on->equalTo($m->starts_on) && $m->ends_on->month === $m->starts_on->month
                ? $m->starts_on->format('j').'–'.$m->ends_on->format('j')
                : $m->starts_on?->format('j'),
            'year' => $m->starts_on?->format('Y'),
            'iso' => $m->starts_on?->toDateString(),
            'place' => $m->venue_country && ! str_contains((string) $m->location, (string) $m->venue_country)
                ? collect([$m->location, $m->venue_country])->filter()->implode(', ')
                : (string) $m->location,
            'past' => $past,
            // Day 3: held, cancelled or postponed, computed from the dates and status.
            // The status only: this array is cached across languages.
            'status' => $m->displayStatus(),
            'url' => route('events.show', ['locale' => app()->getLocale(), 'meeting' => $m->slug]),
        ];
    }

    /**
     * The three tiles under the home hero (item 8f): the library's
     * guidelines, the comparator's reach and the next public meeting, each
     * counted or read from the data at render time. A tile with nothing to
     * say (no guideline, no indicator, no meeting to come) is left out.
     *
     * @return list<array{label: string, value: string, url: string}>
     */
    public static function tiles(): array
    {
        $nav = fn (string $path) => Navigation::url($path);
        $figures = self::figures();
        $tiles = [];

        if ($figures['guidelines'] > 0) {
            $tiles[] = [
                'label' => __('d3_polish.tile_library'),
                'value' => trans_choice('d3_polish.tile_library_value', $figures['guidelines'], ['count' => $figures['guidelines']]),
                'url' => $nav('publications?type[]=guideline'),
            ];
        }

        $indicators = Indicator::active()->count();

        if ($indicators > 0) {
            $tiles[] = [
                'label' => __('d3_polish.tile_comparator'),
                'value' => __('d3_polish.tile_comparator_value', ['states' => $figures['members'], 'indicators' => $indicators]),
                'url' => $nav('comparator'),
            ];
        }

        $next = Meeting::public()->upcoming()->limit(5)->get()->first(fn (Meeting $m) => ! $m->isCancelled());

        if ($next !== null) {
            $place = trim(explode(',', (string) $next->location)[0]);
            $date = $next->starts_on->copy()->locale(app()->getLocale())->isoFormat(__('d3_polish.tile_date_format'));
            $tiles[] = [
                'label' => __('d3_polish.tile_meeting'),
                'value' => $place !== '' ? $date.', '.($next->isOnline() && strcasecmp($place, 'Online') === 0 ? __('e.online') : $place) : $date,
                'url' => route('events.show', ['locale' => app()->getLocale(), 'meeting' => $next->slug]),
            ];
        }

        return $tiles;
    }

    /** Figures for the hero, the glance band and the work cards. @return array<string, int> */
    public static function figures(): array
    {
        return PublicCache::remember('k-figures', fn () => [
            'members' => Country::count(),
            'committees' => Committee::where('is_active', true)->count(),
            'publications' => PublishedCounts::publications(),
            'guidelines' => PublishedCounts::publicationsQuery()->where('publication_type', 'guideline')->count(),
            'postal' => PublishedCounts::publicationsQuery()->whereRelation('sector', 'slug', 'postal')->count(),
            'indicators' => Indicator::count(),
        ]);
    }
}
