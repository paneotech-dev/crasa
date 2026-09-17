<?php

namespace Tests\Feature;

use App\Support\Qa\SiteCrawler;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * qa:crawl, against a small fake site, so each failure rule is pinned.
 */
class QaCrawlTest extends TestCase
{
    private const BASE = 'https://site.test';

    private function page(string $body): array
    {
        return ['<html><body>'.$body.'</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']];
    }

    /** @param array<string, array{0: string, 1: int, 2?: array<string, string>}> $site */
    private function fakeSite(array $site): void
    {
        Http::fake(function (Request $request) use ($site) {
            $path = substr($request->url(), strlen(self::BASE)) ?: '/';
            [$body, $status, $headers] = ($site[$path] ?? ['not found', 404]) + [2 => ['Content-Type' => 'text/html']];

            return Http::response($request->method() === 'HEAD' ? '' : $body, $status, $headers);
        });
    }

    public function test_a_clean_site_passes_and_every_linked_page_is_visited(): void
    {
        $this->fakeSite([
            '/en' => $this->page('<a href="/en/about">About</a><a href="https://site.test/en/news#top">News</a><img src="/img/logo.svg">'),
            '/en/about' => $this->page('<a href="/en">Home</a>'),
            '/en/news' => $this->page('<a href="/en/documents/4/download">PDF</a>'),
            '/img/logo.svg' => ['<svg/>', 200, ['Content-Type' => 'image/svg+xml']],
            '/en/documents/4/download' => ['%PDF', 200, ['Content-Type' => 'application/pdf']],
        ]);

        $result = (new SiteCrawler(self::BASE))->crawl(false);

        $this->assertSame(['/en', '/en/about', '/en/news'], array_column($result['pages'], 'url'));
        $this->assertSame([], $result['failures']);
        $this->assertSame(['/img/logo.svg' => 200, '/en/documents/4/download' => 200], $result['assets']);
    }

    public function test_a_server_error_a_broken_link_a_trace_and_broken_files_are_failures(): void
    {
        $this->fakeSite([
            '/en' => $this->page('<a href="/en/broken">x</a><a href="/en/gone">y</a><a href="/en/debug">z</a>'
                .'<img src="/img/missing.jpg" srcset="/img/missing-480.webp 480w"><a href="/storage/documents/lost.pdf">f</a>'),
            '/en/broken' => ['oops', 500],
            '/en/debug' => $this->page('<pre>Stack trace: #0 vendor/laravel/framework/src</pre>'),
        ]);

        $failures = collect((new SiteCrawler(self::BASE))->crawl(false)['failures'])
            ->map(fn ($f) => $f['kind'].' '.$f['url'])->sort()->values()->all();

        $this->assertSame([
            'broken asset /img/missing-480.webp',
            'broken asset /img/missing.jpg',
            'broken download /storage/documents/lost.pdf',
            'broken link /en/gone',
            'debug trace in page /en/debug',
            'status 500 /en/broken',
        ], $failures);
    }

    public function test_it_never_leaves_the_site_or_follows_sign_out(): void
    {
        $this->fakeSite([
            '/en' => $this->page('<a href="https://elsewhere.test/en/x">out</a><a href="mailto:a@b.c">m</a>'
                .'<a href="/en/logout">bye</a><a href="/admin/raw">no locale</a><a href="#main">skip</a>'),
        ]);

        $result = (new SiteCrawler(self::BASE))->crawl(false);

        $this->assertSame(['/en'], array_column($result['pages'], 'url'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'logout') || str_contains($r->url(), 'elsewhere'));
    }

    public function test_a_refused_page_is_recorded_not_failed(): void
    {
        $this->fakeSite(['/en' => $this->page('<a href="/en/secret">s</a>'), '/en/secret' => ['no', 403]]);

        $result = (new SiteCrawler(self::BASE))->crawl(false);

        $this->assertSame(403, $result['pages'][1]['status']);
        $this->assertSame([], $result['failures']);
    }

    public function test_sign_in_answers_the_two_factor_challenge_with_the_current_code(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $form = fn () => Http::response('<form><input type="hidden" name="_token" value="tok123"></form>', 200, ['Content-Type' => 'text/html']);

        Http::fake(function (Request $request) use ($form, $secret) {
            return match (true) {
                $request->method() === 'GET' => $form(),
                str_ends_with($request->url(), '/en/login') => Http::response('', 302, ['Location' => self::BASE.'/en/two-factor/challenge']),
                str_ends_with($request->url(), '/two-factor/challenge') && $request['code'] === (new Google2FA)->getCurrentOtp($secret)
                    => Http::response('', 302, ['Location' => self::BASE.'/en/admin']),
                default => Http::response('', 302, ['Location' => self::BASE.'/en/two-factor/challenge']),
            };
        });

        $landed = (new SiteCrawler(self::BASE))->signIn('admin@example.org', 'secret', $secret);

        $this->assertSame('/en/admin', $landed);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && ($r->data()['email'] ?? null) === 'admin@example.org' && $r['_token'] === 'tok123');
    }

    public function test_a_failed_sign_in_is_reported_and_the_command_fails(): void
    {
        Http::fake(fn (Request $r) => $r->method() === 'GET'
            ? Http::response('<input type="hidden" name="_token" value="t">', 200, ['Content-Type' => 'text/html'])
            : Http::response('', 302, ['Location' => self::BASE.'/en/login']));

        $file = tempnam(sys_get_temp_dir(), 'acc');
        file_put_contents($file, "viewer someone@example.org wrong-password\nCRASA demonstration accounts\n");

        $this->artisan('qa:crawl', ['--base' => self::BASE, '--accounts' => $file, '--only' => 'someone@example.org'])
            ->expectsOutputToContain('Sign-in failed for someone@example.org')
            ->assertFailed();

        unlink($file);
    }

    public function test_the_command_passes_on_a_clean_site(): void
    {
        $this->fakeSite(['/en' => $this->page('<a href="/en">Home</a>')]);

        $this->artisan('qa:crawl', ['--base' => self::BASE, '--locales' => 'en'])
            ->expectsOutputToContain('No failures.')
            ->assertSuccessful();
    }
}
