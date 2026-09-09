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
    'showing' => 'Showing :first to :last of :total',
    'previous_page' => 'Previous page, page :page',
    'next_page' => 'Next page, page :page',
    'go_to_page' => 'Go to page :page',
    'current_page' => 'Page :page, current page',
];
