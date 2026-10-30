<?php

namespace App\Http\Controllers;

use App\Support\Themes;

/**
 * The thematic areas on the public site (30 October 2026): an index of
 * every theme and one page per theme, each with a way to partner with
 * CRASA on it. Public and read only.
 */
class ThemeController extends Controller
{
    public function index()
    {
        return view('pages.themes.index', ['themes' => Themes::all()]);
    }

    public function show(string $theme)
    {
        $current = Themes::find($theme);
        abort_if($current === null, 404);

        return view('pages.themes.show', [
            'theme' => $current,
            'committees' => Themes::committees($current),
            'others' => array_filter(Themes::all(), fn ($t) => $t['slug'] !== $theme),
        ]);
    }
}
