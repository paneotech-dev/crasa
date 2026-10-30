<?php

namespace App\Services\MachineTranslation;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rafiki AI, the client's own service, through its OpenAI-compatible gateway:
 * POST {RAFIKI_API_URL}/chat/completions with a bearer key, one request per
 * field, the answer read from choices[0].message.content. The provider model
 * that answered comes back in the X-Rafiki-Model header and is kept for the
 * audit entry of the drafts.
 *
 * Models are tried in the configured order. The next one is tried only when
 * the current one fails on its own account: a server error, a timeout, a 400
 * or 404 that names the model, an upstream "model unavailable", or an answer
 * that adds or drops HTML tags. A model that failed that way is not asked
 * again for the rest of the item. A spent budget (429 budget_exceeded) stops
 * the item; a 413 skips the field.
 *
 * Inert until RAFIKI_API_KEY is set. The key, the request body and the text
 * of an error answer are never logged: only status, type, code and ref.
 */
final class RafikiTranslator implements Translator, ReportsModel
{
    public const DEFAULT_URL = 'https://gateway.rafikiai.io/v1';

    public const DEFAULT_MODELS = ['gpt-mini', 'gpt', 'mistral-small'];

    private const LANGUAGES = ['en' => 'English', 'fr' => 'French', 'pt' => 'European Portuguese (as written in Portugal)'];

    /** What to do after one request. */
    private const NEXT = 'next';

    private const STOP = 'stop';

    private const DONE = 'done';

    /** An answer shorter than this share of the source is treated as cut off. */
    private const MIN_RATIO = 0.3;

    /** Sources shorter than this are not checked for length (a title of two words). */
    private const MIN_CHECKED_LENGTH = 20;

    private const MIN_TOKENS = 256;

    private const MAX_TOKENS = 8000;

    private bool $halted = false;

    /** @var list<string> models not to ask again for this item */
    private array $failed = [];

    /** @var list<string> */
    private array $models = [];

    private ?string $fallback = null;

    /** @var list<string> */
    private readonly array $order;

    /**
     * @param  array<int, mixed>  $models  model ids in the order to try; blanks and refused names are skipped
     * @param  array<string, array<string, string>>  $glossary  locale => [English term => term]
     */
    public function __construct(
        private readonly ?string $key,
        private readonly ?string $url = null,
        array $models = [],
        private readonly int $timeout = 30,
        private readonly array $glossary = [],
    ) {
        $this->order = self::usableModels($models) ?: self::DEFAULT_MODELS;
    }

    public function name(): string
    {
        return 'rafiki';
    }

    public function available(): bool
    {
        return is_string($this->key) && trim($this->key) !== '';
    }

    /** @return list<string> the models in the order they are tried */
    public function order(): array
    {
        return $this->order;
    }

    public function translate(string $text, string $from, string $to, bool $html = false): ?string
    {
        if (! $this->available() || $this->halted || ! isset(self::LANGUAGES[$from], self::LANGUAGES[$to]) || $from === $to || trim($text) === '') {
            return null;
        }

        $instruction = $this->instruction($text, $from, $to);

        foreach ($this->order as $model) {
            if (in_array($model, $this->failed, true)) {
                continue;
            }

            [$action, $value] = $this->ask($model, $instruction, $text);

            if ($action === self::DONE) {
                return $value;
            }

            if ($action === self::STOP) {
                return null;
            }

            $this->failed[] = $model;
        }

        // Every model failed on its own account: the rest of the item would too.
        $this->halted = true;

        return null;
    }

    /** The models that answered so far, as "rafiki:<model>", for the audit entry. */
    public function models(): array
    {
        return $this->models;
    }

    /** The X-Rafiki-Fallback header of the last answer that carried one. */
    public function fallback(): ?string
    {
        return $this->fallback;
    }

