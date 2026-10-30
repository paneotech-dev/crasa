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
 * Inert until RAFIKI_API_KEY is set. The key, the request body and the text
 * of an error answer are never logged: only status, type, code and ref.
 *
 * After a spent budget (429 budget_exceeded), a server error or a timeout,
 * the remaining fields of the same item are not sent: they would fail the
 * same way and the queued job has a 180 second limit.
 */
final class RafikiTranslator implements Translator, ReportsModel
{
    public const DEFAULT_URL = 'https://gateway.rafikiai.io/v1';

    public const DEFAULT_MODEL = 'mistral-small';

    private const LANGUAGES = ['en' => 'English', 'fr' => 'French', 'pt' => 'European Portuguese (as written in Portugal)'];

    /** Model names the gateway rejects with a general key (the rafiki/ tier names too). */
    private const REFUSED_MODELS = ['auto'];

    /** An answer shorter than this share of the source is treated as cut off. */
    private const MIN_RATIO = 0.3;

    /** Sources shorter than this are not checked for length (a title of two words). */
    private const MIN_CHECKED_LENGTH = 20;

    private const MIN_TOKENS = 256;

    private const MAX_TOKENS = 8000;

    private bool $halted = false;

    /** @var list<string> */
    private array $models = [];

    private ?string $fallback = null;

    public function __construct(
        private readonly ?string $key,
        private readonly ?string $url = null,
        private readonly ?string $model = null,
        private readonly int $timeout = 30,
    ) {}

    public function name(): string
    {
        return 'rafiki';
    }

    public function available(): bool
    {
        return is_string($this->key) && trim($this->key) !== '';
    }

    public function translate(string $text, string $from, string $to, bool $html = false): ?string
    {
        if (! $this->available() || $this->halted || ! isset(self::LANGUAGES[$from], self::LANGUAGES[$to]) || $from === $to || trim($text) === '') {
            return null;
        }

        $model = $this->requestedModel();

        try {
            $response = Http::timeout($this->timeout)
                ->withToken(trim((string) $this->key))
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint(), [
                    'model' => $model,
                    'temperature' => 0,
                    'max_tokens' => self::maxTokens($text),
                    'messages' => [
                        ['role' => 'system', 'content' => self::instruction($from, $to)],
                        ['role' => 'user', 'content' => $text],
                    ],
                ]);
        } catch (Throwable $e) {
            $this->halted = true;
            Log::warning('Machine translation failed.', ['driver' => 'rafiki', 'error' => $this->redact($e->getMessage())]);

            return null;
        }

        if (! $response->successful()) {
            $this->refused($response);

            return null;
        }

        $value = $response->json('choices.0.message.content');
        $value = is_string($value) ? trim($value) : '';

        if ($value === '' || $this->tooShort($text, $value)) {
            Log::warning('Machine translation answer rejected.', ['driver' => 'rafiki', 'reason' => $value === '' ? 'empty' : 'too short']);

            return null;
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

        return $value;
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

    /** The system message sent with every request. */
    public static function instruction(string $from, string $to): string
    {
        return 'Translate the user message from '.self::LANGUAGES[$from].' to '.self::LANGUAGES[$to].'. '
            .'Keep every HTML tag and attribute exactly as it is and translate only the text between tags. '
            .'Keep proper nouns, acronyms (such as CRASA, SADC, ITU and the names of national regulators) and numbers unchanged. '
            .'Return only the translation, with no explanation, note or quotation marks.';
    }

    /** Four times the input, at about three characters a token, within 256 and 8000. */
    public static function maxTokens(string $text): int
    {
        return max(self::MIN_TOKENS, min(self::MAX_TOKENS, 4 * (int) ceil(mb_strlen($text) / 3)));
    }

    private function requestedModel(): string
    {
        $model = trim((string) $this->model);

        $refused = in_array(strtolower($model), self::REFUSED_MODELS, true) || str_starts_with(strtolower($model), 'rafiki/');

        return $model === '' || $refused ? self::DEFAULT_MODEL : $model;
    }

    private function endpoint(): string
    {
        $url = trim((string) $this->url);

        return rtrim($url !== '' ? $url : self::DEFAULT_URL, '/').'/chat/completions';
    }

    /** Logs status, type, code and ref of a non-2xx answer, never its text. */
    private function refused(Response $response): void
    {
        $status = $response->status();
        $error = $response->json('error');
        $type = is_array($error) ? ($error['type'] ?? null) : null;
        $code = is_array($error) ? ($error['code'] ?? null) : null;
        $ref = is_array($error) ? ($error['ref'] ?? $response->json('ref')) : $response->json('ref');

        $context = array_filter([
            'driver' => 'rafiki',
            'status' => $status,
            'type' => is_scalar($type) ? (string) $type : null,
            'code' => is_scalar($code) ? (string) $code : null,
            'ref' => is_scalar($ref) ? (string) $ref : null,
        ], fn ($value) => $value !== null);

        if ($status === 429 && ($type === 'budget_exceeded' || $code === 'budget_exceeded')) {
            $this->halted = true;
            Log::warning('Machine translation budget spent: the Rafiki AI key has no budget left this month.', $context);

            return;
        }

        if ($status >= 500) {
            $this->halted = true;
        }

        // 413 context_length_exceeded and other refusals skip this field only.
        Log::warning('Machine translation refused.', $context);
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
