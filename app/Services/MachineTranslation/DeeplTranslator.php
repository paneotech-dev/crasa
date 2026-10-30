<?php

namespace App\Services\MachineTranslation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DeepL (https://developers.deepl.com/docs/api-reference/translate).
 * Portuguese is drafted as European Portuguese (PT-PT), the variant the
 * Lusophone member states read. Inert until DEEPL_API_KEY is set.
 */
final class DeeplTranslator implements Translator
{
    private const TARGETS = ['en' => 'EN-GB', 'fr' => 'FR', 'pt' => 'PT-PT'];

    public function __construct(
        private readonly ?string $key,
        private readonly ?string $url = null,
        private readonly int $timeout = 20,
    ) {}

    public function name(): string
    {
        return 'deepl';
    }

    public function available(): bool
    {
        return is_string($this->key) && trim($this->key) !== '';
    }

    public function translate(string $text, string $from, string $to, bool $html = false): ?string
    {
        if (! $this->available() || ! isset(self::TARGETS[$to]) || trim($text) === '') {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Authorization' => 'DeepL-Auth-Key '.$this->key])
                ->asJson()
                ->post($this->endpoint(), array_filter([
                    'text' => [$text],
                    'source_lang' => strtoupper($from),
                    'target_lang' => self::TARGETS[$to],
                    'tag_handling' => $html ? 'html' : null,
                ]));
        } catch (Throwable $e) {
            Log::warning('Machine translation failed.', ['driver' => 'deepl', 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Machine translation refused.', ['driver' => 'deepl', 'status' => $response->status()]);

            return null;
        }

        $value = $response->json('translations.0.text');

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function endpoint(): string
    {
        if (is_string($this->url) && $this->url !== '') {
            return $this->url;
        }

        return str_ends_with((string) $this->key, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';
    }
}
