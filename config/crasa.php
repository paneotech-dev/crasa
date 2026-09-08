<?php

return [
    /*
     * Where website enquiries are sent. The Secretariat address from the
     * approved screens; override in .env when the Secretariat names a
     * dedicated inbox.
     */
    'enquiry_recipient' => env('CRASA_ENQUIRY_RECIPIENT', 'crasa@crasa.org'),

    /*
     * Search.
     *
     * ElasticSearch is named in the specifications and cannot run on this
     * host: no JVM, shared cPanel hosting. MariaDB InnoDB FULLTEXT ships
     * behind App\Search\SearchDriver, so a managed ElasticSearch or
     * OpenSearch service later is a driver class and this config line.
     */
    'search' => [
        'driver' => env('CRASA_SEARCH_DRIVER', 'database'),
        'per_page' => 12,
    ],

    /*
     * The Secretariat's location, for the contact map. Plot 143, Unit 2,
     * Gaborone International Finance Park.
     *
     * These coordinates are approximate: they place the pin in the Gaborone
     * International Finance Park, not on the specific unit, which no source
     * available to this build gives. Confirm with the Secretariat before
     * go-live.
     */
    'map' => [
        'latitude' => env('CRASA_MAP_LAT', -24.6282),
        'longitude' => env('CRASA_MAP_LNG', 25.9231),
        'zoom' => 15,

        /*
         * Map tiles are a third-party request: loading them sends every
         * visitor's IP address to the tile server. The map is therefore
         * click-to-load, so nothing leaves the platform unless the reader
         * asks for it, and the address is always readable as text without
         * the map at all.
         */
        'tile_url' => env('CRASA_MAP_TILES', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'tile_attribution' => '&copy; OpenStreetMap contributors',
    ],
];
