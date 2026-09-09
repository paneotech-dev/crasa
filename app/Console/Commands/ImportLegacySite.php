<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The legacy site's documents and photographs, WS-12.
 *
 * WHY THIS IS A RESUMABLE JOB AND NOT A SCRIPT. Two earlier runs against
 * crasa.org stalled: the first hung for twenty-five minutes on a single
 * request, the second stopped after thirty pages with four hundred still
 * queued. The host is slow and unreliable under sustained crawling, and it
 * is the client's live site, so the answer is not to crawl it harder. State
 * is written after every fetch and --resume picks up exactly where the last
 * run stopped.
 *
 * TWO LEVELS, BECAUSE THE SITE IS WEBFLOW. No document is linked from any
 * listing page. A breadth-first crawl of the six category pages returned
 * zero documents, which looked like an empty site and is not:
 *
 *   /post-category/electronic-communications-regulatory-guidelines
 *       -> 16 links to /post-articles/<name>
 *           -> one PDF each, on cdn.prod.website-files.com
 *
 * So the first pass collects article URLs from the listings and the second
 * fetches each article for its document.
 *
 * ONE REQUEST A SECOND, MEASURED FROM THE END OF THE LAST ONE. Both hosts'
 * robots.txt were checked before any of this was written and both allow it
 * (Disallow: empty, no sitemap on the main host). The user agent names the
 * migration and the client, so whoever reads the access log knows who this
 * is and can say stop.
 *
 * IT WRITES NO RECORDS. This produces a manifest and the files. Mapping a
 * legacy category onto a publication_type is a decision about CRASA's own
 * taxonomy, and it is made once, with the manifest in front of somebody,
 * rather than guessed here.
 */
class ImportLegacySite extends Command
{
    protected $signature = 'legacy:import
                            {--resume : Continue from the last run rather than starting again}
                            {--max=600 : Stop after this many requests, so a link loop cannot run away}
                            {--seconds=1 : Seconds between requests}
                            {--timeout=25 : Seconds to wait for one response}';

    protected $description = 'Crawl the legacy site for its documents and photographs, resumably';

    private const HOST = 'https://www.crasa.org';

    private const AGENT = 'CRASA-migration/1.0 (+website replacement for the CRASA Secretariat; contact crasa@crasa.org)';

    private const STATE = 'legacy/state.json';

    /**
     * Where the crawl starts. The six document categories the migration
     * brief names, then the newsroom, then the events host.
     *
     * @var list<string>
     */
    private const SEEDS = [
        '/post-category/electronic-communications-regulatory-guidelines',
        '/post-category/postal-regulatory-guidelines',
        '/post-category/annual-reports',
        '/post-category/press-releases',
        '/post-category/consultancies',
        '/post-category/tenders',
        '/news/current-news',
        '/news/archived-news',
        '/blog-categories/crasa-news',
        '/blog-categories/members-news',
        '/publications',
        '/about',
        '/contact',
    ];

    /** @var array<string, mixed> */
    private array $state;

