<?php

namespace App\Support;

/**
 * The difference between two versions of a regulatory text.
 *
 * WORD BY WORD, NOT LINE BY LINE. A line diff on prose marks a whole
 * paragraph changed because one date moved in it, and the reader is left to
 * find the date. Regulatory text is what this platform is for, and "what
 * exactly changed in clause 4" is the question a regulator asks.
 *
 * The algorithm is the longest common subsequence, computed over words. It
 * is O(n*m) in time and memory, which is why the input is capped: two
 * versions of a document that long are compared by opening both, and a diff
 * that takes a gigabyte to draw is not a diff anybody sees.
 *
 * Nothing here is coloured. Screen 05's translation editor marks a changed
 * cell with a border and a word, not with a fill, and colour alone would
 * fail SC 1.4.1: an addition carries a + and a removal a -, read out by the
 * screen reader either way.
 */
final class TextDiff
{
    /** Beyond this the pair is reported as too long to compare. */
    public const MAX_WORDS = 4000;

    /**
     * One list of segments: each is kept, added or removed.
     *
     * @return array{segments: list<array{type: string, text: string}>, added: int, removed: int, truncated: bool}
     */
    public static function between(?string $before, ?string $after): array
    {
        $left = self::words((string) $before);
        $right = self::words((string) $after);

        if (count($left) > self::MAX_WORDS || count($right) > self::MAX_WORDS) {
            return ['segments' => [], 'added' => 0, 'removed' => 0, 'truncated' => true];
        }

        $segments = self::walk($left, $right, self::table($left, $right));

        return [
            'segments' => $segments,
            'added' => self::count($segments, 'added'),
            'removed' => self::count($segments, 'removed'),
            'truncated' => false,
        ];
    }

    /**
     * Split on whitespace, keeping the whitespace with the word before it,
     * so joining the segments reproduces the text exactly.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        preg_match_all('/\S+\s*/u', $text, $matches);

        return $matches[0];
    }

    /**
     * The longest-common-subsequence table.
     *
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @return list<list<int>>
     */
    private static function table(array $left, array $right): array
    {
        $rows = count($left);
        $columns = count($right);

        $table = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));

        for ($i = $rows - 1; $i >= 0; $i--) {
            for ($j = $columns - 1; $j >= 0; $j--) {
                $table[$i][$j] = $left[$i] === $right[$j]
                    ? $table[$i + 1][$j + 1] + 1
                    : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        return $table;
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @param  list<list<int>>  $table
     * @return list<array{type: string, text: string}>
     */
    private static function walk(array $left, array $right, array $table): array
    {
        $segments = [];
        $i = 0;
        $j = 0;
        $rows = count($left);
        $columns = count($right);

        while ($i < $rows && $j < $columns) {
            if ($left[$i] === $right[$j]) {
                self::push($segments, 'kept', $left[$i]);
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                self::push($segments, 'removed', $left[$i]);
                $i++;
            } else {
                self::push($segments, 'added', $right[$j]);
                $j++;
            }
        }

        while ($i < $rows) {
            self::push($segments, 'removed', $left[$i++]);
        }

        while ($j < $columns) {
            self::push($segments, 'added', $right[$j++]);
        }

        return $segments;
    }

    /**
     * Runs, not words. Six consecutive added words are one addition to a
     * reader and six marks on the page to nobody's benefit.
     *
     * @param  list<array{type: string, text: string}>  $segments
     */
    private static function push(array &$segments, string $type, string $text): void
    {
        $last = count($segments) - 1;

        if ($last >= 0 && $segments[$last]['type'] === $type) {
            $segments[$last]['text'] .= $text;

            return;
        }

        $segments[] = ['type' => $type, 'text' => $text];
    }

    /** @param list<array{type: string, text: string}> $segments */
    private static function count(array $segments, string $type): int
    {
        $words = 0;

        foreach ($segments as $segment) {
            if ($segment['type'] === $type) {
                $words += count(preg_split('/\s+/u', trim($segment['text'])) ?: []);
            }
        }

        return $words;
    }
}
