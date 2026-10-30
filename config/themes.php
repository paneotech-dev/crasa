<?php

/*
 * CRASA's thematic areas (30 October 2026).
 *
 * Drawn from what the site already records: the sectors, the committees
 * and their mandates, and the subjects of the publications library. The
 * site has no content type that carries an icon and a body per subject
 * (tags are bare labels), so the list lives here and the words in
 * lang/{en,fr,pt}/d3_themes.php, under themes.<slug>.
 *
 * icon: a Bootstrap Icons name, vendored under public/vendor/bootstrap-icons.
 * committees: committee slugs whose public page is linked, when active.
 * sector: a sector slug, linked as a filter on the publications library.
 */

return [
    'spectrum-management' => [
        'icon' => 'broadcast-pin',
        'committees' => ['spectrum-and-frequency-management'],
        'sector' => 'telecommunications',
    ],
    'broadband-universal-access' => [
        'icon' => 'wifi',
        'committees' => [],
        'sector' => 'telecommunications',
    ],
    'cybersecurity' => [
        'icon' => 'shield-lock',
        'committees' => [],
        'sector' => null,
    ],
    'consumer-protection' => [
        'icon' => 'person-check',
        'committees' => ['consumer-affairs-and-protection'],
        'sector' => null,
    ],
    'broadcasting' => [
        'icon' => 'tv',
        'committees' => [],
        'sector' => 'broadcasting',
    ],
    'postal-services' => [
        'icon' => 'envelope-paper',
        'committees' => ['postal-and-logistics'],
        'sector' => 'postal',
    ],
    'numbering-roaming' => [
        'icon' => 'sim',
        'committees' => ['economics'],
        'sector' => 'telecommunications',
    ],
    'digital-inclusion' => [
        'icon' => 'universal-access-circle',
        'committees' => [],
        'sector' => null,
    ],
    'emerging-technologies' => [
        'icon' => 'cpu',
        'committees' => [],
        'sector' => null,
    ],
    'regional-harmonisation' => [
        'icon' => 'globe-europe-africa',
        'committees' => ['legal-and-regulatory-affairs'],
        'sector' => null,
    ],
];
