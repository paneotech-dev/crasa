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
     * and needs only RAFIKI_API_KEY; the URL defaults to the gateway.
     * RAFIKI_MODELS is the order of catalogue model ids to try: the next one
     * is tried only after a server error, a timeout, a refusal that names the
     * model or an answer that adds or drops HTML tags. RAFIKI_MODEL, when
     * set, is tried first. Tested live with the glossary: gpt-mini, gpt and
     * claude-sonnet translate correctly; mistral-small once added a tag.
     * Do not use "auto", the rafiki/ tier names or rafiki/task:translation
     * (refused, or it appends its own sentence); they are skipped here. The
     * glossary is in config/machine_translation_glossary.php. Keep the
     * timeout well under the gateway's 100 second limit.
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
        'model' => env('RAFIKI_MODEL', ''),
        'models' => array_values(array_filter(array_map('trim', explode(',', (string) env('RAFIKI_MODELS', 'gpt-mini,gpt,mistral-small'))))),
        'timeout' => (int) env('RAFIKI_TIMEOUT', 30),
    ],
];