    /** The system message for one field, with the glossary entries found in it. */
    public function instruction(string $text, string $from, string $to): string
    {
        $terms = $this->glossaryFor($text, $from, $to);
        $entries = array_map(
            fn (string $term, string $translation) => '"'.$term.'" = "'.$translation.'"',
            array_keys($terms),
            array_values($terms),
        );

        return 'You translate official text of CRASA, a regional association of communications regulators, from '
            .self::LANGUAGES[$from].' to '.self::LANGUAGES[$to].'. '
            .'Keep every HTML tag and attribute exactly as in the source; add no tags. '
            .'Keep acronyms such as CRASA and SADC, and all numbers and dates. '
            .($entries !== [] ? 'Use this glossary: '.implode('; ', $entries).'. ' : '')
            .'Return only the translation, nothing else.';
    }

    /** Four times the input, at about three characters a token, within 256 and 8000. */
    public static function maxTokens(string $text): int
    {
        return max(self::MIN_TOKENS, min(self::MAX_TOKENS, 4 * (int) ceil(mb_strlen($text) / 3)));
    }

    /**
     * Opening tag names of an HTML fragment, sorted, so two fragments with the
     * same tags in any order compare equal.
     *
     * @return list<string>
     */
    public static function tagNames(string $html): array
    {
        preg_match_all('/<\s*([a-zA-Z][a-zA-Z0-9-]*)\b[^>]*>/', $html, $matches);
        $names = array_map('strtolower', $matches[1]);
        sort($names);

        return $names;
    }

