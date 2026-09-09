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
    'label' => 'Paginação',
    'showing' => 'A mostrar :first a :last de :total',
    'previous_page' => 'Página anterior, página :page',
    'next_page' => 'Página seguinte, página :page',
    'go_to_page' => 'Ir para a página :page',
    'current_page' => 'Página :page, página atual',
];
