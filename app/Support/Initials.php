<?php

namespace App\Support;

/**
 * The two letters in the identity chip in screen 09.
 *
 * It is decorative: the chip is aria-hidden and the name is beside it in
 * full, so a name this cannot reduce sensibly costs nothing.
 */
final class Initials
{
    public static function of(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
