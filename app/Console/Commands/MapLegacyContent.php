<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\LegacyRecord;
use App\Models\MediaAsset;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Sector;
use App\Models\Page;
use App\Support\Legacy\AboutSections;
use App\Support\Legacy\Mapping;
use App\Support\Legacy\PageParser;
use App\Support\Legacy\Renditions;
use App\Support\Legacy\TitleCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The legacy site, written into records. WS-12.
 *
 * THESE ARE REAL RECORDS, NOT SAMPLE DATA. Everything here is CRASA's own
 * published material, so nothing is marked is_sample and the go-live purge
 * must not reach it. That is the difference between this command and every
 * seeder in the project, and it is why it runs once and is then a matter of
 * record.
 *
 * IT IS IDEMPOTENT. Every record is matched on its legacy URL through the
 * legacy_records table, so a second run updates what it wrote the first
 * time rather than doubling the newsroom. That matters because the mapping
 * ruling may be refined once somebody reads the flagged rows.
 *
 * WHAT IT FLAGS RATHER THAN DECIDES. Three things need a person: a
 * guideline whose title names a different sector from its legacy category,
 * an item with no date anywhere, and a headline re-cased out of capitals.
 * All three are written to legacy_records with a note, and none of them
 * stops the import.
 */
class MapLegacyContent extends Command
{
    protected $signature = 'legacy:map
                            {--dry-run : Report what would be written and write nothing}';

    protected $description = 'Write the crawled legacy site into publications, news, meetings and projects';

    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<string, int> */
    private array $flags = ['sector' => 0, 'date' => 0, 'title' => 0];

    /** @var array<string, array<string, mixed>> */
    private array $assets = ['documents' => [], 'images' => []];

    /** @var array<string, int> */
    private array $sectors = [];

    /** @var list<string> */
    private array $archived = [];

