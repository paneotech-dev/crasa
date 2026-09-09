<?php

namespace App\Support;

use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the primary navigation for the language currently being rendered.
 */
final class Navigation
{
    /**
     * The header menu.
     *
     * Records first, config as a floor. If the menu_items table is empty —
     * a fresh install, a bad import, a purge that went further than
     * intended — the site falls back to the config list rather than
     * rendering a header with no links in it. A site with no navigation is
     * a broken site; a site with the default navigation is a working one.
     *
     * @return list<array{key: string, label: string, url: string, current: bool}>
     */
    public static function primary(): array
    {
        $items = self::fromRecords('header');

        return $items === [] ? self::fromConfig() : $items;
    }

    /**
     * The footer menu: column headings with links under them, which is the
     * depth screen 02 draws.
     *
     * @return list<array{label: string, items: list<array{label: string, url: string}>}>
     */
    public static function footer(): array
    {
        $columns = self::footerFromRecords();

        return $columns === [] ? self::footerFromConfig() : $columns;
    }

    /** @return list<array{label: string, items: list<array{label: string, url: string}>}> */
    private static function footerFromRecords(): array
    {
        $columns = [];

        foreach (self::roots('footer') as $column) {
            $links = [];

            foreach ($column->children as $child) {
                if (! $child->is_visible) {
                    continue;
                }

                $url = $child->url();

                // An item whose destination has gone is skipped, not
                // rendered broken. A deleted page must not take the footer
                // down on every page of the site.
                if ($url === null) {
                    continue;
                }

                $links[] = ['label' => $child->labelFor(), 'url' => $url];
            }

            if ($links === []) {
                continue;
            }

            $columns[] = ['label' => $column->labelFor(), 'items' => $links];
        }

        return $columns;
    }

    /**
     * The floor, read from the same config the seeder materialises, so the
     * two cannot drift.
     *
     * @return list<array{label: string, items: list<array{label: string, url: string}>}>
     */
    private static function footerFromConfig(): array
    {
        $columns = [];

        foreach (config('navigation.footer', []) as $column) {
            $links = [];

            foreach ($column['links'] as $link) {
                $url = isset($link['url'])
                    ? self::resolve($link['url'])
                    : (Route::has($link['route']) ? route($link['route'], ['locale' => app()->getLocale()]) : null);

                if ($url === null) {
                    continue;
                }

                $links[] = [
                    'label' => $link['literal'] ?? __($link['label']),
                    'url' => $url,
                ];
            }

            if ($links !== []) {
                $columns[] = ['label' => __($column['heading']), 'items' => $links];
            }
        }

        return $columns;
    }

    /** @return list<array{key: string, label: string, url: string, current: bool}> */
    /**
     * The header, one level deep.
     *
     * A root item may carry children, which the header draws as a dropdown
     * and the drawer as an indented group. Screen 01 draws no dropdown: it
     * names --e-2 as the elevation for "Hover, dropdowns, sticky headers"
     * and stops there, so the elevation is the screen's and the rest is a
     * disclosed variation in docs/OPEN_ITEMS.md.
     *
     * A GROUP MAY HAVE NO DESTINATION OF ITS OWN. "About" opens a page and
     * "Projects" does not; a heading with children and no URL is a button
     * that opens its own list, and one with a URL is a link that also opens
     * it. Both are drawn, because the legacy site has both.
     *
     * A hidden item is absent, and so is a group whose children are all
     * hidden: Projects stays out of the header entirely until the legacy
     * import lands, which is one toggle rather than a deploy.
     */
    private static function fromRecords(string $menu): array
    {
        $items = [];

        foreach (self::roots($menu) as $item) {
            $url = $item->url();
            $children = [];

            foreach ($item->children as $child) {
                if (! $child->is_visible) {
                    continue;
                }

                $childUrl = $child->url();

                if ($childUrl === null) {
                    continue;
                }

                $children[] = [
                    'key' => (string) $child->id,
                    'label' => $child->labelFor(),
                    'url' => $childUrl,
                    'current' => self::isCurrentUrl($childUrl),
                ];
            }

            // A heading with nothing under it and nowhere of its own to go
            // is not navigation.
            if ($url === null && $children === []) {
                continue;
            }

            $items[] = [
                'key' => (string) $item->id,
                'label' => $item->labelFor(),
                'url' => $url,
                'children' => $children,
                'current' => ($url !== null && self::isCurrentUrl($url))
                    || collect($children)->contains('current', true),
            ];
        }

        return $items;
    }

    /** @return Collection<int, MenuItem> */
    private static function roots(string $menu)
    {
        if (! Schema::hasTable('menu_items')) {
            return collect();
        }

        return MenuItem::inMenu($menu)
            ->visible()
            ->whereNull('parent_id')
            ->with(['translations', 'children.translations'])
            ->orderBy('position')
            ->get();
    }

    /** @return list<array{key: string, label: string, url: string, current: bool}> */
    private static function fromConfig(): array
    {
        return array_map(
            fn (array $item) => [
                'key' => $item['key'],
                'label' => __('nav.'.$item['key']),
                'url' => self::url($item['path']),
                // The config fallback is flat. It is what a database with no
                // menu records renders, and a flat header is the right
                // floor for that.
                'children' => [],
                'current' => self::isCurrent($item['path']),
            ],
            config('navigation.primary', [])
        );
    }

    private static function isCurrentUrl(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $locale = app()->getLocale();

        return self::isCurrent($path === $locale ? '' : preg_replace('~^'.preg_quote($locale, '~').'/~', '', $path));
    }

    /** A path inside the language currently being rendered. */
    public static function url(string $path): string
    {
        $locale = app()->getLocale();

        return url($path === '' ? $locale : $locale.'/'.$path);
    }

    /**
     * Resolves a URL an editor stored on a block.
     *
     * An editor typing an internal destination writes "/publications". That
     * is the correct thing for them to type and the wrong thing to render:
     * without the language segment it 404s in every language including
     * English. Absolute URLs, mailto, tel and fragments pass through
     * untouched, because those are deliberate.
     */
    public static function resolve(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (preg_match('~^([a-z][a-z0-9+.-]*:|//|#)~i', $url)) {
            return $url;
        }

        $path = ltrim($url, '/');

        // Already carries a language segment, so leave it alone.
        foreach (Locales::SUPPORTED as $locale) {
            if ($path === $locale || str_starts_with($path, $locale.'/')) {
                return url($path);
            }
        }

        return self::url($path);
    }

    public static function isCurrent(string $path): bool
    {
        $locale = app()->getLocale();
        $current = trim(request()->path(), '/');

        if ($path === '') {
            return $current === $locale;
        }

        return $current === $locale.'/'.$path
            || str_starts_with($current, $locale.'/'.$path.'/');
    }
}