    /**
     * One request to one model.
     *
     * @return array{0: string, 1: ?string} [DONE, translation] or [NEXT|STOP, null]
     */
    private function ask(string $model, string $instruction, string $text): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withToken(trim((string) $this->key))
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint(), array_filter([
                    'model' => $model,
                    // The gateway pins the temperature of claude models.
                    'temperature' => str_starts_with(strtolower($model), 'claude') ? null : 0,
                    'max_tokens' => self::maxTokens($text),
                    'messages' => [
                        ['role' => 'system', 'content' => $instruction],
                        ['role' => 'user', 'content' => $text],
                    ],
                ], fn ($value) => $value !== null));
        } catch (Throwable $e) {
            Log::warning('Machine translation failed.', ['driver' => 'rafiki', 'model' => $model, 'error' => $this->redact($e->getMessage())]);

            return [self::NEXT, null];
        }

        if (! $response->successful()) {
            return [$this->refused($response, $model), null];
        }

        $value = $response->json('choices.0.message.content');
        $value = is_string($value) ? trim($value) : '';

        if ($value === '' || $this->tooShort($text, $value)) {
            Log::warning('Machine translation answer rejected.', ['driver' => 'rafiki', 'model' => $model, 'reason' => $value === '' ? 'empty' : 'too short']);

            return [self::STOP, null];
        }

        if ($text !== strip_tags($text) && self::tagNames($text) !== self::tagNames($value)) {
            Log::warning('Machine translation answer rejected.', ['driver' => 'rafiki', 'model' => $model, 'reason' => 'tags changed']);

            return [self::NEXT, null];
        }

        $answered = trim((string) $response->header('X-Rafiki-Model'));
        $answered = 'rafiki:'.($answered !== '' ? $answered : $model);

        if (! in_array($answered, $this->models, true)) {
            $this->models[] = $answered;
        }

        $fallback = trim((string) $response->header('X-Rafiki-Fallback'));

        if ($fallback !== '') {
            $this->fallback = $fallback;
        }

        return [self::DONE, $value];
    }

    /**
     * Logs status, type, code and ref of a non-2xx answer, never its text,
     * and says whether to try the next model.
     */
    private function refused(Response $response, string $model): string
    {
        $status = $response->status();
        $error = $response->json('error');
        $type = is_array($error) ? ($error['type'] ?? null) : null;
        $code = is_array($error) ? ($error['code'] ?? null) : null;
        $ref = is_array($error) ? ($error['ref'] ?? $response->json('ref')) : $response->json('ref');
        $message = is_array($error) ? ($error['message'] ?? null) : $error;

        $context = array_filter([
            'driver' => 'rafiki',
            'model' => $model,
            'status' => $status,
            'type' => is_scalar($type) ? (string) $type : null,
            'code' => is_scalar($code) ? (string) $code : null,
            'ref' => is_scalar($ref) ? (string) $ref : null,
        ], fn ($value) => $value !== null);

        if ($status === 429 && ($type === 'budget_exceeded' || $code === 'budget_exceeded')) {
            $this->halted = true;
            Log::warning('Machine translation budget spent: the Rafiki AI key has no budget left this month.', $context);

            return self::STOP;
        }

        Log::warning('Machine translation refused.', $context);

        if ($status === 413) {
            return self::STOP;
        }

        // Read, never logged: whether the refusal is about this model.
        $about = strtolower(implode(' ', array_map(fn ($part) => is_scalar($part) ? (string) $part : '', [$type, $code, $message])));
        $namesModel = str_contains($about, strtolower($model)) || str_contains($about, 'model_not_found') || str_contains($about, 'model not found');
        $unavailable = str_contains($about, 'model') && (str_contains($about, 'unavailable') || str_contains($about, 'not available'));

        if ($status >= 500 || (in_array($status, [400, 404], true) && $namesModel) || $unavailable) {
            return self::NEXT;
        }

        return self::STOP;
    }

    /**
     * Glossary entries found in the text: English term => target term, or
     * source term => English term when translating to English.
     *
     * @return array<string, string>
     */
    private function glossaryFor(string $text, string $from, string $to): array
    {
        $pairs = [];

        if ($from === 'en') {
            $pairs = (array) ($this->glossary[$to] ?? []);
        } elseif ($to === 'en') {
            $pairs = array_flip((array) ($this->glossary[$from] ?? []));
        } else {
            // French to Portuguese or back: pair the two lists through the English term.
            foreach ((array) ($this->glossary[$from] ?? []) as $english => $source) {
                if (isset($this->glossary[$to][$english])) {
                    $pairs[$source] = $this->glossary[$to][$english];
                }
            }
        }

        $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_filter(
            $pairs,
            // Whole words, case-insensitive, plural allowed: "ITU" is not found in "institution".
            fn ($translation, $term) => is_string($translation) && is_string($term) && trim($term) !== ''
                && preg_match('/(?<![\p{L}\p{N}])'.preg_quote(trim($term), '/').'(?:s|es)?(?![\p{L}\p{N}])/iu', $plain) === 1,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<int, mixed>  $models
     * @return list<string>
     */
    private static function usableModels(array $models): array
    {
        $usable = [];

        foreach ($models as $model) {
            $model = trim((string) $model);
            $lower = strtolower($model);

            // "auto" and the rafiki/ tier and task routes are refused or add text of their own.
            if ($model === '' || $lower === 'auto' || str_starts_with($lower, 'rafiki/') || in_array($model, $usable, true)) {
                continue;
            }

            $usable[] = $model;
        }

        return $usable;
    }

    private function endpoint(): string
    {
        $url = trim((string) $this->url);

        return rtrim($url !== '' ? $url : self::DEFAULT_URL, '/').'/chat/completions';
    }

    private function tooShort(string $source, string $answer): bool
    {
        $sourceLength = mb_strlen(trim(strip_tags($source)));

        if ($sourceLength < self::MIN_CHECKED_LENGTH) {
            return false;
        }

        return mb_strlen(trim(strip_tags($answer))) < $sourceLength * self::MIN_RATIO;
    }

    /** An exception message could echo a header; the key never reaches the log. */
    private function redact(string $message): string
    {
        $key = trim((string) $this->key);

        return $key === '' ? $message : str_replace($key, '[redacted]', $message);
    }
}
