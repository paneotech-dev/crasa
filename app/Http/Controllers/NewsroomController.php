<?php

namespace App\Http\Controllers;

use App\Enums\NewsCategory;
use App\Models\NewsItem;
use App\Models\Sector;
use App\Models\Tag;
use Illuminate\Http\Request;

class NewsroomController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->string('category')->value() ?: null;
        $sector = $request->string('sector')->value() ?: null;
        $tag = $request->string('tag')->value() ?: null;

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
            'sectors' => Sector::orderBy('name')->get(),
            'tags' => Tag::with('translations')->orderBy('slug')->get(),
            'categories' => NewsCategory::cases(),
            'activeCategory' => $category,
            'activeSector' => $sector,
            'activeTag' => $tag,
        ]);
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
