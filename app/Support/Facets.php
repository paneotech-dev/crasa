<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Reading a multi-select facet out of a query string.
 *
 * Screen 07 draws every facet as a list of checkboxes, so a facet holds a
 * set rather than a value: ?type[]=guideline&type[]=study. A single scalar
 * still works, because a hand-typed ?type=guideline and every link written
 * before 9 September 2026 must keep resolving.
 */
final class Facets
{
    /**
     * The selected values of one facet, as a clean list of strings.
     *
     * Empty strings are dropped rather than kept as a filter nothing
     * matches, duplicates are removed, and the list is capped so a crafted
     * query string cannot turn one page load into a thousand counts.
     *
     * @return list<string>
     */
    public static function values(Request $request, string $key, int $max = 30): array
    {
        $raw = $request->input($key, []);

        if (! is_array($raw)) {
            $raw = [$raw];
        }

        $values = [];

        foreach ($raw as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '' && ! in_array($value, $values, true)) {
                $values[] = $value;
            }

            if (count($values) >= $max) {
                break;
            }
        }

        return $values;
    }

    /**
     * The query string with one value added to or removed from a facet.
     *
     * This is what a checkbox does, written as a URL, so the control works
     * as a link before any script runs and as a form control after it.
     *
     * @param  array<string, list<string>>  $filters
     * @return array<string, mixed>
     */
    public static function toggled(array $filters, string $key, string $value): array
    {
        $current = $filters[$key] ?? [];

        $next = in_array($value, $current, true)
            ? array_values(array_diff($current, [$value]))
            : [...$current, $value];

        return self::query(array_merge($filters, [$key => $next]));
    }

    /**
     * A filter set as query parameters. Empty facets leave the URL rather
     * than sitting in it as ?type= — a reader pastes these into email.
     *
     * @param  array<string, list<string>>  $filters
     * @return array<string, mixed>
     */
    public static function query(array $filters): array
    {
        $query = ['page' => null];

        foreach ($filters as $key => $values) {
            $query[$key] = $values === [] ? null : array_values($values);
        }

        return $query;
    }
}
