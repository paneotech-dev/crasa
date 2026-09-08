<?php

namespace App\Support;

/**
 * Builds the primary navigation for the language currently being rendered.
 */
final class Navigation
{
    /**
     * @return list<array{key: string, label: string, url: string, current: bool}>
     */
    public static function primary(): array
    {
        return array_map(
            fn (array $item) => [
                'key' => $item['key'],
                'label' => __('nav.'.$item['key']),
                'url' => self::url($item['path']),
                'current' => self::isCurrent($item['path']),
            ],
            config('navigation.primary', [])
        );
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
