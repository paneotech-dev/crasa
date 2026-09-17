<?php

namespace App\Support\Qa;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Throwable;

/**
 * Walks the running site over HTTP, as a visitor or as a signed-in account,
 * and records what a person following every link would hit.
 *
 * Black box on purpose: it talks to the web server, not to the framework,
 * so nginx, PHP-FPM, the storage symlink and the real session cookie are all
 * in the path. A route that passes its feature test and 500s behind nginx is
 * exactly what this is for.
 *
 * What counts as a failure:
 * - any 5xx;
 * - a 404 on a link the site itself printed (seeds excepted);
 * - a debug trace in a page body;
 * - an image, stylesheet, script or download link that does not answer
 *   2xx/3xx.
 *
 * A 403 on a page is recorded, not failed: a representative refused the
 * admin area is the platform working.
 *
 * It follows GET links only and never follows sign-out or two-factor
 * enrolment, so it changes nothing but its own session.
 */
final class SiteCrawler
{
    private const PUBLIC_SCOPE = '#^/(%s)(/|$|\?)#';

    private const SIGNED_IN_SCOPE = '#^/(%s)/(admin|extranet|submissions|contributions|directory|register|authority|completeness|indicators|announcements|notifications|account)(/|$|\?)#';

    private const NEVER = '#logout|two-factor/enrol|two-factor/confirm#';

    private const DOWNLOAD = '#\.(csv|ics|json|pdf|png|jpe?g|webp|svg|zip|docx?|xlsx?|pptx?)(\?|$)|/export|/download|^/storage/#i';

    private const TRACE = '#Stack trace:|Whoops, looks like|vendor/laravel/framework|Illuminate\\\\[A-Z]#';

    private const ADMIN_SEEDS = ['/admin', '/admin/data', '/admin/pages', '/admin/media', '/admin/translations',
        '/admin/submissions', '/admin/reports', '/admin/users', '/admin/meetings', '/admin/announcements',
        '/admin/contributions', '/admin/confirmations', '/admin/authority-changes', '/admin/settings',
        '/admin/audit', '/admin/menus', '/admin/samples', '/admin/help', '/admin/search?q=roaming'];

    private const MEMBER_SEEDS = ['/extranet', '/extranet/dashboard', '/extranet/folders', '/extranet/calendar',
        '/submissions', '/contributions', '/directory', '/register', '/authority', '/completeness',
        '/indicators', '/announcements', '/notifications', '/account'];

    private CookieJar $jar;

    /** @var array<string, int> */
    private array $assets = [];

    /**
     * @param  list<string>  $locales
     * @param  array<string, string>  $resolve  host => IP, as curl --resolve
     */
    public function __construct(
        private readonly string $base,
        private readonly array $locales = ['en'],
        private readonly int $maxPages = 400,
        private readonly array $resolve = [],
        private readonly bool $verifyTls = true,
    ) {
        $this->jar = new CookieJar;
    }

    /**
     * Sign in through the real form, answering a two-factor challenge when
     * one is shown and a secret was given.
     */
    public function signIn(string $email, string $password, ?string $totpSecret = null): string
    {
        $locale = $this->locales[0];
        $form = $this->get("/{$locale}/login");

        $response = $this->post("/{$locale}/login", [
            '_token' => $this->token($form->body()),
            'email' => $email,
            'password' => $password,
        ]);

        $landed = $this->location($response) ?? '';

        if (str_contains($landed, 'two-factor/challenge')) {
            if ($totpSecret === null) {
                throw new RuntimeException("{$email} is asked for a two-factor code and no secret was given.");
            }

            $challenge = $this->get($landed);
            $response = $this->post($landed, [
                '_token' => $this->token($challenge->body()),
                'code' => (new Google2FA)->getCurrentOtp($totpSecret),
            ]);
            $landed = $this->location($response) ?? '';
        }

        if (str_contains($landed, '/login') || str_contains($landed, 'two-factor/challenge')) {
            throw new RuntimeException("Sign-in failed for {$email}: sent back to {$landed}.");
        }

        return $landed;
    }

