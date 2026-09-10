<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * The events host, WS-12.
 *
 * WHY THIS IS NOT A CRAWL. events.crasa.org is WordPress running The
 * Events Calendar, which publishes every event as JSON at
 * /wp-json/tribe/events/v1/events with its venue, its category, its dates
 * and its description already separated. Scraping the rendered pages for
 * the same fields would be slower, more fragile and less complete.
 *
 * THE DEFAULT QUERY RETURNS ONE EVENT. The endpoint answers with upcoming
 * events only unless a start date is given, so a run without one reports
 * a total of 1 against a calendar of 43. The window below runs from before
 * the association's first recorded meeting to well past the delivery date.
 */
class PullLegacyEvents extends Command
{
    protected $signature = 'legacy:events {--timeout=30 : Seconds to wait for one response}';

    protected $description = 'Pull the events calendar from events.crasa.org into the legacy manifest';

    private const ENDPOINT = 'https://events.crasa.org/wp-json/tribe/events/v1/events';

    private const AGENT = 'CRASA-migration/1.0 (+website replacement for the CRASA Secretariat; contact crasa@crasa.org)';

    private const WINDOW_FROM = '2000-01-01';

    private const WINDOW_TO = '2035-12-31';

    public function handle(): int
    {
        $events = [];
        $page = 1;

        do {
            $url = self::ENDPOINT.'?'.http_build_query([
                'page' => $page,
                'per_page' => 50,
                'start_date' => self::WINDOW_FROM,
                'end_date' => self::WINDOW_TO,
            ]);

            $payload = $this->fetch($url);

            if ($payload === null) {
                $this->error('The events host did not answer page '.$page.'.');

                return self::FAILURE;
            }

            foreach ($payload['events'] ?? [] as $event) {
                $events[$event['url']] = $this->shape($event);
            }

            $this->line(sprintf('  page %d: %d events', $page, count($payload['events'] ?? [])));
            $page++;
        } while ($page <= (int) ($payload['total_pages'] ?? 1));

        Storage::disk('local')->put(
            'legacy/events.json',
            json_encode([
                'pulled_at' => now()->toIso8601String(),
                'events' => array_values($events),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->info(count($events).' events written to the manifest.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function shape(array $event): array
    {
        return [
            'external_id' => (string) ($event['id'] ?? ''),
            'url' => $event['url'] ?? '',
            'title' => html_entity_decode((string) ($event['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'slug' => $event['slug'] ?? '',
            'description' => $event['description'] ?? '',
            'excerpt' => $event['excerpt'] ?? '',
            'start_date' => $event['start_date'] ?? null,
            'end_date' => $event['end_date'] ?? null,
            'all_day' => (bool) ($event['all_day'] ?? false),
            'venue' => $event['venue']['venue'] ?? null,
            'venue_country' => $event['venue']['country'] ?? null,
            'category' => $event['categories'][0]['name'] ?? null,
            'website' => $event['website'] ?? null,
            'image' => $event['image']['url'] ?? null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function fetch(string $url): ?array
    {
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => (int) $this->option('timeout'),
            CURLOPT_USERAGENT => self::AGENT,
            CURLOPT_ACCEPT_ENCODING => '',
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($body === false || $status >= 400) {
            return null;
        }

        $decoded = json_decode((string) $body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
