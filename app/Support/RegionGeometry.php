<?php

namespace App\Support;

/**
 * Geometry helpers for the region map.
 *
 * The map is one Mercator projection fitted to the region, which is correct
 * and means the smallest member states are genuinely tiny. At 375px, Lesotho
 * and Eswatini are a few pixels across: too small to read a figure off, too
 * small to aim a thumb at, and too small to tell apart.
 *
 * Comoros and Mauritius already have their own enlarged panels for the same
 * reason. This computes the equivalent for any member state from the shape
 * the map already draws, so the enlargement is the same Natural Earth
 * geometry at a different scale rather than a second drawing of a country.
 * Nothing here invents a coordinate.
 */
final class RegionGeometry
{
    /**
     * The member states small enough to need their own panel.
     *
     * Comoros and Mauritius are islands and have hand-prepared inset paths
     * in the config. Lesotho and Eswatini are mainland and keep their place
     * on the map; they get a panel as well because at phone width the map
     * cannot answer for them.
     */
    public const ENLARGED = ['km', 'mu', 'ls', 'sz'];

    /**
     * A viewBox that frames one path, with a little air around it.
     *
     * Parses the coordinate pairs out of the path and takes their extent.
     * The map's paths are absolute line and move commands with no curves, so
     * every number in them is a coordinate; a path with arcs would need a
     * real parser and the geometry has none.
     */
    public static function viewBoxFor(string $path, float $padding = 4.0): ?string
    {
        preg_match_all('/-?\d+(?:\.\d+)?/', $path, $matches);

        $numbers = array_map('floatval', $matches[0]);

        if (count($numbers) < 4) {
            return null;
        }

        $xs = [];
        $ys = [];

        foreach ($numbers as $index => $number) {
            $index % 2 === 0 ? $xs[] = $number : $ys[] = $number;
        }

        $minX = min($xs) - $padding;
        $minY = min($ys) - $padding;
        $width = (max($xs) - min($xs)) + ($padding * 2);
        $height = (max($ys) - min($ys)) + ($padding * 2);

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return sprintf('%.2f %.2f %.2f %.2f', $minX, $minY, $width, $height);
    }
}