    public function handle(): int
    {
        $content = $this->read('legacy/content.json');

        if ($content === null) {
            $this->error('No content manifest. Run legacy:content first.');

            return self::FAILURE;
        }

        $this->assets = $this->read('legacy/assets.json') ?? $this->assets;
        $this->sectors = Sector::query()->pluck('id', 'slug')->all();
        $this->archived = $this->archivedUrls($content['pages']);

        $pages = $content['pages'];

        try {
            DB::transaction(function () use ($pages): void {
                foreach ($pages as $url => $page) {
                    $this->one($url, $page);
                }

                $this->about($pages);
                $this->catalogue();
                $this->events();

                if ($this->option('dry-run')) {
                    throw new DryRun;
                }
            });
        } catch (DryRun) {
            $this->warn('Dry run. Nothing was written.');
        }

        $this->report();

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $page */
    private function one(string $url, array $page): void
    {
        $path = LegacyRecord::normalise($url);

        if (isset(Mapping::LISTINGS[$path])) {
            $this->listing($url, $path);

            return;
        }

        if (str_starts_with($path, '/project/')) {
            $this->project($url, $page);

            return;
        }

        $rule = Mapping::forCategory($page['category'] ?? null);

        if ($rule === null) {
            // A page nobody ruled on. Recorded so the redirect map can
            // answer it, and counted so the report says how many.
            $this->record($url, $page['category'] ?? null, null, null, false, 'no mapping rule for this page', 'crawl');
            $this->count('unmapped');

            return;
        }

        match ($rule['kind']) {
            'publication' => $this->publication($url, $page, $rule),
            'news' => $this->news($url, $page, $rule),
            'meeting' => $this->meeting($url, $page),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $rule
     */
    private function publication(string $url, array $page, array $rule): void
    {
        $title = TitleCase::apply($page['title']);
        $sector = Mapping::sectorFor($title, $rule['sector'] ?? null);
        $date = $this->dateFor($page);

        $publication = $this->anyOf(Publication::class)
            ->firstOrNew(['slug' => $this->uniqueSlug(Publication::class, $title, $url)]);

        $publication->fill([
            'publication_type' => $rule['type'],
            'sector_id' => $sector['sector'] === null ? null : ($this->sectors[$sector['sector']] ?? null),
            'published_on' => $date['date'],
            'is_public' => true,
            'is_sample' => false,
        ])->save();

        $publication->setTranslation('title', 'en', $title);

        if (($page['summary'] ?? '') !== '') {
            $publication->setTranslation('summary', 'en', $page['summary']);
        }

        $this->attachDocuments($publication, $page);

        $this->record(
            $url,
            $page['category'] ?? null,
            $publication,
            '/publications/'.$publication->slug,
            $sector['from_title'] || $date['unknown'] || TitleCase::wasRecased($page['title']),
            $this->note($sector, $date, $page['title']),
            $date['source'],
        );

        $this->count('publications');
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $rule
     */
    private function news(string $url, array $page, array $rule): void
    {
        $title = TitleCase::apply($page['title']);
        $date = $this->dateFor($page);
        $sector = Mapping::sectorFor($title, null);

        $item = $this->anyOf(NewsItem::class)
            ->firstOrNew(['slug' => $this->uniqueSlug(NewsItem::class, $title, $url)]);

        $item->fill([
            'category' => $rule['type'],
            'sector_id' => $sector['sector'] === null ? null : ($this->sectors[$sector['sector']] ?? null),
            'published_at' => $date['date'],
            'status' => 'published',
            'archived' => in_array(LegacyRecord::normalise($url), $this->archived, true),
            'hero_image_path' => $this->heroPath($page),
            'is_sample' => false,
        ])->save();

        $item->setTranslation('title', 'en', $title);

        if (($page['summary'] ?? '') !== '') {
            $item->setTranslation('summary', 'en', $page['summary']);
        }

        if (($page['body_html'] ?? '') !== '') {
            $item->setTranslation('body', 'en', $this->body($page['body_html']));
        }

        $this->attachDocuments($item, $page);
        $this->attachImages($item, $page);

        $this->record(
            $url,
            $page['category'] ?? null,
            $item,
            '/newsroom/'.$item->slug,
            $date['unknown'] || TitleCase::wasRecased($page['title']),
            $this->note($sector, $date, $page['title']),
            $date['source'],
        );

        $this->count($item->archived ? 'news (archived)' : 'news');
    }

    /** @param array<string, mixed> $page */
    private function meeting(string $url, array $page): void
    {
        $title = TitleCase::apply($page['title']);
        $date = $this->dateFor($page);

        $meeting = Meeting::firstOrNew(['slug' => $this->uniqueSlug(Meeting::class, $title, $url)]);

        $meeting->fill([
            'title' => $title,
            'kind' => 'committee',
            'category' => 'Meeting',
            'starts_on' => ($page['event_dates']['start'] ?? null) !== null
                ? $this->parse($page['event_dates']['start'])
                : $date['date'],
            'ends_on' => ($page['event_dates']['end'] ?? null) !== null
                ? $this->parse($page['event_dates']['end'])
                : null,
            'summary' => $page['summary'] ?: null,
            'clearance' => 'public',
            'is_sample' => false,
        ])->save();

        $this->record(
            $url,
            $page['category'] ?? null,
            $meeting,
            '/events/'.$meeting->slug,
            $date['unknown'] || TitleCase::wasRecased($page['title']),
            $this->note(['sector' => null, 'from_title' => false], $date, $page['title']),
            $date['source'],
        );

        $this->count('meetings (from articles)');
    }

    /** @param array<string, mixed> $page */
    private function project(string $url, array $page): void
    {
        $title = TitleCase::apply($page['title']);
        $state = $this->projectState($url);
        $date = $this->dateFor($page);
        $sector = Mapping::sectorFor($title, null);

        $project = $this->anyOf(Project::class)
            ->firstOrNew(['slug' => $this->uniqueSlug(Project::class, $title, $url)]);

        $project->fill([
            'state' => $state,
            'sector_id' => $sector['sector'] === null ? null : ($this->sectors[$sector['sector']] ?? null),
            'hero_image_path' => $this->heroPath($page),
            'status' => 'published',
            'published_at' => $date['date'] ?? now(),
            'is_sample' => false,
        ])->save();

        $project->setTranslation('title', 'en', $title);

        if (($page['summary'] ?? '') !== '') {
            $project->setTranslation('summary', 'en', $page['summary']);
        }

        if (($page['body_html'] ?? '') !== '') {
            $project->setTranslation('body', 'en', $this->body($page['body_html']));
        }

        $this->attachDocuments($project, $page);
        $this->attachImages($project, $page);

        $this->record(
            $url,
            'Projects: '.$state,
            $project,
            '/projects/'.$project->slug,
            TitleCase::wasRecased($page['title']),
            $this->note($sector, $date, $page['title']),
            $date['source'],
        );

        $this->count('projects ('.$state.')');
    }

    /**
     * The About page, out of the "being prepared" states.
     *
     * Three of the page's blocks were standing on a note saying the text
     * was being prepared, and the legacy site states all three in CRASA's
     * own words: the structure of the Association, what the Executive
     * Committee does, and who is in the Secretariat. Those words are better
     * than a placeholder and better than anything this project could write
     * on the Secretariat's behalf.
     *
     * Partnerships and strategic priorities are left flagged, because the
     * legacy site says nothing about either and inventing them would be the
     * one failure mode a content audit exists to catch.
     *
     * Members is left alone: the platform already has a members page built
     * from the fourteen country records, which is a better source than a
     * page of cards.
     *
     * @param  array<string, mixed>  $pages
     */
    private function about(array $pages): void
    {
        $source = $pages['https://www.crasa.org/about'] ?? null;

        if ($source === null || ! Storage::disk('local')->exists($source['html_file'])) {
            return;
        }

        $page = Page::where('slug', 'about')->first();

        if ($page === null) {
            return;
        }

        $sections = AboutSections::parse(Storage::disk('local')->get($source['html_file']));

        $intoBlocks = [
            'governance' => 'structure',
            'executive-committee' => 'executive-committee',
            'secretariat' => 'secretariat',
        ];

        foreach ($intoBlocks as $anchor => $key) {
            $section = $sections[$key] ?? null;
            $block = $page->blocks()->where('anchor', $anchor)->first();

            if ($section === null || $block === null || $section['body'] === '') {
                continue;
            }

            $block->forceFill([
                'needs_content' => false,
                'imported_from_legacy' => true,
                'content_note' => null,
            ])->save();

            /*
             * The body only. The heading is the platform's own and exists
             * in all three languages already; overwriting it with the
             * legacy page's English would drop the French and the
             * Portuguese heading to a fallback for no gain.
             */
            $block->setTranslation('body', 'en', $section['body']);

            $this->count('About blocks filled from the legacy page');
        }

        $this->visionMissionHistory($page, $sections);
    }

    /**
     * Focus, vision, mission and history, as one block.
     *
     * The legacy page hides these behind a four-tab control. A tab is a
     * control rather than content, and a reader who wants CRASA's mission
     * should not have to press anything to see it. Screen 03 draws no tabs
     * on About.
     *
     * @param  array<string, array{heading: string, body: string}>  $sections
     */
    private function visionMissionHistory(Page $page, array $sections): void
    {
        $section = $sections['vision-mission-statement'] ?? null;

        if ($section === null || $section['body'] === '') {
            return;
        }

        $anchor = 'vision-mission-history';
        $block = $page->blocks()->where('anchor', $anchor)->first();

        if ($block === null) {
            $first = $page->blocks()->orderBy('position')->first();

            $block = $page->blocks()->create([
                'block_type' => 'rich_text',
                'anchor' => $anchor,
                'position' => ($first?->position ?? 0) + 1,
                'is_visible' => true,
                'settings' => ['alignment' => 'start'],
                'is_sample' => false,
            ]);

            // Everything that was at or after this position moves down one.
            $page->blocks()
                ->whereKeyNot($block->getKey())
                ->where('position', '>=', $block->position)
                ->increment('position');
        }

        $block->forceFill(['needs_content' => false, 'imported_from_legacy' => true, 'content_note' => null])->save();
        // The heading in all three, because a block created by an import
        // should not arrive on the French page already falling back.
        $block->setTranslation('heading', 'en', 'Focus, vision, mission and history');
        $block->setTranslation('heading', 'fr', 'Objectif, vision, mission et histoire');
        $block->setTranslation('heading', 'pt', 'Foco, visao, missao e historia');
        $block->setTranslation('body', 'en', $section['body']);

        $this->count('About blocks filled from the legacy page');
    }
    /**
     * Every downloaded photograph in the media library, attached or not.
     *
     * A picture on the About page or the homepage belongs to no news item
     * and is still one of the client's own photographs. Cataloguing only
     * the ones that happen to hang off an article would leave the library
     * showing forty of sixty-five and the rest reachable only through a
     * JSON file on disk.
     */
    private function catalogue(): void
    {
        foreach ($this->assets['images'] as $url => $asset) {
            MediaAsset::updateOrCreate(
                ['source_url' => $url],
                [
                    'disk' => 'public',
                    'path' => $asset['path'],
                    'original_name' => $asset['original_filename'],
                    'mime_type' => $asset['mime_type'],
                    'size_bytes' => $asset['size_bytes'],
                    'width' => $asset['width'],
                    'height' => $asset['height'],
                    'checksum' => hash('sha256', $url),
                    'renditions' => Renditions::fromManifest($asset['renditions'] ?? [], $asset['path'], $asset['width'] ?? null),
                    'rights_holder' => 'CRASA',
                    'licence' => "The client's own material, from crasa.org",
                    'is_sample' => false,
                ]
            );

            $this->count('photographs catalogued');
        }
    }
    /** The forty-three events on events.crasa.org. */
    private function events(): void
    {
        $manifest = $this->read('legacy/events.json');

        if ($manifest === null) {
            $this->warn('No events manifest. Run legacy:events for the calendar.');

            return;
        }

        foreach ($manifest['events'] as $event) {
            $title = TitleCase::apply($event['title']);

            $meeting = Meeting::firstOrNew(['external_id' => $event['external_id']]);

            $meeting->fill([
                'title' => $title,
                'slug' => $meeting->exists ? $meeting->slug : $this->uniqueSlug(Meeting::class, $title, $event['url']),
                'kind' => 'committee',
                'category' => $event['category'] ?? 'Meeting',
                'starts_on' => $this->parse($event['start_date']),
                'ends_on' => $this->parse($event['end_date']),
                'venue' => $event['venue'],
                'venue_country' => $event['venue_country'],
                'location' => $event['venue'] ?? $event['venue_country'],
                'summary' => PageParser::text((string) ($event['excerpt'] ?? '')) ?: null,
                'agenda' => PageParser::text((string) ($event['description'] ?? '')) ?: null,
                'clearance' => 'public',
                'registration_url' => $event['url'],
                'synced_at' => now(),
                'is_sample' => false,
            ])->save();

            $this->record(
                $event['url'],
                'Events: '.($event['category'] ?? 'Meeting'),
                $meeting,
                '/events/'.$meeting->slug,
                TitleCase::wasRecased($event['title']),
                TitleCase::wasRecased($event['title']) ? 'Headline re-cased from capitals.' : null,
                'page',
            );

            $this->count('meetings (from the events host)');
        }
    }

    private function listing(string $url, string $path): void
    {
        $this->record($url, null, null, '/'.ltrim(Mapping::LISTINGS[$path], '/'), false, null, 'page');
        $this->count('listing pages redirected');
    }

    /**
     * Which legacy items sit under Archived News.
     *
     * The listing page's own links are the only statement of this, so the
     * saved HTML is read rather than guessed at from a date.
     *
     * @param  array<string, mixed>  $pages
     * @return list<string>
     */
    private function archivedUrls(array $pages): array
    {
        $listing = $pages['https://www.crasa.org/news/archived-news'] ?? null;

        if ($listing === null || ! Storage::disk('local')->exists($listing['html_file'])) {
            return [];
        }

        $html = Storage::disk('local')->get($listing['html_file']);
        preg_match_all('/href="(\/post\/[^"#?]+)"/i', PageParser::section($html), $m);

        return array_values(array_unique(array_map(
            fn (string $p) => LegacyRecord::normalise($p),
            $m[1]
        )));
    }

    private function projectState(string $url): string
    {
        $content = $this->read('legacy/content.json');
        $listing = $content['pages']['https://www.crasa.org/projects/past-projects'] ?? null;

        if ($listing === null || ! Storage::disk('local')->exists($listing['html_file'])) {
            return 'current';
        }

        $html = PageParser::section(Storage::disk('local')->get($listing['html_file']));
        $path = LegacyRecord::normalise($url);

        return str_contains($html, 'href="'.$path.'"') ? 'past' : 'current';
    }

    /**
     * The date, and where it came from.
     *
     * The ruling's order: a date stated on the page wins, then the edition
     * year in the standfirst, then a year in the document's filename, then
     * the crawl date flagged unknown.
     *
     * @param  array<string, mixed>  $page
     * @return array{date: ?\Illuminate\Support\Carbon, source: string, unknown: bool}
     */
    private function dateFor(array $page): array
    {
        if (! empty($page['date_text'])) {
            $parsed = $this->parse($page['date_text']);

            if ($parsed !== null) {
                return ['date' => $parsed, 'source' => 'page', 'unknown' => false];
            }
        }

        $edition = Mapping::editionYear($page['summary'] ?? null);

        if ($edition !== null) {
            return ['date' => \Illuminate\Support\Carbon::create($edition, 1, 1), 'source' => 'page', 'unknown' => false];
        }

        foreach ($page['documents'] as $document) {
            $year = Mapping::yearInFilename($document);

            if ($year !== null) {
                return ['date' => \Illuminate\Support\Carbon::create($year, 1, 1), 'source' => 'filename', 'unknown' => false];
            }
        }

        $fetched = $this->parse($page['fetched_at'] ?? null) ?? now();

        return ['date' => $fetched, 'source' => 'crawl', 'unknown' => true];
    }

    /**
     * @param  array{sector: ?string, from_title: bool}  $sector
     * @param  array{date: ?\Illuminate\Support\Carbon, source: string, unknown: bool}  $date
     */
    private function note(array $sector, array $date, string $originalTitle): ?string
    {
        $notes = [];

        if ($sector['from_title']) {
            $this->flags['sector']++;
            $notes[] = 'The title names the '.$sector['sector'].' sector and the legacy category did not. The title won.';
        }

        if ($date['unknown']) {
            $this->flags['date']++;
            $notes[] = 'No date on the page and no year in a filename. The crawl date stands in.';
        }

        if (TitleCase::wasRecased($originalTitle)) {
            $this->flags['title']++;
            $notes[] = 'Headline re-cased from capitals.';
        }

        return $notes === [] ? null : Str::limit(implode(' ', $notes), 490);
    }

    private function record(
        string $url,
        ?string $category,
        ?object $recordable,
        ?string $newPath,
        bool $needsReview,
        ?string $note,
        string $dateSource,
    ): void {
        LegacyRecord::updateOrCreate(
            ['legacy_url' => $url],
            [
                'legacy_category' => $category,
                'recordable_type' => $recordable?->getMorphClass(),
                'recordable_id' => $recordable?->getKey(),
                'new_path' => $newPath,
                'needs_review' => $needsReview,
                'review_note' => $note,
                'date_source' => $dateSource,
                'imported_at' => now(),
            ]
        );
    }

    /** @param array<string, mixed> $page */
    private function attachDocuments(object $owner, array $page): void
    {
        foreach ($page['documents'] as $url) {
            $asset = $this->assets['documents'][$url] ?? null;

            if ($asset === null) {
                continue;
            }

            Document::updateOrCreate(
                [
                    'documentable_type' => $owner->getMorphClass(),
                    'documentable_id' => $owner->getKey(),
                    'original_filename' => $asset['original_filename'],
                ],
                [
                    'file_path' => $asset['path'],
                    'mime_type' => $asset['mime_type'],
                    'size_bytes' => $asset['size_bytes'],
                    'locale' => 'en',
                    'clearance' => 'public',
                    'is_sample' => false,
                ]
            );
        }
    }

    /** @param array<string, mixed> $page */
    private function attachImages(object $owner, array $page): void
    {
        foreach ($page['images'] as $url) {
            $asset = $this->assets['images'][$url] ?? null;

            if ($asset === null) {
                continue;
            }

            MediaAsset::updateOrCreate(
                ['source_url' => $url],
                [
                    'disk' => 'public',
                    'path' => $asset['path'],
                    'original_name' => $asset['original_filename'],
                    'mime_type' => $asset['mime_type'],
                    'size_bytes' => $asset['size_bytes'],
                    'width' => $asset['width'],
                    'height' => $asset['height'],
                    'checksum' => hash('sha256', $url),
                    'renditions' => Renditions::fromManifest($asset['renditions'] ?? [], $asset['path'], $asset['width'] ?? null),
                    'rights_holder' => 'CRASA',
                    'licence' => "The client's own material, from crasa.org",
                    'is_sample' => false,
                ]
            );
        }
    }

    /** @param array<string, mixed> $page */
    private function heroPath(array $page): ?string
    {
        $hero = $page['hero_image'] ?? null;

        if ($hero !== null && isset($this->assets['images'][$hero])) {
            return $this->assets['images'][$hero]['path'];
        }

        foreach ($page['images'] as $image) {
            if (isset($this->assets['images'][$image])) {
                return $this->assets['images'][$image]['path'];
            }
        }

        return null;
    }

    /**
     * The rich text, with Webflow's wrapper and its empty paragraphs gone.
     *
     * Webflow writes a zero-width non-joiner into an empty paragraph, which
     * renders as a blank line the editor cannot see or delete.
     */
    private function body(string $html): string
    {
        $inner = preg_replace('/^<div[^>]*>|<\/div>$/', '', trim($html)) ?? $html;
        $inner = str_replace("\u{200C}", '', $inner);
        $inner = preg_replace('/<p>\s*<\/p>/', '', $inner) ?? $inner;

        return trim($inner);
    }

    private function uniqueSlug(string $model, string $title, string $url): string
    {
        $base = Mapping::slugFor($title, $url);

        $existing = LegacyRecord::query()->where('legacy_url', $url)->first();

        if ($existing?->recordable_id !== null && $existing->recordable_type === (new $model)->getMorphClass()) {
            $kept = $this->anyOf($model)->whereKey($existing->recordable_id)->value('slug');

            if ($kept !== null) {
                return $kept;
            }
        }

        $slug = $base;
        $suffix = 2;

        while ($this->anyOf($model)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * A query over every row of a model, soft-deleted included.
     *
     * Meetings do not soft-delete and publications do, so a bare
     * withTrashed() is a fatal error on one of them.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function anyOf(string $model): \Illuminate\Database\Eloquent\Builder
    {
        $query = $model::query();

        return in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true)
            ? $query->withTrashed()
            : $query;
    }

    private function parse(?string $value): ?\Illuminate\Support\Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function read(string $path): ?array
    {
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $decoded = json_decode(Storage::disk('local')->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function count(string $key): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    private function report(): void
    {
        $this->line('');
        ksort($this->counts);

        foreach ($this->counts as $key => $count) {
            $this->line(sprintf('  %-34s %d', $key, $count));
        }

        $this->line('');
        $this->line(sprintf('  %-34s %d', 'flagged: sector from the title', $this->flags['sector']));
        $this->line(sprintf('  %-34s %d', 'flagged: no date anywhere', $this->flags['date']));
        $this->line(sprintf('  %-34s %d', 'flagged: headline re-cased', $this->flags['title']));
    }
}

/** Thrown to roll back a dry run. Never escapes the command. */
class DryRun extends \RuntimeException {}
