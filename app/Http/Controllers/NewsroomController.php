<?php

namespace App\Http\Controllers;

use App\Enums\NewsCategory;
use App\Models\NewsItem;
use App\Models\Sector;
use App\Models\Tag;
use App\Support\Facets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * WS-06.
 *
 * THE SAME SIDEBAR THE LIBRARY USES. Screen 07 draws the filter panel and
 * no screen draws a second treatment for the newsroom, so the newsroom gets
 * that one: an aside of checkbox facets beside the results, each option
 * carrying the number of results a click would produce. Three hand-written
 * copies of the same filter is how one of them ends up with the count and
 * the other two without.
 *
 * Every facet holds a set, so it takes ?category[]=tender&category[]=news.
 * A scalar ?category=tender still resolves: links written before
 * 9 September 2026 must keep working.
 *
 * Counts are grouped in one query per facet while that facet has nothing
 * selected, which is the ordinary case and the case where a group count is
 * exactly the size of the result the click would produce. Once a facet has
 * a selection a click changes a set rather than defining one, and no single
 * grouped query can answer it, so it costs one count per option. See
 * PublicationController, which carries the same note.
 */
class NewsroomController extends Controller
{
    /** Cards per page: three columns, four full rows. */
    public const PER_PAGE = 12;

    public function index(Request $request)
    {
        $filters = [
            'category' => Facets::values($request, 'category'),
            'sector' => Facets::values($request, 'sector'),
            'tag' => Facets::values($request, 'tag'),
        ];

        $year = (int) $request->query('year') ?: null;
        $search = trim((string) $request->query('q', ''));
        $sort = $request->query('sort') === 'oldest' ? 'oldest' : 'newest';

        $categories = collect(NewsCategory::cases());
        $sectors = Sector::orderBy('name')->get();
        $tags = Tag::with('translations')->orderBy('slug')->get();

        $options = [
            'category' => $categories->map(fn ($c) => (string) $c->value)->all(),
            'sector' => $sectors->pluck('slug')->all(),
            'tag' => $tags->pluck('slug')->all(),
        ];

        $ordered = fn () => $this->query($filters, $year, $search)
            ->with(['translations', 'sector', 'tags.translations'])
            ->orderBy('date_unknown')
            ->orderBy('published_at', $sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc');

        // The newest item leads page 1 as a wide card and is left out of the
        // grid on every page, so the grid is always whole rows of twelve.
        $page = max(1, (int) $request->query('page', 1));
        $featured = $sort === 'newest' ? $ordered()->first() : null;

        $items = $ordered()
            ->when($featured, fn ($q) => $q->whereKeyNot($featured->getKey()))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('pages.newsroom.index', [
            'items' => $items,
            'featured' => $page === 1 ? $featured : null,
            'matching' => $items->total() + ($featured ? 1 : 0),
            'filters' => $filters,
            'year' => $year,
            'search' => $search,
            'sort' => $sort,
            'years' => $this->yearCounts($filters, $search),
            'counts' => $this->facetCounts($filters, $options, $year, $search),
            'total' => NewsItem::published()->count(),
            'sectors' => $sectors,
            'tags' => $tags,
            'categories' => $categories,
        ]);
    }

    /**
     * Years that hold at least one dated item under the other filters.
     *
     * @param  array<string, list<string>>  $filters
     * @return array<int, int>
     */
    private function yearCounts(array $filters, string $search): array
    {
        return $this->query($filters, null, $search)
            ->where('date_unknown', false)
            ->get(['published_at'])
            ->groupBy(fn (NewsItem $item) => (int) $item->published_at->year)
            ->map->count()
            ->sortKeysDesc()
            ->all();
    }

    /**
     * @param  array<string, list<string>>  $filters
     * @return Builder<NewsItem>
     */
    private function query(array $filters, ?int $year = null, string $search = ''): Builder
    {
        return NewsItem::published()
            ->when($filters['category'], fn ($q, $v) => $q->whereIn('category', $v))
            ->when($filters['sector'], fn ($q, $v) => $q->whereHas('sector', fn ($s) => $s->whereIn('slug', $v)))
            ->when($filters['tag'], fn ($q, $v) => $q->whereHas('tags', fn ($t) => $t->whereIn('slug', $v)))
            ->when($year, fn ($q, $v) => $q->where('date_unknown', false)->whereYear('published_at', $v))
            ->when($search !== '', fn ($q) => $q->whereHas('translations', fn ($t) => $t
                ->whereIn('field', ['title', 'summary', 'body'])
                ->where('value', 'like', '%'.addcslashes($search, '%_\\').'%')));
    }

    /**
     * @param  array<string, list<string>>  $filters
     * @param  array<string, list<string>>  $options
     * @return array<string, array<string, int>>
     */
    private function facetCounts(array $filters, array $options, ?int $year = null, string $search = ''): array
    {
        // Closures, so a facet that has a selection never runs the grouped
        // query whose answer it would throw away.
        $grouped = [
            'category' => fn (Builder $q) => $q
                ->selectRaw('category, COUNT(*) as total')
                ->groupBy('category')
                ->pluck('total', 'category'),

            'sector' => fn (Builder $q) => $q
                ->join('sectors', 'sectors.id', '=', 'news_items.sector_id')
                ->selectRaw('sectors.slug, COUNT(*) as total')
                ->groupBy('sectors.slug')
                ->pluck('total', 'slug'),

            // Tags are polymorphic, so the join carries the morph type as
            // well as the id. Without it a publication tagged "roaming"
            // would be counted as a news item.
            'tag' => fn (Builder $q) => $q
                ->join('taggables', function ($join) {
                    $join->on('taggables.taggable_id', '=', 'news_items.id')
                        ->where('taggables.taggable_type', '=', (new NewsItem)->getMorphClass());
                })
                ->join('tags', 'tags.id', '=', 'taggables.tag_id')
                ->selectRaw('tags.slug, COUNT(DISTINCT news_items.id) as total')
                ->groupBy('tags.slug')
                ->pluck('total', 'slug'),
        ];

        $counts = [];

        foreach ($options as $key => $values) {
            $counts[$key] = ($filters[$key] ?? []) === []
                ? $grouped[$key]($this->query(array_merge($filters, [$key => []]), $year, $search))
                    ->map(fn ($total) => (int) $total)
                    ->all()
                : $this->countsFor($filters, $key, $values, $year, $search);
        }

        return $counts;
    }

    /**
     * @param  array<string, list<string>>  $filters
     * @param  list<string>  $options
     * @return array<string, int>
     */
    private function countsFor(array $filters, string $key, array $options, ?int $year = null, string $search = ''): array
    {
        $counts = [];

        foreach ($options as $option) {
            $option = (string) $option;

            $next = in_array($option, $filters[$key], true)
                ? array_values(array_diff($filters[$key], [$option]))
                : [...$filters[$key], $option];

            $counts[$option] = $this->query(array_merge($filters, [$key => $next]), $year, $search)->count();
        }

        return $counts;
    }

    public function show(string $slug)
    {
        $item = NewsItem::published()
            ->where('slug', $slug)
            ->with(['translations', 'sector', 'tags.translations'])
            ->first();

        abort_if($item === null, 404);

        // Previous and next in the newsroom's own order, newest first.
        $newer = NewsItem::published()->with('translations')
            ->where('date_unknown', $item->date_unknown)
            ->where(fn ($q) => $q->where('published_at', '>', $item->published_at)
                ->orWhere(fn ($q) => $q->where('published_at', $item->published_at)->where('id', '>', $item->id)))
            ->orderBy('published_at')->orderBy('id')->first();
        $older = NewsItem::published()->with('translations')
            ->where('date_unknown', $item->date_unknown)
            ->where(fn ($q) => $q->where('published_at', '<', $item->published_at)
                ->orWhere(fn ($q) => $q->where('published_at', $item->published_at)->where('id', '<', $item->id)))
            ->orderByDesc('published_at')->orderByDesc('id')->first();

        return view('pages.newsroom.show', [
            'item' => $item,
            'related' => $item->related(),
            'newer' => $newer,
            'older' => $older,
        ]);
    }
}
