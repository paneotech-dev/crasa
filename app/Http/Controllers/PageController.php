<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;

/**
 * Renders a composed page from its block records.
 *
 * Blocks and their translations load in two queries regardless of how many
 * blocks a page has.
 */
class PageController extends Controller
{
    public function home()
    {
        return $this->render('home');
    }

    public function show(Request $request)
    {
        return $this->render($request->route()->defaults['slug']);
    }

    /**
     * Any other published page, by slug.
     *
     * Registered last, so it never shadows a named route. Nothing here
     * trusts the slug beyond a lookup: the route pattern restricts it to
     * lowercase words and hyphens, and an unpublished or missing page is a
     * 404 rather than a redirect, so the address does not reveal that a
     * draft with that slug exists.
     */
    public function showBySlug(Request $request)
    {
        return $this->render((string) $request->route('slug'));
    }

    private function render(string $slug)
    {
        $page = Page::published()
            ->where('slug', $slug)
            ->with(['translations', 'visibleBlocks'])
            ->first();

        abort_if($page === null, 404);

        // A page with its own layout (29 October 2026, UI system) reads the
        // same block records through a template drawn for it; every other
        // page is composed from its blocks in order.
        $view = 'pages.static.'.$slug;

        return view(view()->exists($view) ? $view : 'pages.composed', ['page' => $page]);
    }
}
