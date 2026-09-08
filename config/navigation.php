<?php

/*
 * The primary navigation, defined once. The header and the drawer read this
 * list, so the two never drift apart.
 *
 * `path` is appended to the current language segment. Paths are not yet
 * localised; translated slugs are a later decision and will not change the
 * shape of this list.
 */

return [
    'primary' => [
        ['key' => 'home', 'path' => ''],
        ['key' => 'about', 'path' => 'about'],
        ['key' => 'committees', 'path' => 'committees'],
        ['key' => 'members', 'path' => 'members'],
        ['key' => 'publications', 'path' => 'publications'],
        ['key' => 'newsroom', 'path' => 'newsroom'],
        ['key' => 'events', 'path' => 'events'],
        ['key' => 'comparator', 'path' => 'comparator'],
        ['key' => 'contact', 'path' => 'contact'],
    ],

    'extranet_path' => 'extranet',
    'search_path' => 'search',
];
