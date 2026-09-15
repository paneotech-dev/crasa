<?php

namespace App\Console\Commands;

use App\Models\Authority;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Normalizer;
use ZipArchive;

/**
 * The fourteen member states' own marks, from CRASA's own site.
 *
 * WHY THESE ARE WORTH IMPORTING AND THE REST OF THE ARCHIVE IS NOT. The
 * scraped archive holds 108 distinct images, and 65 of them are photographs
 * the legacy import already downloaded, byte for byte. What it adds is the
 * complete set of national regulator logos: nine that the earlier run filed
 * as ordinary photographs because they are named for their authorities, and
 * five it dropped because its chrome filter rejects any filename containing
 * "logo". All fourteen `authorities.logo_path` columns are null.
 *
 * THE ACRONYM IS THE IDENTIFIER, NOT THE FILENAME. Ten files carry the
 * authority's acronym, which is what the database holds and is unambiguous.
 * The other four carry the full name and are matched on a folded comparison
 * of it. A tie or a miss refuses the whole run rather than guessing: a
 * regulator's mark against the wrong country is worse than no mark.
 *
 * DECOMPOSED UNICODE IS WHY FOLDING EXISTS. Nine of the filenames are in
 * NFD: "Communicaço̧es" is a c followed by a combining cedilla, not a ç. A
 * literal comparison fails on every one of them.
 *
 * IT NEVER OVERWRITES. An authority whose logo_path is already set is
 * skipped and said so, because that value may have been uploaded by the
 * member state itself through the authority profile form, and a scrape of
 * an old site must not win against a regulator's own upload.
 *
 * THE LICENCE IS NOT SETTLED. These are fourteen other organisations'
 * registered trademarks. CRASA publishes them on its own About page, which
 * is not the same permission as this platform publishing them. Every row in
 * docs/LICENCES.csv says so and go-live needs CRASA's confirmation.
 */
class ImportAuthorityLogos extends Command
{
    protected $signature = 'authorities:import-logos
                            {--dry-run : Report the mapping and write nothing}
                            {--source= : A directory of logo files, instead of the archive}';

    protected $description = 'Import the fourteen member state authority logos from the scraped archive';

    private const ARCHIVE = 'images/scraped-assets.zip';

    private const TARGET_ROOT = 'images/authorities';

    /** Where in the archive the site assets live. */
    private const BUCKET = 'cdn.prod.website-files.com/5fb8ce4afaee58b9d9393635/';

    public function handle(): int
    {
        $candidates = $this->candidates();

        if ($candidates === null) {
            return self::FAILURE;
        }

        $this->line(sprintf('%d candidate files in the source.', count($candidates)));

        $plan = [];

        foreach (Authority::with('country')->get()->sortBy(fn (Authority $a) => $a->country?->iso_code) as $authority) {
            $match = $this->match($authority, $candidates);

            if ($match === null) {
                $this->error(sprintf(
                    'No logo matched %s (%s). Nothing has been written.',
                    $authority->acronym ?: $authority->name,
                    $authority->country?->iso_code ?? '??'
                ));

                return self::FAILURE;
            }

            if ($match === 'ambiguous') {
                $this->error(sprintf(
                    'More than one file could be %s (%s). Nothing has been written.',
                    $authority->acronym ?: $authority->name,
                    $authority->country?->iso_code ?? '??'
                ));

                return self::FAILURE;
            }

            $plan[] = $match + ['authority' => $authority];
        }

        $this->report($plan);

        if ($this->option('dry-run')) {
            $this->line('');
            $this->warn('Dry run. Nothing has been written.');

            return self::SUCCESS;
        }

        return $this->write($plan);
    }

    /**
     * Every file that could be a logo, folded for comparison.
     *
     * Read out of the archive by default, so the command depends on the
     * thing that was delivered rather than on a temporary extraction that
     * somebody may have tidied away. The archive is opened read-only and
     * is never modified.
     *
     * @return array<string, array{name: string, folded: string, bytes: string}>|null
     */
    private function candidates(): ?array
    {
        $source = $this->option('source');

        return $source === null ? $this->fromArchive() : $this->fromDirectory($source);
    }

    /** @return array<string, array{name: string, folded: string, bytes: string}>|null */
    private function fromArchive(): ?array
    {
        $path = Storage::disk('public')->path(self::ARCHIVE);

        if (! is_file($path)) {
            $this->error('The archive is not at '.self::ARCHIVE.'.');

            return null;
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            $this->error('The archive could not be opened.');

            return null;
        }

        $candidates = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            if (! str_contains($entry, self::BUCKET) || ! $this->isCandidate(basename($entry))) {
                continue;
            }

            $name = basename($entry);
            $candidates[$name] = [
                'name' => $name,
                'folded' => $this->fold($this->bare($name)),
                'bytes' => (string) $zip->getFromIndex($i),
            ];
        }

        $zip->close();

