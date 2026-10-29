<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The address of a new page (F9): lower-case words joined by hyphens,
 * unique among pages, and never one of the platform's own addresses. A page
 * called "publications" would be shadowed by the library (pages are matched
 * last), so it is refused rather than created and never seen.
 */
final class PageAddress
{
    private const ALWAYS = [
        'admin', 'api', 'new', 'en', 'fr', 'pt', 'login', 'logout', 'register', 'password',
        'storage', 'css', 'js', 'img', 'fonts', 'build', 'vendor', 'media', 'uploads',
        'sitemap', 'robots', 'feed', 'rss', 'search', 'home', 'index', 'manifest',
    ];

    public static function suggest(string $title): string
    {
        $base = Str::limit(Str::slug($title), 80, '') ?: 'page';
        $base = rtrim($base, '-');
        $slug = $base;
        $n = 2;

        while (self::isReserved($slug) || Page::withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, self::reserved(), true);
    }

    /** @return list<string> */
    public static function reserved(): array
    {
        $words = self::ALWAYS;

        foreach (Route::getRoutes() as $route) {
            $segments = explode('/', trim($route->uri(), '/'));

            if (($segments[0] ?? '') === '{locale}') {
                array_shift($segments);
            }

            $first = $segments[0] ?? '';

            if ($first !== '' && ! str_starts_with($first, '{')) {
                $words[] = $first;
            }
        }

        return array_values(array_unique($words));
    }
}
