<?php

namespace App\Http\Controllers;

use App\Models\Page;

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

    public function show(string $slug)
    {
        return $this->render($slug);
    }

    private function render(string $slug)
    {
        $page = Page::published()
            ->where('slug', $slug)
            ->with(['translations', 'visibleBlocks'])
            ->first();

        abort_if($page === null, 404);

        return view('pages.composed', ['page' => $page]);
    }
}
