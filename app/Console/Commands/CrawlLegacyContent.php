<?php

namespace App\Console\Commands;

use App\Support\Legacy\PageParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The second pass: what each legacy page actually says, WS-12.
 *
 * WHY A SECOND PASS AT ALL. `legacy:import` produced a manifest of URLs,
 * titles and asset links. That was enough to decide the taxonomy and it is
 * not enough to write records: a publication needs its summary and its
 * body, a news item needs its date and its category, and none of that was
 * kept. Rather than change the first crawler and lose the run that is
 * already banked, this reads its page list and fetches each page again for
 * its content.
 *
 * TWO GAPS THE FIRST CRAWL LEFT, both found by reading its own output. The
 * queue rules matched /post-category/, /news/, /blog-categories/ and
 * /publications and nothing else, so `/projects/current-projects`,
 * `/projects/past-projects` and the whole `/project/<slug>` collection
 * behind them were never reached, and neither was the homepage. All four
 * are seeded here.
 *
 * Same rate limit, same user agent, same resumable shape as the first
 * crawl, and for the same reason: this is the client's live site.
 */
class CrawlLegacyContent extends Command
{
    protected $signature = 'legacy:content
                            {--fresh : Start again rather than resuming}
                            {--reparse : Re-read the saved HTML with the current parser, fetching nothing}
                            {--max=400 : Stop after this many requests}
                            {--seconds=1 : Seconds between requests}
                            {--timeout=25 : Seconds to wait for one response}';

    protected $description = 'Fetch each legacy page for its title, body, date, category and assets';

    private const HOST = 'https://www.crasa.org';

    private const AGENT = 'CRASA-migration/1.0 (+website replacement for the CRASA Secretariat; contact crasa@crasa.org)';

    private const STATE = 'legacy/content.json';

    private const CRAWL = 'legacy/state.json';

    /** Reached by no link the first crawl followed. */
    private const EXTRA_SEEDS = [
        '/',
        '/projects/current-projects',
        '/projects/past-projects',
    ];

    /** @var array<string, mixed> */
    private array $state;

    public function handle(): int
    {
        $this->state = $this->load();

        /*
         * A parser fix must not become another ninety-eight requests
         * against the client's live site. Every page was saved as served,
         * so a re-parse reads the disk.
         */
        if ($this->option('reparse')) {
            return $this->reparse();
        }

        $this->line(sprintf(
            'Legacy content. %d queued, %d already parsed.',
            count($this->state['queue']),
            count($this->state['pages'])
        ));

        $max = (int) $this->option('max');
        $pause = max(0, (int) $this->option('seconds'));
        $fetched = 0;

        while ($this->state['queue'] !== [] && $fetched < $max) {
            $url = array_shift($this->state['queue']);

            if (isset($this->state['pages'][$url]) || isset($this->state['failed'][$url])) {
                continue;
            }

            $started = microtime(true);
            $html = $this->fetch($url);
            $fetched++;

            if ($html === null) {
                $this->state['failed'][$url] = true;
                $this->warn('  no answer: '.$url);
            } else {
                $this->keep($url, $html);
            }

            $this->save();

            if ($fetched % 10 === 0) {
                $this->line(sprintf(
                    '  %d fetched, %d parsed, %d queued, %d failed',
                    $fetched,
                    count($this->state['pages']),
                    count($this->state['queue']),
                    count($this->state['failed'])
                ));
            }

            $elapsed = microtime(true) - $started;

            if ($this->state['queue'] !== [] && $elapsed < $pause) {
                usleep((int) (($pause - $elapsed) * 1_000_000));
            }
        }

        $this->line('');
        $this->info(sprintf(
            '%d pages parsed, %d still queued, %d failed.',
            count($this->state['pages']),
            count($this->state['queue']),
            count($this->state['failed'])
        ));

        return self::SUCCESS;
    }

    private function reparse(): int
    {
        $changed = 0;

        foreach ($this->state['pages'] as $url => $page) {
            $file = $page['html_file'] ?? null;

            if ($file === null || ! Storage::disk('local')->exists($file)) {
                $this->warn('  no saved page for '.$url);

                continue;
            }

            $parsed = PageParser::parse($url, Storage::disk('local')->get($file));
            unset($parsed['child_links']);
            $parsed['html_file'] = $file;

            if ($parsed !== $page) {
                $changed++;
            }

            $this->state['pages'][$url] = $parsed;
        }

        $this->save();
        $this->info(sprintf('%d of %d pages re-parsed with a different result.', $changed, count($this->state['pages'])));

        return self::SUCCESS;
    }
    /** @return array<string, mixed> */
    private function load(): array
    {
        if (! $this->option('fresh') && Storage::disk('local')->exists(self::STATE)) {
            $state = json_decode(Storage::disk('local')->get(self::STATE), true);

            if (is_array($state)) {
                return $state;
            }
        }

        return [
            'queue' => $this->seeds(),
            'pages' => [],
            'failed' => [],
            'started_at' => now()->toIso8601String(),
        ];
    }

    /** @return list<string> */
    private function seeds(): array
    {
        $seeds = array_map(fn (string $p) => rtrim(self::HOST.$p, '/') ?: self::HOST, self::EXTRA_SEEDS);

        if (Storage::disk('local')->exists(self::CRAWL)) {
            $crawl = json_decode(Storage::disk('local')->get(self::CRAWL), true);

            foreach (array_keys($crawl['pages'] ?? []) as $url) {
                $seeds[] = rtrim((string) $url, '/');
            }
        }

        return array_values(array_unique($seeds));
    }

    private function save(): void
    {
        $this->state['updated_at'] = now()->toIso8601String();

        Storage::disk('local')->put(
            self::STATE,
            json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function keep(string $url, string $html): void
    {
        // The raw page as served, kept because the legacy site will be
        // switched off and a parse cannot be re-run against nothing.
        Storage::disk('local')->put('legacy/html/'.sha1($url).'.html', $html);

        $page = PageParser::parse($url, $html);
        $children = $page['child_links'];
        unset($page['child_links']);

        $page['html_file'] = 'legacy/html/'.sha1($url).'.html';
        $this->state['pages'][$url] = $page;

        foreach ($children as $child) {
            if (! isset($this->state['pages'][$child]) && ! in_array($child, $this->state['queue'], true)) {
                // Item pages go to the front. A run stopped early should
                // have fetched content rather than more listings.
                array_unshift($this->state['queue'], $child);
            }
        }
    }

    private function fetch(string $url): ?string
    {
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => (int) $this->option('timeout'),
            CURLOPT_USERAGENT => self::AGENT,
            CURLOPT_ACCEPT_ENCODING => '',
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return $body === false || $status >= 400 ? null : (string) $body;
    }
}
