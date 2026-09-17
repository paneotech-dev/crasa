<?php

namespace App\Console\Commands;

use App\Support\Qa\SiteCrawler;
use Illuminate\Console\Command;
use Throwable;

/**
 * The rendered-site regression pass, repeatable. D3's interim answer.
 *
 * The contract names a Selenium regression suite. Until that exists, this
 * walks the running site the way a person would, once as a visitor in each
 * language and once per signed-in account, and fails on anything a person
 * would hit: a 500, a link to nowhere, a debug trace, a missing image or a
 * download that does not download.
 *
 * It does not measure colour contrast; that needs a browser that computes
 * styles, and lives in the separate Playwright audit (docs/QA_CRAWL.md).
 *
 *   php artisan qa:crawl
 *   php artisan qa:crawl --base=https://crasadev.paneo.tech --resolve=crasadev.paneo.tech:127.0.0.1 --insecure \
 *       --accounts=/path/to/accounts.txt --json=storage/app/private/qa/latest.json
 *
 * The accounts file has one account per line: the address, the password as
 * the last word, and optionally "totp:SECRET" before it for an account with
 * two-factor sign-in. Lines without an address are ignored, so the
 * demonstration credentials file can be passed as it is.
 *
 * Exit status is non-zero when anything failed, so it can gate a deploy.
 */
class CrawlSite extends Command
{
    protected $signature = 'qa:crawl
        {--base= : Site root. Defaults to APP_URL}
        {--locales=en,fr,pt : Languages to walk as a visitor}
        {--accounts= : File of accounts to walk as, one per line}
        {--only= : Limit to these identities: guest and/or addresses, comma-separated}
        {--max=400 : Most pages per identity}
        {--resolve= : host:ip, to reach the site at an address DNS does not give}
        {--insecure : Accept a certificate that does not verify, for a self-signed test host}
        {--json= : Write every page and failure to this file}';

    protected $description = 'Walk the running site as a visitor and as each account, and fail on errors, broken links and broken files';

    public function handle(): int
    {
        $base = rtrim((string) ($this->option('base') ?: config('app.url')), '/');
        $locales = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('locales')))));
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        $resolve = [];
        if ($this->option('resolve')) {
            [$host, $ip] = explode(':', (string) $this->option('resolve'), 2);
            $resolve[$host] = $ip;
        }

        $identities = ['guest' => null];
        foreach ($this->accounts() as $email => $account) {
            $identities[$email] = $account;
        }

        if ($only !== []) {
            $identities = array_intersect_key($identities, array_flip($only));
        }

        $report = ['base' => $base, 'started' => now()->toIso8601String(), 'identities' => []];
        $failed = 0;
        $rows = [];

        foreach ($identities as $name => $account) {
            $crawler = new SiteCrawler(
                $base,
                $account === null ? $locales : [$locales[0] ?? 'en'],
                (int) $this->option('max'),
                $resolve,
                ! $this->option('insecure'),
            );

            $landed = '';

            try {
                if ($account !== null) {
                    $landed = $crawler->signIn($name, $account['password'], $account['totp']);
                }

                $result = $crawler->crawl($account !== null);
            } catch (Throwable $e) {
                $result = ['pages' => [], 'assets' => [], 'failures' => [['url' => '', 'kind' => 'sign-in', 'detail' => $e->getMessage(), 'from' => '']]];
            }

            $statuses = collect($result['pages'])->countBy('status')->sortKeys()
                ->map(fn ($n, $s) => "{$s}×{$n}")->implode(' ');
            $slowest = collect($result['pages'])->sortByDesc('ms')->first();

            $rows[] = [
                $name, $landed ?: '-', count($result['pages']), $statuses, count($result['assets']),
                $slowest ? $slowest['url'].' '.$slowest['ms'].'ms' : '-', count($result['failures']),
            ];

            $failed += count($result['failures']);
            $report['identities'][$name] = ['landed' => $landed] + $result;
        }

        $this->table(['Identity', 'Landed', 'Pages', 'Statuses', 'Files checked', 'Slowest', 'Failures'], $rows);

        foreach ($report['identities'] as $name => $result) {
            foreach ($result['failures'] as $failure) {
                $this->error(sprintf('%s: %s %s%s%s', $name, $failure['kind'], $failure['url'],
                    $failure['detail'] !== '' ? ' ('.$failure['detail'].')' : '',
                    $failure['from'] !== '' ? ' linked from '.$failure['from'] : ''));
            }
        }

        if ($this->option('json')) {
            $report['finished'] = now()->toIso8601String();
            file_put_contents((string) $this->option('json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line('Written to '.$this->option('json'));
        }

        if ($failed > 0) {
            $this->error($failed.' failure'.($failed === 1 ? '' : 's').'.');

            return self::FAILURE;
        }

        $this->info('No failures.');

        return self::SUCCESS;
    }

    /** @return array<string, array{password: string, totp: ?string}> */
    private function accounts(): array
    {
        $file = $this->option('accounts');

        if (! $file) {
            return [];
        }

        if (! is_readable((string) $file)) {
            $this->error('Cannot read '.$file);

            return [];
        }

        $accounts = [];

        foreach (file((string) $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $words = preg_split('/\s+/', trim($line)) ?: [];
            $email = collect($words)->first(fn ($w) => filter_var($w, FILTER_VALIDATE_EMAIL) !== false);

            if ($email === null || count($words) < 2 || end($words) === $email) {
                continue;
            }

            $totp = collect($words)->first(fn ($w) => str_starts_with($w, 'totp:'));

            $accounts[$email] = [
                'password' => (string) end($words),
                'totp' => $totp !== null ? substr($totp, 5) : null,
            ];
        }

        return $accounts;
    }
}
