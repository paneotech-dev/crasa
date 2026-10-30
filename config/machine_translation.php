<?php

return [
    /*
     * The service that drafts French, Portuguese or English when an item is
     * approved in one language (item 1b). "none" drafts nothing: the hook
     * still runs on approval and records that no service is configured.
     *
     * "deepl" sends the approved text to DeepL and needs DEEPL_API_KEY. A
     * free-plan key ends in ":fx" and uses api-free.deepl.com; a paid key
     * uses api.deepl.com. Nothing is sent anywhere without a key.
     */
    'driver' => env('CRASA_MT_DRIVER', 'none'),

    'deepl' => [
        'key' => env('DEEPL_API_KEY'),
        'url' => env('DEEPL_API_URL'),
        'timeout' => (int) env('DEEPL_TIMEOUT', 20),
    ],
];
