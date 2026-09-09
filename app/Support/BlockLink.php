<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Where a block's link goes.
 *
 * Blocks are edited by the Secretariat, and a free-text URL field in a
 * content editor produces two failures on a platform like this one: links
 * that 404 after a page is renamed, and links that leave the platform
 * without saying so. Both are worse here than on an ordinary site, because
 * "every download comes from the platform itself" is a claim this platform
 * makes.
 *
 * So a block link is a named destination from a fixed list, resolved in the
 * reader's own language at render time. A name that is not on the list
 * resolves to null and the block renders as plain text: a figure that is
 * not a link is a smaller failure than a figure that is a broken one.
 */
final class BlockLink
{
    /** @var array<string, string> destination name => route name */
    private const DESTINATIONS = [
        'members' => 'members',
        'about' => 'about',
        'committees' => 'committees',
        'publications' => 'publications',
        'comparator' => 'comparator',
        'newsroom' => 'newsroom',
        'events' => 'events',
        'completeness' => 'completeness',
        'contact' => 'contact',
    ];

    /** @return array<int, string> */
    public static function names(): array
    {
        return array_keys(self::DESTINATIONS);
    }

    public static function resolve(?string $name): ?string
    {
        if (! filled($name) || ! isset(self::DESTINATIONS[$name])) {
            return null;
        }

        $route = self::DESTINATIONS[$name];

        return Route::has($route)
            ? route($route, ['locale' => app()->getLocale()])
            : null;
    }
}