    public function handle(): int
    {
        $this->state = $this->load();

        $this->line('Legacy import. '.count($this->state['queue']).' queued, '
            .count($this->state['visited']).' already fetched.');

        $max = (int) $this->option('max');
        $pause = max(1, (int) $this->option('seconds'));
        $fetched = 0;

        while ($this->state['queue'] !== [] && $fetched < $max) {
            $url = array_shift($this->state['queue']);

            if (isset($this->state['visited'][$url])) {
                continue;
            }

            $started = microtime(true);
            $body = $this->fetch($url);
            $fetched++;

            $this->state['visited'][$url] = true;

            if ($body === null) {
                $this->state['errors'][] = $url;
                $this->warn('  no answer: '.$url);
            } else {
                $this->harvest($url, $body);
            }

            // After every fetch, not every ten. A run that stops between
            // writes loses the page it was on and nothing else.
            $this->save();

            if ($fetched % 10 === 0) {
                $this->report($fetched);
            }

            /*
             * A second from the end of the last response, not the start.
             * Measuring from the start means a slow answer is followed
             * immediately by the next request, which is the opposite of
             * what a rate limit is for.
             */
            $elapsed = microtime(true) - $started;

            if ($this->state['queue'] !== [] && $elapsed < $pause) {
                usleep((int) (($pause - $elapsed) * 1_000_000));
            }
        }

        $this->report($fetched);

        if ($this->state['queue'] !== []) {
            $this->line('');
            $this->info(count($this->state['queue']).' still queued. Run again with --resume.');
        } else {
            $this->line('');
            $this->info('The queue is empty. '.count($this->state['documents']).' documents and '
                .count($this->state['images']).' images are in the manifest.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        if ($this->option('resume') && Storage::disk('local')->exists(self::STATE)) {
            $state = json_decode(Storage::disk('local')->get(self::STATE), true);

            if (is_array($state)) {
                return $state;
            }

            $this->warn('The state file could not be read. Starting again.');
        }

        return [
            'queue' => array_map(fn (string $path) => self::HOST.$path, self::SEEDS),
            'visited' => [],
            'documents' => [],
            'images' => [],
            'pages' => [],
            'errors' => [],
            'started_at' => now()->toIso8601String(),
        ];
    }

    private function save(): void
    {
        $this->state['updated_at'] = now()->toIso8601String();

        Storage::disk('local')->put(
            self::STATE,
            json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function report(int $fetched): void
    {
        $this->line(sprintf(
            '  %d fetched this run, %d pages, %d documents, %d images, %d queued, %d errors',
            $fetched,
            count($this->state['pages']),
            count($this->state['documents']),
            count($this->state['images']),
            count($this->state['queue']),
            count($this->state['errors'])
        ));
    }

    /**
     * One request, with a timeout.
     *
     * cURL directly rather than the HTTP client, because what this needs is
     * a hard connect and total timeout: the first run hung for twenty-five
     * minutes on a single request and a default has no ceiling that low.
     */
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

    /** Everything worth keeping on one page, and where to go next. */
    private function harvest(string $url, string $body): void
    {
        $this->state['pages'][$url] = [
            'title' => $this->title($body),
            'fetched_at' => now()->toIso8601String(),
        ];

        foreach ($this->links($body) as $link) {
            if ($this->isDocument($link)) {
                $this->state['documents'][$link] = ['found_on' => $url];

                continue;
            }

            if ($this->isImage($link)) {
                $this->state['images'][$link] = ['found_on' => $url];

                continue;
            }

            if (! $this->isOwnHost($link) || isset($this->state['visited'][$link])) {
                continue;
            }

            /*
             * The second level. An article page is where the documents
             * are, so it goes to the front: a run that is stopped early
             * should have fetched documents rather than more listings.
             */
            if (Str::contains($link, ['/post-articles/', '/post/'])) {
                array_unshift($this->state['queue'], $link);

                continue;
            }

            if (Str::contains($link, ['/post-category/', '/news/', '/blog-categories/', '/publications'])) {
                $this->state['queue'][] = $link;
            }
        }

        $this->state['queue'] = array_values(array_unique($this->state['queue']));
    }

    /** @return list<string> */
    private function links(string $body): array
    {
        preg_match_all('/(?:href|src)\s*=\s*"([^"]+)"/i', $body, $matches);

        $links = [];

        foreach ($matches[1] as $raw) {
            $link = $this->absolute(trim($raw));

            if ($link !== null) {
                $links[] = $link;
            }
        }

        return array_values(array_unique($links));
    }

    private function absolute(string $link): ?string
    {
        if ($link === '' || str_starts_with($link, '#') || str_starts_with($link, 'mailto:')
            || str_starts_with($link, 'tel:') || str_starts_with($link, 'javascript:')
            || str_starts_with($link, 'data:')) {
            return null;
        }

        /*
         * A trailing fragment is the same page. One article link on the
         * legacy site ends in a bare "#", which was fetched as a
         * separate URL and answered with nothing.
         */
        $link = preg_replace('/#.*$/', '', $link);

        if ($link === '') {
            return null;
        }

        if (str_starts_with($link, '//')) {
            return 'https:'.$link;
        }

        if (str_starts_with($link, '/')) {
            return self::HOST.$link;
        }

        return str_starts_with($link, 'http') ? $link : null;
    }

    private function isOwnHost(string $url): bool
    {
        return str_starts_with($url, self::HOST);
    }

    private function isDocument(string $url): bool
    {
        return (bool) preg_match('/\.(pdf|docx?|xlsx?|pptx?)(\?|$)/i', $url);
    }

    private function isImage(string $url): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp|gif|svg)(\?|$)/i', $url);
    }

    private function title(string $body): string
    {
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $match);

        return trim(html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
