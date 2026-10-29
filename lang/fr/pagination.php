<?php

/*
 * Pagination, on this platform own components.
 *
 * Laravel default view is the Tailwind one and Tailwind is not on this
 * platform, so the views are published under resources/views/vendor.
 * These are their strings.
 *
 * Every control carries a real accessible name: "Next" alone says nothing
 * about what is being paged, and a numbered link with no context is a
 * number read aloud.
 */

return [
    'label' => 'Pagination',
    'showing' => 'Résultats :first à :last sur :total',
    'previous_page' => 'Page précédente, page :page',
    'next_page' => 'Page suivante, page :page',
    'go_to_page' => 'Aller à la page :page',
    'current_page' => 'Page :page, page actuelle',

    // Laravel's own pagination views, should one be used.
    'previous' => '&laquo; Précédent',
    'next' => 'Suivant &raquo;',
];
