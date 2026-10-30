<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\Instrument;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\PageBlock;
use App\Models\Project;
use App\Models\Publication;
use App\Services\ContentRenderer;
use App\Support\PlaceholderContent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * What the renderer does to every stored value, without changing any of it.
 *
 * READ ONLY, AND THAT IS THE POINT. The fix is at the rendering layer: the
 * database keeps the source, so the Secretariat can still read what came
 * across and a later improvement to the renderer fixes every record with no
 * second pass. This command is how somebody sees the effect of that
 * decision without taking it on trust.
 *
 * It writes one report to the private disk. Nothing it produces is served.
 */
class AuditContent extends Command
{
    protected $signature = 'content:audit
                            {--full : Include the rendered output of every record, not just the summary}';

    protected $description = 'Report what the content renderer does to every stored value. Changes nothing.';

    /** @var array<class-string<Model>, list<string>> */
    private const FIELDS = [
        NewsItem::class => ['body', 'summary'],
        Project::class => ['body', 'summary'],
        Publication::class => ['summary'],
        Instrument::class => ['summary', 'body'],
        PageBlock::class => ['body', 'intro'],
        Announcement::class => ['body'],
    ];

    public function handle(ContentRenderer $renderer): int
    {
        $report = [
            'ran_at' => now()->toIso8601String(),
            'note' => 'Read only. No database row was written.',
            'records' => [],
            'summary' => [],
        ];

        $changed = 0;
        $withImages = [];
        $shortcodes = [];
        $filler = [];
        $total = 0;

        foreach (self::FIELDS as $model => $fields) {
            foreach ($model::query()->get() as $record) {
                foreach ($fields as $field) {
                    if (! in_array($field, $this->translatableOf($record), true)) {
                        continue;
                    }

                    foreach (\App\Support\Locales::SUPPORTED as $locale) {
                        $stored = $record->translated($field, '', $locale);

                        if (trim($stored) === '') {
                            continue;
                        }

                        $total++;
                        $rendered = $renderer->render($stored)->toHtml();

                        $entry = $this->compare($record, $field, $locale, $stored, $rendered);

                        if ($entry === null) {
                            continue;
                        }

                        $changed++;

                        if ($entry['images_dropped'] > 0 || $entry['images_localised'] > 0) {
                            $withImages[] = $entry['record'];
                        }

                        foreach ($entry['shortcodes'] as $tag) {
                            $shortcodes[$tag] = ($shortcodes[$tag] ?? 0) + 1;
                        }

                        $report['records'][] = $this->option('full')
                            ? $entry + ['stored' => $stored, 'rendered' => $rendered]
                            : $entry;
                    }
                }
            }
        }

        // Meeting agendas are a plain column rather than a translation.
        foreach (Meeting::whereNotNull('agenda')->get() as $meeting) {
            $total++;
            $stored = (string) $meeting->agenda;

            if (PlaceholderContent::isFiller($stored)) {
                $filler[] = ['id' => $meeting->id, 'slug' => $meeting->slug, 'title' => $meeting->title];

                continue;
            }

            $rendered = $renderer->render($stored, 'meeting:'.$meeting->id)->toHtml();
            $entry = $this->compare($meeting, 'agenda', 'n/a', $stored, $rendered);

            if ($entry === null) {
                continue;
            }

            $changed++;

            foreach ($entry['shortcodes'] as $tag) {
                $shortcodes[$tag] = ($shortcodes[$tag] ?? 0) + 1;
            }

            $report['records'][] = $this->option('full') ? $entry + ['stored' => $stored, 'rendered' => $rendered] : $entry;
        }

        $report['summary'] = [
            'values_inspected' => $total,
            'values_the_renderer_changes' => $changed,
            'records_with_images' => array_values(array_unique($withImages)),
            'shortcodes_found' => $shortcodes,
            'placeholder_agendas' => $filler,
        ];

        $path = 'content-audit/'.now()->format('Y-m-d-His').'.json';
        Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->line('');
        $this->line(sprintf('  %-46s %d', 'stored values inspected', $total));
        $this->line(sprintf('  %-46s %d', 'values the renderer changes', $changed));
        $this->line(sprintf('  %-46s %d', 'records carrying images', count(array_unique($withImages))));
        $this->line(sprintf('  %-46s %d', 'agendas held back as placeholder', count($filler)));

        if ($shortcodes !== []) {
            $this->line('');
            $this->line('  shortcodes removed:');

            foreach ($shortcodes as $tag => $count) {
                $this->line(sprintf('    %-24s %d', $tag, $count));
            }
        }

        if ($filler !== []) {
            $this->line('');
            $this->line('  agendas showing a placeholder instead of theme filler:');

            foreach ($filler as $m) {
                $this->line(sprintf('    #%-5d %-46s %s', $m['id'], $m['slug'], $m['title']));
            }
        }

        $this->line('');
        $this->info('Report written to storage/app/private/'.$path.'. No database row was written.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function translatableOf(Model $record): array
    {
        return method_exists($record, 'translatableFields')
            ? $record->translatableFields()
            : (fn () => $this->translatable ?? [])->call($record);
    }

    /**
     * What the renderer did, or null when it did nothing worth reporting.
     *
     * @return array<string, mixed>|null
     */
    private function compare(Model $record, string $field, string $locale, string $stored, string $rendered): ?array
    {
        preg_match_all('/\[(vc_[a-z0-9_]*)/i', $stored, $codes);
        preg_match_all('/<img[^>]+src="([^"]+)"/i', $stored, $before);
        preg_match_all('/<img[^>]+src="([^"]+)"/i', $rendered, $after);

        $shortcodes = array_values(array_unique(array_map('strtolower', $codes[1])));
        $dropped = count($before[1]) - count($after[1]);

        $textBefore = trim(preg_replace('/\s+/', ' ', strip_tags($stored)) ?? '');
        $textAfter = trim(preg_replace('/\s+/', ' ', strip_tags((string) preg_replace('#<caption class="c-sr">.*?</caption>#is', '', $rendered))) ?? '');

        if ($shortcodes === [] && $dropped === 0 && $stored === $rendered) {
            return null;
        }

        return [
            'record' => class_basename($record).':'.$record->getKey(),
            'field' => $field,
            'locale' => $locale,
            'shortcodes' => $shortcodes,
            'images_localised' => count($after[1]),
            'images_dropped' => max(0, $dropped),
            'text_before' => mb_strlen($textBefore),
            'text_after' => mb_strlen($textAfter),
        ];
    }
}