        return $candidates;
    }

    /** @return array<string, array{name: string, folded: string, bytes: string}>|null */
    private function fromDirectory(string $source): ?array
    {
        if (! is_dir($source)) {
            $this->error('No such directory: '.$source);

            return null;
        }

        $candidates = [];

        foreach (scandir($source) ?: [] as $name) {
            if (! is_file($source.'/'.$name) || ! $this->isCandidate($name)) {
                continue;
            }

            $candidates[$name] = [
                'name' => $name,
                'folded' => $this->fold($this->bare($name)),
                'bytes' => (string) file_get_contents($source.'/'.$name),
            ];
        }

        return $candidates;
    }

    /**
     * An image that is not one of Webflow's derivatives.
     *
     * The `-p-500`, `-p-800` and `-p-1080` copies are Webflow's own resizes
     * of the file beside them. Taking one would import a copy of a copy.
     */
    private function isCandidate(string $name): bool
    {
        return preg_match('/\.(png|jpe?g|svg)$/i', $name) === 1
            && preg_match('/-p-\d+\.[a-z]+$/i', $name) !== 1;
    }

    /** Webflow prefixes every upload with a hex id that is not part of the name. */
    private function bare(string $name): string
    {
        return preg_replace('/^[0-9a-f]{16,}_/i', '', pathinfo($name, PATHINFO_FILENAME)) ?? $name;
    }

    /**
     * Case-folded, accent-stripped, punctuation-free.
     *
     * Normalised to NFC first, because nine of these filenames are in NFD
     * and a c followed by a combining cedilla is not the same string as ç.
     */
    private function fold(string $value): string
    {
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($value)) ?? $value);
    }

    /**
     * The file that belongs to this authority.
     *
     * The acronym first: it is the identifier the database holds, it is
     * matched as a whole word, and it is unambiguous. The full name is the
     * fallback for the four files that carry no acronym. Either way, two
     * matches refuse rather than pick.
     *
     * @param  array<string, array{name: string, folded: string, bytes: string}>  $candidates
     * @return array{file: string, how: string, bytes: string}|string|null
     */
    private function match(Authority $authority, array $candidates): array|string|null
    {
        $acronym = $this->fold((string) $authority->acronym);

        if ($acronym !== '') {
            $hits = array_filter(
                $candidates,
                fn (array $c) => preg_match('/(^| )'.preg_quote($acronym, '/').'( |$)/', $c['folded']) === 1
            );

            if (count($hits) > 1) {
                return 'ambiguous';
            }

            if (count($hits) === 1) {
                $hit = reset($hits);

                return ['file' => $hit['name'], 'how' => 'acronym', 'bytes' => $hit['bytes']];
            }
        }

        $name = $this->fold((string) $authority->name);
        $best = null;
        $bestScore = 0.0;
        $tied = false;

        foreach ($candidates as $candidate) {
            similar_text($candidate['folded'], $name, $percent);

            if ($percent < 82) {
                continue;
            }

            if (abs($percent - $bestScore) < 0.01 && $best !== null) {
                $tied = true;
            }

            if ($percent > $bestScore) {
                $bestScore = $percent;
                $best = $candidate;
                $tied = false;
            }
        }

        if ($tied) {
            return 'ambiguous';
        }

        return $best === null
            ? null
            : ['file' => $best['name'], 'how' => sprintf('name %d%%', (int) $bestScore), 'bytes' => $best['bytes']];
    }

    /** @param list<array<string, mixed>> $plan */
    private function report(array $plan): void
    {
        $this->line('');
        $this->table(
            ['ISO', 'Acronym', 'Matched by', 'Source file', 'Target', 'Action'],
            array_map(function (array $row): array {
                $authority = $row['authority'];

                return [
                    $authority->country?->iso_code ?? '??',
                    $authority->acronym,
                    $row['how'],
                    mb_strimwidth($this->bare($row['file']), 0, 46, '...'),
                    $this->target($row),
                    $this->action($row),
                ];
            }, $plan)
        );
    }

    /** @param array<string, mixed> $row */
    private function target(array $row): string
    {
        $extension = strtolower(pathinfo($row['file'], PATHINFO_EXTENSION));
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        return self::TARGET_ROOT.'/'.$row['authority']->country?->iso_code.'.'.$extension;
    }

    /** @param array<string, mixed> $row */
    private function action(array $row): string
    {
        if ($row['authority']->logo_path !== null) {
            return 'skip: already set';
        }

        $target = $this->target($row);

        if (Storage::disk('public')->exists($target)
            && Storage::disk('public')->get($target) === $row['bytes']) {
            return 'record only';
        }

        return 'copy and record';
    }

    /** @param list<array<string, mixed>> $plan */
    private function write(array $plan): int
    {
        $copied = 0;
        $recorded = 0;
        $skipped = 0;

        foreach ($plan as $row) {
            $authority = $row['authority'];

            /*
             * A logo already on the record wins. It may have been uploaded
             * by the member state itself through the authority profile
             * form, and a scrape of the old site must not overwrite a
             * regulator's own file.
             */
            if ($authority->logo_path !== null) {
                $this->line(sprintf('  %s: already set, left alone.', $authority->acronym));
                $skipped++;

                continue;
            }

            $target = $this->target($row);

            if (str_ends_with($target, '.svg') && ! $this->svgIsSafe($row['bytes'])) {
                $this->error(sprintf('  %s: the SVG carries script and was not copied.', $authority->acronym));
                $skipped++;

                continue;
            }

            // Nothing is written if the bytes on disk are already these
            // bytes, so a second run touches no file.
            if (! Storage::disk('public')->exists($target)
                || Storage::disk('public')->get($target) !== $row['bytes']) {
                Storage::disk('public')->put($target, $row['bytes']);
                $copied++;
            }

            $authority->forceFill(['logo_path' => $target])->save();
            $recorded++;
        }

        $this->line('');
        $this->info(sprintf(
            '%d files copied, %d records updated, %d left alone.',
            $copied,
            $recorded,
            $skipped
        ));

        return self::SUCCESS;
    }

    /**
     * An SVG that carries no script.
     *
     * SVG is a document format, and one served from the public disk runs
     * whatever it contains in the reader's browser on this platform's own
     * origin. These came off somebody else's site, so they are checked
     * rather than trusted.
     */
    private function svgIsSafe(string $bytes): bool
    {
        return preg_match(
            '/<\s*script|javascript\s*:|\son\w+\s*=|<\s*foreignObject|<!ENTITY|xlink:href\s*=\s*["\']\s*(?!#)/i',
            $bytes
        ) !== 1;
    }
}
