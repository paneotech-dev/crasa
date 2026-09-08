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