    /**
     * @return array{pages: list<array<string, mixed>>, assets: array<string, int>, failures: list<array<string, string>>}
     */
    public function crawl(bool $signedIn): array
    {
        $alternation = implode('|', array_map('preg_quote', $this->locales));
        $scope = sprintf($signedIn ? self::SIGNED_IN_SCOPE : self::PUBLIC_SCOPE, $alternation);

        $seeds = [];
        foreach ($this->locales as $locale) {
            if (! $signedIn) {
                $seeds[] = "/{$locale}";

                continue;
            }

            foreach ([...self::MEMBER_SEEDS, ...self::ADMIN_SEEDS] as $path) {
                $seeds[] = "/{$locale}{$path}";
            }
        }

        $queue = $seeds;
        $referrer = [];
        $seen = [];
        $pages = [];
        $failures = [];

        while ($queue !== [] && count($pages) < $this->maxPages) {
            $path = array_shift($queue);

            if (isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;
            $started = microtime(true);

            try {
                [$response, $final] = $this->follow($path);
                $status = $response->status();
                $body = $response->body();
            } catch (Throwable $e) {
                $failures[] = ['url' => $path, 'kind' => 'unreachable', 'detail' => mb_substr($e->getMessage(), 0, 120), 'from' => $referrer[$path] ?? ''];
                $pages[] = ['url' => $path, 'status' => 0];

                continue;
            }

            $pages[] = array_filter([
                'url' => $path,
                'status' => $status,
                'final' => $final !== $path ? $final : null,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ], fn ($v) => $v !== null);

            if ($status >= 500) {
                $failures[] = ['url' => $path, 'kind' => "status {$status}", 'detail' => '', 'from' => $referrer[$path] ?? ''];
            } elseif ($status === 404 && ! in_array($path, $seeds, true)) {
                $failures[] = ['url' => $path, 'kind' => 'broken link', 'detail' => '404', 'from' => $referrer[$path] ?? ''];
            }

            if (preg_match(self::TRACE, $body)) {
                $failures[] = ['url' => $path, 'kind' => 'debug trace in page', 'detail' => '', 'from' => $referrer[$path] ?? ''];
            }

            if ($status !== 200 || ! str_contains((string) $response->header('Content-Type'), 'html')) {
                continue;
            }

            [$links, $assets] = $this->extract($body);

            foreach ($assets as $asset) {
                $code = $this->asset($asset);

                if ($code === 0 || $code >= 400) {
                    $failures[] = ['url' => $asset, 'kind' => 'broken asset', 'detail' => (string) $code, 'from' => $path];
                }
            }

            foreach ($links as $link) {
                if (preg_match(self::NEVER, $link)) {
                    continue;
                }

                if (preg_match(self::DOWNLOAD, $link)) {
                    $code = $this->asset($link);

                    if ($code === 0 || $code >= 400) {
                        $failures[] = ['url' => $link, 'kind' => 'broken download', 'detail' => (string) $code, 'from' => $path];
                    }

                    continue;
                }

                if (preg_match($scope, $link) && ! isset($seen[$link]) && ! in_array($link, $queue, true)) {
                    $queue[] = $link;
                    $referrer[$link] = $path;
                }
            }
        }

        return ['pages' => $pages, 'assets' => $this->assets, 'failures' => $this->unique($failures)];
    }

    /** @return array{0: list<string>, 1: list<string>} links and assets, as site-relative paths */
    private function extract(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $links = [];
        foreach ($xpath->query('//a[@href]') as $a) {
            if (($path = $this->local($a->getAttribute('href'))) !== null) {
                $links[] = $path;
            }
        }

        $assets = [];
        foreach ($xpath->query('//img[@src]|//script[@src]|//link[@rel="stylesheet"][@href]|//link[@rel="icon"][@href]|//source[@srcset]|//img[@srcset]') as $node) {
            $values = $node->hasAttribute('srcset') && $node->nodeName !== 'img'
                ? [$node->getAttribute('srcset')]
                : [$node->getAttribute('src') ?: $node->getAttribute('href')];

            if ($node->nodeName === 'img' && $node->hasAttribute('srcset')) {
                $values[] = $node->getAttribute('srcset');
            }

            foreach ($values as $value) {
                foreach (explode(',', $value) as $candidate) {
                    $url = trim(explode(' ', trim($candidate))[0] ?? '');

                    if ($url !== '' && ($path = $this->local($url)) !== null) {
                        $assets[] = $path;
                    }
                }
            }
        }

        return [array_values(array_unique($links)), array_values(array_unique($assets))];
    }

    /** A link as a site-relative path, or null if it leaves the site. */
    private function local(string $href): ?string
    {
        $href = html_entity_decode(trim($href));

        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript|data):#i', $href)) {
            return null;
        }

        $base = rtrim($this->base, '/');

        if (str_starts_with($href, $base)) {
            $href = substr($href, strlen($base)) ?: '/';
        } elseif (preg_match('#^(https?:)?//#i', $href)) {
            return null;
        }

        if (! str_starts_with($href, '/')) {
            return null;
        }

        return explode('#', $href)[0];
    }

    private function asset(string $path): int
    {
        if (isset($this->assets[$path])) {
            return $this->assets[$path];
        }

        try {
            $status = $this->client()->head($this->base.$path)->status();
        } catch (Throwable) {
            $status = 0;
        }

        return $this->assets[$path] = $status;
    }

    /** @return array{0: Response, 1: string} */
    private function follow(string $path): array
    {
        $response = $this->get($path);

        for ($hops = 0; $hops < 5 && $response->redirect(); $hops++) {
            $next = $this->location($response);

            if ($next === null || preg_match(self::NEVER, $next)) {
                break;
            }

            $path = $next;
            $response = $this->get($path);
        }

        return [$response, $path];
    }

    private function location(Response $response): ?string
    {
        $location = $response->header('Location');

        return $location === '' ? null : ($this->local($location) ?? $location);
    }

    private function token(string $html): string
    {
        if (! preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)
            && ! preg_match('/value="([^"]+)"\s+name="_token"/', $html, $m)) {
            throw new RuntimeException('The form carries no CSRF token.');
        }

        return $m[1];
    }

    private function get(string $path): Response
    {
        return $this->client()->get($this->base.$path);
    }

    /** @param array<string, string> $data */
    private function post(string $path, array $data): Response
    {
        return $this->client()->asForm()->post($this->base.$path, $data);
    }

    private function client(): PendingRequest
    {
        $host = (string) parse_url($this->base, PHP_URL_HOST);
        $port = (int) (parse_url($this->base, PHP_URL_PORT) ?: (str_starts_with($this->base, 'https') ? 443 : 80));
        $curl = isset($this->resolve[$host]) ? [CURLOPT_RESOLVE => ["{$host}:{$port}:{$this->resolve[$host]}"]] : [];

        return Http::withoutRedirecting()
            ->timeout(60)
            ->withHeaders(['User-Agent' => 'CRASA-qa-crawl/1.0'])
            ->withOptions(['cookies' => $this->jar, 'verify' => $this->verifyTls, 'curl' => $curl, 'http_errors' => false]);
    }

    /**
     * @param  list<array<string, string>>  $failures
     * @return list<array<string, string>>
     */
    private function unique(array $failures): array
    {
        $out = [];

        foreach ($failures as $failure) {
            $out[$failure['kind'].' '.$failure['url']] ??= $failure;
        }

        return array_values($out);
    }
}
