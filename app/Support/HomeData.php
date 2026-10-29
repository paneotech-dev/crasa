<?php

namespace App\Support;

use App\Enums\PublicationType;
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
        // newsroom's social feed. Add "upcoming" and "past" here once the
        // events page carries those ids.
        return in_array($fragment, $anchors, true) || in_array($fragment, ['social'], true);
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
                        'type' => __($p->publication_type->labelKey()),
                        'type_key' => $p->publication_type->value,
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
                    'type' => __($n->category->labelKey()),
                    'type_key' => $n->category->value,
                    'date' => $n->date_unknown ? null : $n->published_at?->translatedFormat('j F Y'),
                    'iso' => $n->date_unknown ? null : $n->published_at?->toDateString(),
                    'image' => $n->hero_image_path ? ResizedImage::attributes($n->hero_image_path, 'card') : null,
                    'placeholder' => NewsFormat::placeholder($n->category->value),
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
            'url' => route('events.show', ['locale' => app()->getLocale(), 'meeting' => $m->slug]),
        ];
    }

    /** Figures for the hero, the glance band and the work cards. @return array<string, int> */
    public static function figures(): array
    {
        return PublicCache::remember('k-figures', fn () => [
            'members' => Country::count(),
            'committees' => Committee::where('is_active', true)->count(),
            'publications' => PublishedCounts::publications(),
            'guidelines' => PublishedCounts::publicationsQuery()->where('publication_type', PublicationType::Guideline->value)->count(),
            'postal' => PublishedCounts::publicationsQuery()->whereRelation('sector', 'slug', 'postal')->count(),
            'indicators' => Indicator::count(),
        ]);
    }
}
