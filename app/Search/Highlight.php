<?php

namespace App\Search;

/**
 * Marks searched terms in result text, ignoring accents.
 *
 * MariaDB matches with an accent-insensitive collation, so "itinerance"
 * finds "itinérance". Highlighting used a plain case-insensitive regex, so
 * the same result came back with nothing marked: a French or Portuguese
 * reader typing without accents saw results with no reason given. Each
 * letter of a term now also matches its accented forms, in both directions.
 */
final class Highlight
{
    /** Base letter and the accented forms it stands for. */
    private const FORMS = [
        'a' => 'aàáâãäåā',
        'c' => 'cçć',
        'e' => 'eèéêëē',
        'i' => 'iìíîïī',
        'n' => 'nñ',
        'o' => 'oòóôõöø',
        'u' => 'uùúûüū',
        'y' => 'yýÿ',
    ];

    /**
     * The regex for one term, every letter widened to its accented forms.
     * The term is taken as given: escape it first when the subject is
     * escaped HTML.
     */
    public static function pattern(string $term): string
    {
        $out = '';

        foreach (mb_str_split(mb_strtolower($term)) as $char) {
            $base = self::base($char);
            $out .= $base === null ? preg_quote($char, '/') : '['.self::FORMS[$base].']';
        }

        return $out;
    }

    /**
     * Wraps every occurrence of the terms in <mark>. The subject must already
     * be escaped; the terms are escaped here the same way so they line up.
     *
     * @param  list<string>  $terms
     */
    public static function mark(string $escaped, array $terms): string
    {
        $parts = array_map(fn (string $t) => self::pattern(e($t)), array_values(array_filter($terms, fn ($t) => $t !== '')));

        if ($parts === []) {
            return $escaped;
        }

        return preg_replace('/('.implode('|', $parts).')/iu', '<mark>$1</mark>', $escaped) ?? $escaped;
    }

    /**
     * Character offset of the first term found in plain text, or null.
     *
     * @param  list<string>  $terms
     */
    public static function position(string $text, array $terms): ?int
    {
        foreach ($terms as $term) {
            if ($term !== '' && preg_match('/'.self::pattern($term).'/iu', $text, $m, PREG_OFFSET_CAPTURE) === 1) {
                return mb_strlen(substr($text, 0, $m[0][1]));
            }
        }

        return null;
    }

    private static function base(string $char): ?string
    {
        foreach (self::FORMS as $base => $forms) {
            if (mb_strpos($forms, $char) !== false) {
                return $base;
            }
        }

        return null;
    }
}
