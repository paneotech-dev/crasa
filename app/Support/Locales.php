<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The three interface languages, and the rules for choosing between them.
 *
 * English is the fallback throughout the platform. An item may publish in
 * English only; requiring all three would block all publishing.
 */
final class Locales
{
    public const FALLBACK = 'en';

    /** @var list<string> */
    public const SUPPORTED = ['en', 'fr', 'pt'];

    /**
     * Short code and endonym for each language. The switcher shows the code;
     * the endonym is the accessible name, so the control is never a flag alone.
     *
     * @return array<string, array{short: string, native: string}>
     */
    public static function all(): array
    {
        return [
            'en' => ['short' => 'EN', 'native' => 'English'],
            'fr' => ['short' => 'FR', 'native' => 'Français'],
            'pt' => ['short' => 'PT', 'native' => 'Português'],
        ];
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::SUPPORTED, true);
    }

    public static function short(string $locale): string
    {
        return self::all()[$locale]['short'] ?? strtoupper($locale);
    }

    public static function native(string $locale): string
    {
        return self::all()[$locale]['native'] ?? $locale;
    }

    /**
     * Choose a language for a request that does not name one: the language
     * last chosen, then the browser's preference, then English.
     */
    public static function detect(Request $request): string
    {
        $remembered = $request->hasSession() ? $request->session()->get('locale') : null;

        if (self::isSupported($remembered)) {
            return $remembered;
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED);

        return self::isSupported($preferred) ? $preferred : self::FALLBACK;
    }

    /**
     * The current path rendered in another language. Used by the switcher, so
     * changing language keeps the reader on the page they were reading.
     */
    public static function urlFor(string $locale, Request $request): string
    {
        $segments = $request->segments();

        if ($segments !== [] && self::isSupported($segments[0])) {
            $segments[0] = $locale;
        } else {
            array_unshift($segments, $locale);
        }

        $path = '/'.implode('/', $segments);
        $query = $request->getQueryString();

        return $request->getSchemeAndHttpHost().$path.($query !== null ? '?'.$query : '');
    }
}
