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

    /*
     * The footer's four link columns.
     *
     * This is the default structure, not the live one: MenuSeeder
     * materialises it into menu_items records and the Secretariat edits it
     * from the back office. It stays here as the floor, for the same reason
     * the primary list does — a fresh install, a bad import or a purge that
     * went further than intended should leave a working footer rather than
     * an empty one.
     *
     * `label` is a translation key. `route` is a named route. `url` is
     * anything else, which covers the section fragments and the social
     * links: a route plus a fragment is not a route.
     */
    'footer' => [
        [
            'heading' => 'footer.about',
            'links' => [
                ['route' => 'about', 'label' => 'footer.who_we_are'],
                ['url' => '/about#governance', 'label' => 'footer.governance'],
                ['url' => '/about#executive-committee', 'label' => 'footer.executive_committee'],
                ['url' => '/about#secretariat', 'label' => 'footer.secretariat'],
                ['url' => '/about#partnerships', 'label' => 'footer.partnerships'],
            ],
        ],
        [
            'heading' => 'footer.publications',
            'links' => [
                ['route' => 'publications', 'label' => 'footer.regulatory_library'],
                ['route' => 'comparator', 'label' => 'nav.comparator'],
                ['route' => 'committees', 'label' => 'nav.committees'],
                ['route' => 'members', 'label' => 'nav.members'],
            ],
        ],
        [
            'heading' => 'footer.newsroom',
            'links' => [
                ['route' => 'newsroom', 'label' => 'nav.newsroom'],
                ['route' => 'events', 'label' => 'nav.events'],
                ['route' => 'contact', 'label' => 'nav.contact'],
                ['route' => 'extranet', 'label' => 'nav.extranet'],
            ],
        ],
        [
            'heading' => 'footer.follow_us',
            'links' => [
                ['url' => 'https://x.com/Crasa2C', 'literal' => 'X'],
                ['url' => 'https://facebook.com/pages/CRASA', 'literal' => 'Facebook'],
                ['url' => 'https://www.linkedin.com/company/crasa', 'literal' => 'LinkedIn'],
            ],
        ],
    ],

    'extranet_path' => 'extranet',
    'search_path' => 'search',
];
