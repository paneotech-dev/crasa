<?php

namespace App\Support;

use App\Models\Committee;

/**
 * CRASA's thematic areas (30 October 2026): the list in config/themes.php,
 * the words in lang/{en,fr,pt}/d3_themes.php, and the Bootstrap Icons
 * drawn inline from public/vendor/bootstrap-icons (the CSP allows self
 * only, and an inline SVG needs no request at all).
 */
class Themes
{
    /** @var array<string, string> */
    private static array $svg = [];

    /**
     * Every theme, in the configured order, with its words in the reader's
     * language.
     *
     * @return array<string, array{slug: string, icon: string, title: string, summary: string, body: array<int, string>, committees: array<int, string>, sector: ?string}>
     */
    public static function all(): array
    {
        $themes = [];
        foreach (config('themes', []) as $slug => $theme) {
            $themes[$slug] = [
                'slug' => $slug,
                'icon' => $theme['icon'],
                'title' => __("d3_themes.themes.$slug.title"),
                'summary' => __("d3_themes.themes.$slug.summary"),
                'body' => (array) __("d3_themes.themes.$slug.body"),
                'committees' => $theme['committees'] ?? [],
                'sector' => $theme['sector'] ?? null,
            ];
        }

        return $themes;
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /**
     * The active committees a theme links to, by name.
     *
     * @return \Illuminate\Support\Collection<int, Committee>
     */
    public static function committees(array $theme)
    {
        if ($theme['committees'] === []) {
            return collect();
        }

        return Committee::where('is_active', true)->whereIn('slug', $theme['committees'])->orderBy('name')->get();
    }

    /**
     * Where "Partner with us on this theme" goes: the Partner with us page
     * record (slug partner-with-us) with ?theme=<slug>, while that page is
     * published; until then the contact page, so the button never leads to
     * a 404.
     */
    public static function partnerUrl(string $slug): string
    {
        $locale = app()->getLocale();
        $published = \App\Models\Page::published()->where('slug', 'partner-with-us')->exists();

        return $published
            ? url($locale.'/partner-with-us').'?theme='.rawurlencode($slug)
            : route('contact', ['locale' => $locale]).'?theme='.rawurlencode($slug);
    }

    /**
     * A Bootstrap Icon as inline SVG, hidden from assistive technology:
     * the text beside it names the theme.
     */
    public static function icon(string $name): \Illuminate\Support\HtmlString
    {
        // Read from a vendored file, never from stored content: safe to print
        // with {{ }} as an HtmlString.
        if (! preg_match('/^[a-z0-9-]+$/', $name)) {
            return new \Illuminate\Support\HtmlString('');
        }

        return new \Illuminate\Support\HtmlString(self::$svg[$name] ??= (function () use ($name) {
            $path = public_path("vendor/bootstrap-icons/icons/$name.svg");
            if (! is_file($path)) {
                return '';
            }
            $svg = trim((string) file_get_contents($path));

            return preg_replace('/^<svg\b[^>]*>/', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false" class="th-svg">', $svg);
        })());
    }
}
