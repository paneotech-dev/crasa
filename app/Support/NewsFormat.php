<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Small formatting rules shared by the newsroom listing and the article page.
 */
final class NewsFormat
{
    /** Excerpt length on a card, before the three-line clamp in CSS. */
    public const EXCERPT_LENGTH = 180;

    /**
     * The long date in the page language: 4 November 2025, 4 novembre 2025,
     * 4 de novembro de 2025.
     */
    public static function longDate(?CarbonInterface $date, ?string $locale = null): ?string
    {
        if ($date === null) {
            return null;
        }

        $locale ??= app()->getLocale();
        $date = $date->copy()->locale($locale);

        return $locale === 'pt'
            ? $date->translatedFormat('j \d\e F \d\e Y')
            : $date->translatedFormat('j F Y');
    }

    /**
     * Cut at a word boundary, never inside a word, and end with an ellipsis
     * when anything was cut.
     */
    public static function excerpt(?string $text, int $limit = self::EXCERPT_LENGTH): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)) ?? '');

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit + 1);
        $space = mb_strrpos($cut, ' ');
        $cut = $space !== false && $space > $limit / 2 ? mb_substr($cut, 0, $space) : mb_substr($text, 0, $limit);

        return rtrim($cut, " \t,;:.-").'…';
    }

    /** Static placeholder for an item without its own photograph, by type. */
    public static function placeholder(string $category): string
    {
        $known = ['news', 'press_release', 'tender', 'consultation', 'communique'];

        return '/img/newsroom/placeholder-'.str_replace('_', '-', in_array($category, $known, true) ? $category : 'news').'.png';
    }
}
