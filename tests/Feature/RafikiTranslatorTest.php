<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\NewsItem;
use App\Models\Translation;
use App\Models\User;
use App\Services\MachineTranslation\MachineDrafts;
use App\Services\MachineTranslation\NoTranslator;
use App\Services\MachineTranslation\RafikiTranslator;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The Rafiki AI driver: inert until RAFIKI_API_KEY is set, then one
 * OpenAI-compatible chat completion per field on the Rafiki gateway. No real
 * service is called here; every request goes to Http::fake.
 */
class RafikiTranslatorTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-rafiki-secret-test-key-123';

    private const GATEWAY = 'https://gateway.rafikiai.io/v1/chat/completions';

    private function configure(?string $key = self::KEY, ?string $url = null, ?string $model = null, array $models = ['gpt-mini', 'gpt', 'mistral-small']): void
    {
        config([
            'machine_translation.driver' => 'rafiki',
            'machine_translation.rafiki.key' => $key,
            'machine_translation.rafiki.url' => $url,
            'machine_translation.rafiki.model' => $model,
            'machine_translation.rafiki.models' => $models,
            'machine_translation.rafiki.timeout' => 30,
        ]);
    }

    private static function refusal(int $status, string $message, string $type, string $code): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['error' => ['message' => $message, 'type' => $type, 'code' => $code]], $status);
    }

    /** @return list<string> the model of every request sent, in order */
    private function modelsSent(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]['model'])->values()->all();
    }

    private static function answer(string $content, array $headers = []): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content]]]], 200, $headers);
    }

    private function englishOnlyNews(): NewsItem
    {
        $news = NewsItem::factory()->create(['slug' => 'rafiki-spectrum-workshop']);
        $news->setTranslations('en', [
            'title' => 'Spectrum workshop held in Lilongwe',
            'summary' => 'Regulators met to align spectrum plans.',
            'body' => '<p>Fourteen regulators attended.</p>',
        ]);

        return $news;
    }

    private function row(NewsItem $news, string $locale, string $field): ?Translation
    {
        return Translation::where('translatable_type', $news->getMorphClass())
            ->where('translatable_id', $news->id)->where('locale', $locale)->where('field', $field)->first();
    }

    /** Every log call, JSON-encoded, collected through Log::spy. */
    private function captureLogs(): \ArrayObject
    {
        $logged = new \ArrayObject;
        Log::spy();

        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'] as $level) {
            Log::shouldReceive($level)->andReturnUsing(function (...$args) use ($logged) {
                $logged[] = json_encode($args);
            });
        }

        return $logged;
    }

    public function test_the_default_driver_stays_none_and_the_defaults_need_only_a_key(): void
    {
        $this->assertSame('none', config('machine_translation.driver'));
        $this->assertInstanceOf(NoTranslator::class, MachineDrafts::translator());
        $this->assertSame('https://gateway.rafikiai.io/v1', config('machine_translation.rafiki.url'));
        $this->assertSame('', config('machine_translation.rafiki.model'));
        $this->assertSame(['gpt-mini', 'gpt', 'mistral-small'], config('machine_translation.rafiki.models'));
        $this->assertSame(30, config('machine_translation.rafiki.timeout'));
    }

    public function test_nothing_is_sent_without_a_key(): void
    {
        Http::fake();

        foreach ([null, '', '   '] as $key) {
            $this->configure($key);

            $this->assertInstanceOf(RafikiTranslator::class, MachineDrafts::translator());
            $this->assertFalse(MachineDrafts::configured());
            $this->assertNull(MachineDrafts::translator()->translate('Hello', 'en', 'fr'));
        }

        Http::assertNothingSent();
    }

    public function test_an_unconfigured_driver_records_the_skip_like_deepl(): void
    {
        Http::fake();
        $this->configure(null);
        $news = $this->englishOnlyNews();

        $this->assertSame([], MachineDrafts::draft($news));
        $this->assertDatabaseHas('audit_log', ['action' => 'machine_translation_skipped', 'auditable_id' => $news->id]);
        Http::assertNothingSent();
    }

    public function test_the_request_is_a_chat_completion_with_the_bearer_key_the_model_and_the_instruction(): void
    {
        $answer = '<p class="lead">Catorze <a href="/en/members">reguladores</a> participaram.</p>';
        Http::fake(['gateway.rafikiai.io/*' => self::answer($answer)]);
        $this->configure();

        $html = '<p class="lead">Fourteen <a href="/en/members">regulators</a> attended.</p>';
        $this->assertSame($answer, MachineDrafts::translator()->translate($html, 'en', 'pt', true));

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($html) {
            $system = $request['messages'][0]['content'] ?? '';

            return $request->method() === 'POST'
                && $request->url() === self::GATEWAY
                && $request->hasHeader('Authorization', 'Bearer '.self::KEY)
                && $request->hasHeader('Content-Type', 'application/json')
                && $request['model'] === 'gpt-mini'
                && $request['temperature'] === 0
                && $request['max_tokens'] === 256
                && $request['messages'][0]['role'] === 'system'
                && str_starts_with($system, 'You translate official text of CRASA, a regional association of communications regulators, from English to European Portuguese')
                && str_contains($system, 'Keep every HTML tag and attribute exactly as in the source; add no tags.')
                && str_contains($system, 'Keep acronyms such as CRASA and SADC, and all numbers and dates.')
                && ! str_contains($system, 'glossary')
                && str_ends_with($system, 'Return only the translation, nothing else.')
                && $request['messages'][1] === ['role' => 'user', 'content' => $html]
                && array_keys($request->data()) === ['model', 'temperature', 'max_tokens', 'messages'];
        });
    }

    public function test_the_url_and_model_can_be_set_and_refused_model_names_are_never_sent(): void
    {
        Http::fake(['rafiki.test/*' => self::answer('  Bonjour  ')]);

        $this->configure(self::KEY, 'https://rafiki.test/v1/', 'qwen');
        $this->assertSame('Bonjour', MachineDrafts::translator()->translate('Hello', 'en', 'fr'));

        $this->configure(self::KEY, 'https://rafiki.test/v1', 'auto');
        MachineDrafts::translator()->translate('Hello', 'en', 'fr');

        $this->configure(self::KEY, 'https://rafiki.test/v1', 'rafiki/task:translation');
        MachineDrafts::translator()->translate('Hello', 'en', 'fr');

        $models = [];
        Http::assertSent(function ($request) use (&$models) {
            $models[] = $request['model'];

            return $request->url() === 'https://rafiki.test/v1/chat/completions'
                && str_contains($request['messages'][0]['content'], 'from English to French');
        });
        $this->assertSame(['qwen', 'gpt-mini', 'gpt-mini'], $models);
    }

    public function test_max_tokens_is_four_times_the_input_within_bounds(): void
    {
        $this->assertSame(256, RafikiTranslator::maxTokens('Hello'));
        $this->assertSame(4000, RafikiTranslator::maxTokens(str_repeat('a', 3000)));
        $this->assertSame(8000, RafikiTranslator::maxTokens(str_repeat('a', 30000)));
    }

    public function test_an_answer_becomes_a_machine_draft_with_the_answering_model_in_the_audit_entry(): void
    {
        Http::fake(['gateway.rafikiai.io/*' => function ($request) {
            $text = $request['messages'][1]['content'];
            $prefix = str_contains($request['messages'][0]['content'], 'to French') ? 'FR: ' : 'PT: ';

            return self::answer($prefix.$text, ['X-Rafiki-Model' => 'mistral-small-2503', 'X-Rafiki-Request-Id' => 'req-123']);
        }]);
        $this->configure();
        $news = $this->englishOnlyNews();

        $drafted = MachineDrafts::draft($news);

        $this->assertSame(['title', 'summary', 'body'], $drafted['fr']);
        $this->assertSame(['title', 'summary', 'body'], $drafted['pt']);
        Http::assertSentCount(6);

        $this->assertSame('FR: Spectrum workshop held in Lilongwe', $this->row($news, 'fr', 'title')->value);
        $this->assertTrue($this->row($news, 'fr', 'title')->is_machine_draft);
        $this->assertSame('PT: <p>Fourteen regulators attended.</p>', $this->row($news, 'pt', 'body')->value);

        $entry = AuditLog::where('action', 'machine_translation_drafted')->where('auditable_id', $news->id)->sole();
        $this->assertStringContainsString('rafiki:mistral-small-2503', json_encode($entry->toArray()));
        $this->assertStringNotContainsString('req-123', json_encode($entry->toArray()));
    }

    public function test_without_the_model_header_the_requested_model_is_recorded_with_any_fallback(): void
    {
        Http::fake(['gateway.rafikiai.io/*' => self::answer('Traduit', ['X-Rafiki-Fallback' => 'deepseek'])]);
        $this->configure();
        $news = NewsItem::factory()->create();
        $news->setTranslations('en', ['title' => 'Workshop']);

        MachineDrafts::draft($news);

        $entry = json_encode(AuditLog::where('action', 'machine_translation_drafted')->where('auditable_id', $news->id)->sole()->toArray());
        $this->assertStringContainsString('rafiki:gpt-mini', $entry);
        $this->assertStringContainsString('deepseek', $entry);
    }

    public function test_an_empty_cut_off_or_refused_answer_writes_nothing(): void
    {
        $this->configure();
        $news = $this->englishOnlyNews();

        Http::fake(['gateway.rafikiai.io/*' => Http::sequence()
            ->pushResponse(self::answer('   '))->pushResponse(self::answer('   '))->pushResponse(self::answer('   '))
            ->pushResponse(self::answer('   '))->pushResponse(self::answer('   '))->pushResponse(self::answer('   '))
            ->pushResponse(self::answer('Atelier'))
            ->pushResponse(Http::response(['choices' => []]))
            ->pushResponse(Http::response(['error' => ['message' => 'too long', 'type' => 'invalid_request_error', 'code' => 'context_length_exceeded']], 413))]);

        $this->assertSame([], MachineDrafts::draft($news));
        $this->assertNull($this->row($news, 'fr', 'title'));
        Http::assertSentCount(6);

        $this->assertNull(MachineDrafts::translator()->translate('Spectrum workshop held in Lilongwe on Tuesday', 'en', 'fr'));
        $this->assertNull(MachineDrafts::translator()->translate('Hello', 'en', 'fr'));
        $this->assertNull(MachineDrafts::translator()->translate('Hello', 'en', 'fr'));
        Http::assertSentCount(9);

        $this->assertNull($this->row($news, 'fr', 'title'));
        $this->assertNull($this->row($news, 'pt', 'title'));
    }

    public function test_a_413_skips_only_that_field(): void
    {
        Http::fake(['gateway.rafikiai.io/*' => function ($request) {
            return str_contains($request['messages'][1]['content'], '<p>')
                ? Http::response(['error' => ['message' => 'too long', 'type' => 'invalid_request_error', 'code' => 'context_length_exceeded']], 413)
                : self::answer('Traduit: '.$request['messages'][1]['content']);
        }]);
        $this->configure();
        $news = $this->englishOnlyNews();

        $drafted = MachineDrafts::draft($news);

        $this->assertSame(['title', 'summary'], $drafted['fr']);
        $this->assertNull($this->row($news, 'fr', 'body'));
    }

    public function test_a_spent_budget_is_logged_once_and_nothing_more_is_sent(): void
    {
        $logged = $this->captureLogs();
        Http::fake(['gateway.rafikiai.io/*' => Http::response(['error' => ['message' => 'Monthly budget of key sk-... exceeded', 'type' => 'budget_exceeded', 'code' => 'budget_exceeded'], 'ref' => 'r-42'], 429)]);
        $this->configure();
        $news = $this->englishOnlyNews();

        $this->assertSame([], MachineDrafts::draft($news));

        Http::assertSentCount(1);
        $this->assertCount(1, $logged);
        $this->assertStringContainsString('budget_exceeded', $logged[0]);
        $this->assertStringContainsString('r-42', $logged[0]);
        $this->assertStringNotContainsString('Monthly budget', $logged[0], 'The error text is not logged.');
        $this->assertNull($this->row($news, 'fr', 'title'));
    }

    public function test_a_server_error_or_timeout_drafts_nothing_and_stops_the_item(): void
    {
        $this->configure();
        $news = $this->englishOnlyNews();

        Http::fake(['gateway.rafikiai.io/*' => Http::sequence()
            ->pushResponse(Http::response(['error' => 'upstream', 'ref' => 'r-1'], 502))
            ->pushFailedConnection('cURL error 28: Operation timed out')
            ->pushResponse(Http::response(['error' => 'upstream', 'ref' => 'r-2'], 503))]);

        // Each model is tried once; when all fail, the rest of the item is not sent.
        $this->assertSame([], MachineDrafts::draft($news));
        $this->assertSame(['gpt-mini', 'gpt', 'mistral-small'], $this->modelsSent());
        $this->assertNull($this->row($news, 'fr', 'title'));
    }

    public function test_the_key_never_reaches_the_log(): void
    {
        $logged = $this->captureLogs();
        $this->configure(self::KEY, null, null, ['gpt-mini']);

        Http::fake(['gateway.rafikiai.io/*' => Http::sequence()
            ->pushResponse(Http::response(['error' => ['message' => 'bad key '.self::KEY, 'type' => 'authentication_error', 'code' => 'invalid_api_key', 'ref' => 'r-9']], 401))
            ->pushResponse(self::answer(''))
            ->pushFailedConnection('cURL error 28: timed out, header Authorization: Bearer '.self::KEY)]);

        // A fresh translator for each call: a timeout stops the rest of an item.
        $this->assertNull(MachineDrafts::translator()->translate('Hello', 'en', 'fr'));
        $this->assertNull(MachineDrafts::translator()->translate('Hello', 'en', 'fr'));
        $this->assertNull(MachineDrafts::translator()->translate('Hello', 'en', 'fr'));

        $this->assertCount(3, $logged);
        Log::shouldHaveReceived('warning')->times(3);
        $all = implode("\n", $logged->getArrayCopy());
        $this->assertStringNotContainsString(self::KEY, $all);
        $this->assertStringNotContainsString('Return only the translation', $all, 'The request is not logged.');
        $this->assertStringContainsString('timed out', $all, 'The exception message is logged.');
        $this->assertStringContainsString('invalid_api_key', $all);
        $this->assertStringContainsString('r-9', $all);
    }

    public function test_the_next_model_is_tried_after_a_server_error_or_a_refusal_naming_the_model(): void
    {
        Http::fake(['gateway.rafikiai.io/*' => function ($request) {
            return match ($request['model']) {
                'gpt-mini' => Http::response(['error' => 'upstream', 'ref' => 'r-1'], 503),
                'gpt' => self::refusal(404, 'The model gpt does not exist', 'invalid_request_error', 'model_not_found'),
                default => self::answer('Traduit: '.$request['messages'][1]['content'], ['X-Rafiki-Model' => 'mistral-small-2503']),
            };
        }]);
        $this->configure();
        $news = $this->englishOnlyNews();

        $drafted = MachineDrafts::draft($news);

        $this->assertSame(['title', 'summary', 'body'], $drafted['fr']);
        // The two failed models are not asked again for the other five fields.
        $this->assertSame(['gpt-mini', 'gpt', 'mistral-small', 'mistral-small', 'mistral-small', 'mistral-small', 'mistral-small', 'mistral-small'], $this->modelsSent());
        $entry = json_encode(AuditLog::where('action', 'machine_translation_drafted')->where('auditable_id', $news->id)->sole()->toArray());
        $this->assertStringContainsString('rafiki:mistral-small-2503', $entry);
    }

    public function test_an_upstream_model_unavailable_error_tries_the_next_model(): void
    {
        Http::fake(['gateway.rafikiai.io/*' => Http::sequence()
            ->pushResponse(self::refusal(422, 'Upstream model is unavailable', 'upstream_error', 'model_unavailable'))
            ->pushResponse(self::answer('Bonjour'))]);
        $this->configure();

        $this->assertSame('Bonjour', MachineDrafts::translator()->translate('Hello', 'en', 'fr'));
        $this->assertSame(['gpt-mini', 'gpt'], $this->modelsSent());
    }

    public function test_no_other_model_is_tried_after_a_spent_budget_a_413_or_another_refusal(): void
    {
        $this->configure();
        $refusal = null;
        Http::fake(['gateway.rafikiai.io/*' => function ($request) use (&$refusal) {
            return $request['model'] === 'gpt-mini' ? $refusal : self::answer('Bonjour');
        }]);

        foreach ([
            self::refusal(429, 'Monthly budget exceeded', 'budget_exceeded', 'budget_exceeded'),
            self::refusal(413, 'Too long', 'invalid_request_error', 'context_length_exceeded'),
            self::refusal(400, 'max_tokens is too large', 'invalid_request_error', 'invalid_value'),
            self::refusal(401, 'Bad key', 'authentication_error', 'invalid_api_key'),
        ] as $i => $response) {
            $refusal = $response;

            $this->assertNull(MachineDrafts::translator()->translate('Hello', 'en', 'fr'));
            $this->assertSame(array_fill(0, $i + 1, 'gpt-mini'), $this->modelsSent());
        }
    }

    public function test_an_answer_that_adds_or_drops_tags_tries_the_next_model(): void
    {
        $html = '<p>The <strong>annual return</strong> is due on 31 March.</p>';
        $good = '<p>La <strong>déclaration annuelle</strong> est due le 31 mars.</p>';
        $this->configure();

        Http::fake(['gateway.rafikiai.io/*' => Http::sequence()
            ->pushResponse(self::answer('<p>La <strong>déclaration annuelle</strong> est due le 31<sup>er</sup> mars.</p>'))
            ->pushResponse(self::answer('<p>La déclaration annuelle est due le 31 mars.</p>'))
            ->pushResponse(self::answer($good))]);

        $this->assertSame($good, MachineDrafts::translator()->translate($html, 'en', 'fr', true));
        $this->assertSame(['gpt-mini', 'gpt', 'mistral-small'], $this->modelsSent());

        $this->assertSame(['p', 'strong'], RafikiTranslator::tagNames('<P class="x"><strong>a</strong></p>'));
        $this->assertSame(['br', 'p', 'p'], RafikiTranslator::tagNames('<p>a<br/></p><p>b</p>'));
    }

    public function test_claude_models_are_sent_without_a_temperature(): void
    {
        Http::fake(['gateway.rafikiai.io/*' => self::answer('Bonjour')]);
        $this->configure(self::KEY, null, 'claude-sonnet');

        $this->assertSame('Bonjour', MachineDrafts::translator()->translate('Hello', 'en', 'fr'));

        Http::assertSent(fn ($request) => $request['model'] === 'claude-sonnet'
            && ! array_key_exists('temperature', $request->data()));
    }

    public function test_the_glossary_sends_only_the_terms_found_in_the_text(): void
    {
        $this->assertSame('déclaration annuelle', config('machine_translation_glossary.fr')['annual return']);
        $this->assertSame('Estado-Membro', config('machine_translation_glossary.pt')['member state']);
        $this->assertCount(8, config('machine_translation_glossary.fr'));
        $this->assertCount(8, config('machine_translation_glossary.pt'));

        Http::fake(['gateway.rafikiai.io/*' => self::answer('Traduction de test assez longue')]);
        $this->configure();

        MachineDrafts::translator()->translate('Each member State files its Annual Return with the Secretariat by the institution deadline.', 'en', 'fr');
        MachineDrafts::translator()->translate('Os Estados-Membros e o Secretariado.', 'pt', 'en');
        MachineDrafts::translator()->translate('La déclaration annuelle du Secrétariat.', 'fr', 'pt');

        $systems = Http::recorded()->map(fn ($pair) => $pair[0]['messages'][0]['content'])->values();

        $this->assertStringContainsString('Use this glossary: "annual return" = "déclaration annuelle"; "member state" = "État membre"; "Secretariat" = "Secrétariat".', $systems[0]);
        $this->assertStringNotContainsString('UIT', $systems[0], 'ITU is not found inside "institution".');
        $this->assertStringContainsString('"Secretariado" = "Secretariat"', $systems[1]);
        $this->assertStringContainsString('"déclaration annuelle" = "declaração anual"', $systems[2]);
        $this->assertStringContainsString('"Secrétariat" = "Secretariado"', $systems[2]);
    }

    public function test_the_back_office_notice_names_the_missing_settings(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        config(['crasa.two_factor_required' => false]);
        $admin = User::factory()->administrator()->create();

        $this->configure(null);
        $this->actingAs($admin)->get('/en/admin/translations')->assertOk()
            ->assertSee('data-mt-unconfigured', false)
            ->assertSee('Selected service: rafiki. Missing settings in the .env file: RAFIKI_API_KEY.');
        $this->actingAs($admin)->get('/fr/admin/translations')->assertOk()
            ->assertSee('Paramètres manquants dans le fichier .env : RAFIKI_API_KEY.');

        config(['machine_translation.driver' => 'none']);
        $this->actingAs($admin)->get('/pt/admin/translations')->assertOk()
            ->assertSee('Defina no ficheiro .env CRASA_MT_DRIVER');

        config(['machine_translation.driver' => 'deepl', 'machine_translation.deepl.key' => null]);
        $this->assertSame(['DEEPL_API_KEY'], MachineDrafts::missingSettings());

        $this->configure();
        $this->assertSame([], MachineDrafts::missingSettings());
        $this->actingAs($admin)->get('/en/admin/translations')->assertOk()->assertDontSee('data-mt-unconfigured', false);
    }
}
