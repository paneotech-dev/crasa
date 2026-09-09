<?php

namespace App\Http\Controllers;

use App\Enums\NewsCategory;
use App\Models\NewsItem;
use App\Models\Sector;
use App\Models\Tag;
use Illuminate\Http\Request;

/**
 * WS-06.
 *
 * Facet counts come from one grouped query per facet, each computed with the
 * other filters applied but not its own, so the number states what a reader
 * would get if they clicked rather than how many exist in total.
 */
class NewsroomController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->string('category')->value() ?: null;
        $sector = $request->string('sector')->value() ?: null;
        $tag = $request->string('tag')->value() ?: null;

        $filters = ['category' => $category, 'sector' => $sector, 'tag' => $tag];

        $items = NewsItem::published()
            ->with(['translations', 'sector', 'tags.translations'])
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($sector, fn ($q) => $q->whereHas('sector', fn ($s) => $s->where('slug', $sector)))
            ->when($tag, fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('slug', $tag)))
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('pages.newsroom.index', [
            'items' => $items,
            'counts' => $this->facetCounts($filters),
            'sectors' => Sector::orderBy('name')->get(),
            'tags' => Tag::with('translations')->orderBy('slug')->get(),
            'categories' => NewsCategory::cases(),
            'activeCategory' => $category,
            'activeSector' => $sector,
            'activeTag' => $tag,
        ]);
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return array<string, array<string, int>>
     */
    private function facetCounts(array $filters): array
    {
        $without = function (string $key) use ($filters) {
            $applied = array_merge($filters, [$key => null]);

            return NewsItem::published()
                ->when($applied['category'], fn ($q, $v) => $q->where('category', $v))
                ->when($applied['sector'], fn ($q, $v) => $q->whereHas('sector', fn ($s) => $s->where('slug', $v)))
                ->when($applied['tag'], fn ($q, $v) => $q->whereHas('tags', fn ($t) => $t->where('slug', $v)));
        };

        return [
            'category' => $without('category')
                ->selectRaw('category, COUNT(*) as total')
                ->groupBy('category')
                ->pluck('total', 'category')
                ->map(fn ($total) => (int) $total)
                ->all(),

            'sector' => $without('sector')
                ->join('sectors', 'sectors.id', '=', 'news_items.sector_id')
                ->selectRaw('sectors.slug, COUNT(*) as total')
                ->groupBy('sectors.slug')
                ->pluck('total', 'slug')
                ->map(fn ($total) => (int) $total)
                ->all(),

            // Tags are polymorphic, so the join carries the morph type as
            // well as the id. Without it a publication tagged "roaming"
            // would be counted as a news item.
            'tag' => $without('tag')
                ->join('taggables', function ($join) {
                    $join->on('taggables.taggable_id', '=', 'news_items.id')
                        ->where('taggables.taggable_type', '=', (new NewsItem)->getMorphClass());
                })
                ->join('tags', 'tags.id', '=', 'taggables.tag_id')
                ->selectRaw('tags.slug, COUNT(DISTINCT news_items.id) as total')
                ->groupBy('tags.slug')
                ->pluck('total', 'slug')
                ->map(fn ($total) => (int) $total)
                ->all(),
        ];
    }

    public function show(string $slug)
    {
        $item = NewsItem::published()
            ->where('slug', $slug)
            ->with(['translations', 'sector', 'tags.translations'])
            ->first();

        abort_if($item === null, 404);

        return view('pages.newsroom.show', [
            'item' => $item,
            'related' => $item->related(),
        ]);
    }
}
