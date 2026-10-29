<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Help per screen, keyed by route name (client brief, item 7).
 *
 * Every lang/{locale}/screenhelp_*.php file is read, so each part of the
 * platform keeps the help for its own screens in its own file. An entry is
 * ['title' => optional, 'purpose' => one sentence, 'actions' => [three]].
 * A screen with no entry gets its section's general help (the keys
 * _back_office and _member in screenhelp_core), never an empty page.
 */
final class ScreenHelp
{
    public const BACK_OFFICE = '_back_office';

    public const MEMBER = '_member';

    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $cache = [];

    /**
     * Every entry in one language, files merged in name order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function entries(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $entries = [];

        foreach (self::files() as $file) {
            $values = trans($file, [], $locale);

            if (is_array($values)) {
                foreach ($values as $route => $entry) {
                    if (is_array($entry) && filled($entry['purpose'] ?? null)) {
                        $entries[$route] = $entry;
                    }
                }
            }
        }

        return self::$cache[$locale] = $entries;
    }

    /**
     * The names of the help files, from every language folder.
     *
     * @return list<string>
     */
    public static function files(): array
    {
        $names = [];

        foreach (Locales::SUPPORTED as $locale) {
            foreach (File::glob(lang_path($locale.'/screenhelp_*.php')) ?: [] as $path) {
                $names[] = pathinfo($path, PATHINFO_FILENAME);
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /** Whether a screen has its own entry in this language. */
    public static function has(string $route, string $locale): bool
    {
        return isset(self::entries($locale)[$route]);
    }

    /** The section a screen belongs to: the back office or the member area. */
    public static function section(string $route): string
    {
        return str_starts_with($route, 'admin.') ? self::BACK_OFFICE : self::MEMBER;
    }

    /**
     * The help for one screen: its own entry, in this language or else in
     * English, and otherwise its section's.
     *
     * @return array{title: ?string, purpose: string, actions: list<string>, general: bool, section: string}
     */
    public static function for(string $route, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $section = self::section($route);

        $entry = self::entries($locale)[$route] ?? self::entries(Locales::FALLBACK)[$route] ?? null;
        $general = $entry === null;
        $entry ??= self::entries($locale)[$section] ?? self::entries(Locales::FALLBACK)[$section] ?? [];

        return [
            'title' => isset($entry['title']) && is_string($entry['title']) ? $entry['title'] : null,
            'purpose' => (string) ($entry['purpose'] ?? __('d3_notify.help_general_note')),
            'actions' => array_values(array_filter((array) ($entry['actions'] ?? []), 'is_string')),
            'general' => $general,
            'section' => $section,
        ];
    }

    public static function forget(): void
    {
        self::$cache = [];
    }
}
