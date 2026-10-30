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
     *
     * "rafiki" sends the approved text to the client's Rafiki AI gateway
     * (OpenAI-compatible POST {RAFIKI_API_URL}/chat/completions, bearer key)
     * and needs only RAFIKI_API_KEY; the URL defaults to the gateway. The
     * model is a catalogue id: "mistral-small" is cheap and good in French
     * and Portuguese. "qwen" is Rafiki's router preference for translation
     * but costs about ten times as much; "deepseek" is another cheap option.
     * The rafiki/ tier names and "auto" are refused by the gateway with a
     * general key ("auto" is replaced by the default here). Keep the timeout
     * well under the gateway's 100 second limit. Nothing is sent without a key.
     */
    'driver' => env('CRASA_MT_DRIVER', 'none'),

    'deepl' => [
        'key' => env('DEEPL_API_KEY'),
        'url' => env('DEEPL_API_URL'),
        'timeout' => (int) env('DEEPL_TIMEOUT', 20),
    ],

    'rafiki' => [
        'url' => env('RAFIKI_API_URL', 'https://gateway.rafikiai.io/v1'),
        'key' => env('RAFIKI_API_KEY'),
        'model' => env('RAFIKI_MODEL', 'mistral-small'),
        'timeout' => (int) env('RAFIKI_TIMEOUT', 30),
    ],
];
