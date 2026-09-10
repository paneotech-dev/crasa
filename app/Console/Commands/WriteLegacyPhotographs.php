<?php

namespace App\Console\Commands;

use App\Support\Legacy\TitleCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The photograph write-up, WS-12 and the image brief.
 *
 * WHY A COMMAND AND NOT A HAND-WRITTEN DOCUMENT. Sixty-five photographs,
 * each with a size, a source page and a subject. A hand list goes stale the
 * first time the crawl is re-run, and the one thing a picture library must
 * not do is claim an image is there when it is not.
 *
 * WHAT INSTITUTIONAL MEANS HERE. The brief: anything showing a CRASA
 * meeting, delegates, the Chairperson, a host minister or a signing. Those
 * are the subjects the platform is short of, because the accepted library
 * has one boardroom photograph and screens 02, 04 and 11 all want one. They
 * are copied into institutional/ so the slot has candidates, and no
 * photograph is placed: what a picture shows is a judgement the Secretariat
 * makes, and a wrong face on a hero band is a real problem on a treaty
 * body's homepage.
 */
class WriteLegacyPhotographs extends Command
{
    protected $signature = 'legacy:photographs';

    protected $description = 'Describe the imported photographs, copy the institutional ones and record their licences';

    private const INSTITUTIONAL_ROOT = 'images/legacy/institutional';

    /** Words in a source page's title that make a photograph institutional. */
    private const INSTITUTIONAL_WORDS = [
        'meeting', 'agm', 'annual general', 'committee', 'exco', 'summit',
        'chairperson', 'chairpersonship', 'courtesy', 'visit', 'minister',
        'signing', 'sign ', 'signs ', 'memorandum', 'mou', 'workshop',
        'delegat', 'forum', 'assumes', 'appoint', 'secretary', 'conference',
    ];

    public function handle(): int
    {
        $manifest = json_decode(Storage::disk('local')->get('legacy/assets.json'), true);

        if (! is_array($manifest)) {
            $this->error('No asset manifest. Run legacy:assets first.');

            return self::FAILURE;
        }

        $photographs = [];
        $this->pages = json_decode(Storage::disk('local')->get('legacy/content.json'), true)['pages'] ?? [];

        foreach ($manifest['images'] as $url => $asset) {
            $asset = $this->attribute($url, $asset);
            $subject = $this->subject($asset);
            $institutional = $this->isInstitutional($asset, $subject);

            if ($institutional) {
                $asset['institutional_path'] = $this->copyToInstitutional($asset);
            }

            $photographs[] = $asset + [
                'source_url' => $url,
                'subject' => $subject,
                'institutional' => $institutional,
                'slot' => $this->slotFor($asset, $institutional),
                'megapixels' => round((($asset['width'] ?? 0) * ($asset['height'] ?? 0)) / 1_000_000, 1),
            ];
        }

        usort($photographs, fn (array $a, array $b) => ($b['megapixels'] <=> $a['megapixels']));

        $this->writeDocument($photographs, $manifest['skipped'] ?? []);
        $this->writeLicences($photographs);

        $institutionalCount = count(array_filter($photographs, fn (array $p) => $p['institutional']));

        $this->info(sprintf(
            '%d photographs described, %d copied into institutional/.',
            count($photographs),
            $institutionalCount
        ));

        return self::SUCCESS;
    }

    /** @var array<string, mixed> */
    private array $pages = [];

    /**
     * The page a photograph is actually about.
     *
     * The crawl records the first page an image was seen on, and a listing
     * page is reached before the articles it lists. That put one article's
     * headline against a dozen unrelated photographs, which in a document
     * whose whole job is to say what each picture shows is worse than
     * saying nothing. An article page that carries the same image wins over
     * a listing page.
     *
     * @param  array<string, mixed>  $asset
     * @return array<string, mixed>
     */
    private function attribute(string $url, array $asset): array
    {
        $found = (string) ($asset['found_on'] ?? '');

        if (! $this->isListing($found)) {
            return $asset;
        }

        foreach ($this->pages as $pageUrl => $page) {
            if ($this->isListing($pageUrl)) {
                continue;
            }

            if (in_array($url, $page['images'] ?? [], true)) {
                return array_merge($asset, [
                    'found_on' => $pageUrl,
                    'page_title' => $page['title'] ?? $asset['page_title'] ?? null,
                ]);
            }
        }

        // Seen only on a listing. Saying so is more useful than naming
        // whichever article happened to be at the top of it.
        return array_merge($asset, ['page_title' => null, 'listing_only' => true]);
    }

    private function isListing(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        return ! str_contains($path, '/post/')
            && ! str_contains($path, '/post-articles/')
            && ! str_contains($path, '/project/');
    }
    /** @param array<string, mixed> $asset */
    private function subject(array $asset): string
    {
        $title = (string) ($asset['page_title'] ?? '');

        // The page titles are Webflow's, so they carry the site name.
        $title = trim(preg_replace('/^CRASA\s*(Publications|Blog)?\s*\|\s*/i', '', $title) ?? $title);

        if ($title !== '') {
            return TitleCase::apply($title);
        }

        $name = TitleCase::apply(pathinfo($asset['original_filename'], PATHINFO_FILENAME));

        return ($asset['listing_only'] ?? false)
            ? $name.' (seen only on a listing page; the article it belongs to is not recorded)'
            : $name;
    }

    /** @param array<string, mixed> $asset */
    private function isInstitutional(array $asset, string $subject): bool
    {
        $haystack = Str::lower($subject.' '.$asset['original_filename']);

        foreach (self::INSTITUTIONAL_WORDS as $word) {
            if (str_contains($haystack, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $asset
     * @return array{slot: string, why: string}
     */
    private function slotFor(array $asset, bool $institutional): array
    {
        $width = (int) ($asset['width'] ?? 0);
        $height = (int) ($asset['height'] ?? 0);
        $landscape = $height > 0 && ($width / $height) >= 1.3;

        if ($institutional && $landscape && $width >= 1920) {
            return ['slot' => 'hero or institutional', 'why' => 'Institutional subject, landscape, wide enough for the band at 1920.'];
        }

        if ($institutional) {
            return ['slot' => 'institutional', 'why' => $landscape
                ? 'Institutional subject but narrower than the band needs.'
                : 'Institutional subject, not landscape. A card or an article hero.'];
        }

        if ($landscape && $width >= 1440) {
            return ['slot' => 'article hero', 'why' => 'Landscape and large enough for a full-width article hero.'];
        }

        return ['slot' => 'article or card', 'why' => 'Sized for a card rather than a band.'];
    }

    /** @param array<string, mixed> $asset */
    private function copyToInstitutional(array $asset): string
    {
        $target = self::INSTITUTIONAL_ROOT.'/'.basename($asset['path']);

        if (! Storage::disk('public')->exists($target)) {
            Storage::disk('public')->put($target, Storage::disk('public')->get($asset['path']));
        }

        return $target;
    }

    /**
     * @param  list<array<string, mixed>>  $photographs
     * @param  array<string, mixed>  $skipped
     */
    private function writeDocument(array $photographs, array $skipped): void
    {
        $institutional = array_values(array_filter($photographs, fn (array $p) => $p['institutional']));
        $rest = array_values(array_filter($photographs, fn (array $p) => ! $p['institutional']));
        $candidates = $this->heroCandidates($institutional);

        $lines = [];
        $lines[] = '# Legacy photographs — WS-12';
        $lines[] = '';
        $lines[] = '*Written by `php artisan legacy:photographs`. Re-run it after any change';
        $lines[] = 'to the crawl; do not edit by hand.*';
        $lines[] = '';
        $lines[] = sprintf(
            'Sixty-five is what came back. %d photographs downloaded at the largest size the legacy',
            count($photographs)
        );
        $lines[] = 'CDN holds, EXIF stripped, derivatives built at 480, 960, 1440 and 1920.';
        $lines[] = sprintf('%d of them are institutional and are copied into `%s` as well.', count($institutional), self::INSTITUTIONAL_ROOT);
        $lines[] = '';
        $lines[] = 'Every one is CRASA\'s own material, from crasa.org. None is placed on any page.';
        $lines[] = 'What a photograph shows is a judgement the Secretariat makes, and a wrong face on';
        $lines[] = 'a hero band is a real problem on a treaty body\'s homepage.';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## The hero recommendation';
        $lines[] = '';
        $lines[] = '**None of them. Keep the supplied hero.**';
        $lines[] = '';
        $lines[] = 'Screen 02\'s band wants a landscape photograph of at least 1920 across, an';
        $lines[] = 'institutional subject, and a quiet third at one end for the headline to sit';
        $lines[] = 'over. Two of the sixty-five clear the measurements. Both were opened and';
        $lines[] = 'neither works, for reasons no size check can see:';
        $lines[] = '';

        foreach (array_slice($candidates, 0, 2) as $candidate) {
            $lines[] = sprintf(
                '- **`%s`** (%d&times;%d) — %s',
                $candidate['original_filename'],
                $candidate['width'] ?? 0,
                $candidate['height'] ?? 0,
                $candidate['subject']
            );
        }

        $lines[] = '';
        $lines[] = 'The first is one speaker at a lectern, filmed centre-frame, with a CRAN pull-up';
        $lines[] = 'banner filling the left third. CRAN is one of the fourteen. A regional';
        $lines[] = 'association whose homepage opens on one member state\'s branding is making a';
        $lines[] = 'claim it does not mean to make, and the headline would have to sit on top of';
        $lines[] = 'that banner because it is the only clear area.';
        $lines[] = '';
        $lines[] = 'The second is a delegation line-up in BEREC\'s lobby in Europe, under the';
        $lines[] = 'European sign and beside an EU flag. IMAGE_BRIEF already holds two supplied';
        $lines[] = 'images for being photographed outside the region; this is the same problem and';
        $lines[] = 'the EU emblem makes it worse, because CLAUDE.md forbids showing that emblem';
        $lines[] = 'until CRASA supplies the wording that goes with it.';
        $lines[] = '';
        $lines[] = 'What the set is good for is the institutional slot and article heroes, which is';
        $lines[] = 'where it is short. Twenty-four photographs of CRASA meetings, signings and';
        $lines[] = 'courtesy calls is more institutional material than the platform has had.';
        $lines[] = '';
        $lines[] = '*This paragraph is a judgement about what the pictures show, not a';
        $lines[] = 'measurement. If the crawl is re-run and the candidates change, somebody has to';
        $lines[] = 'open them again.*';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = sprintf('## Institutional — %d', count($institutional));
        $lines[] = '';
        $lines[] = 'A CRASA meeting, delegates, the Chairperson, a host minister or a signing.';
        $lines[] = '';
        $lines[] = '| Photograph | Shows | Size | Could fill |';
        $lines[] = '|---|---|---|---|';

        foreach ($institutional as $photograph) {
            $lines[] = $this->row($photograph);
        }

        $lines[] = '';
        $lines[] = sprintf('## Everything else — %d', count($rest));
        $lines[] = '';
        $lines[] = '| Photograph | Shows | Size | Could fill |';
        $lines[] = '|---|---|---|---|';

        foreach ($rest as $photograph) {
            $lines[] = $this->row($photograph);
        }

        $lines[] = '';
        $lines[] = sprintf('## Not kept — %d', count($skipped));
        $lines[] = '';
        $lines[] = 'Site furniture and images too small to be a photograph. Recorded rather than';
        $lines[] = 'silently dropped, so the count adds up.';
        $lines[] = '';
        $lines[] = '| File | Why |';
        $lines[] = '|---|---|';

        foreach ($skipped as $asset) {
            $lines[] = sprintf('| `%s` | %s |', $asset['name'] ?? '?', $asset['reason'] ?? '?');
        }

        $lines[] = '';

        file_put_contents(base_path('docs/LEGACY_PHOTOGRAPHS.md'), implode("\n", $lines)."\n");
    }

    /** @param array<string, mixed> $photograph */
    private function row(array $photograph): string
    {
        return sprintf(
            '| `%s` | %s | %d&times;%d | %s — %s |',
            $photograph['original_filename'],
            str_replace('|', '/', $photograph['subject']),
            $photograph['width'] ?? 0,
            $photograph['height'] ?? 0,
            $photograph['slot']['slot'],
            $photograph['slot']['why']
        );
    }

    /**
     * The photographs that clear the band's measurements, largest first.
     *
     * Measurements are all this can decide. Whether a picture is right for
     * the homepage of a treaty body is a judgement about what it shows,
     * which is why the document carries a written assessment beside this
     * list rather than naming a winner.
     *
     * @param  list<array<string, mixed>>  $institutional
     * @return list<array<string, mixed>>
     */
    private function heroCandidates(array $institutional): array
    {
        return array_values(array_filter($institutional, function (array $photograph): bool {
            $width = (int) ($photograph['width'] ?? 0);
            $height = (int) ($photograph['height'] ?? 0);

            return $width >= 1920 && $height > 0 && ($width / $height) >= 1.3;
        }));
    }

    /** @param list<array<string, mixed>> $photographs */
    private function writeLicences(array $photographs): void
    {
        $path = base_path('docs/LICENCES.csv');
        $existing = file($path, FILE_IGNORE_NEW_LINES) ?: [];

        // Rows already written by an earlier run are replaced, not doubled.
        $kept = array_values(array_filter(
            $existing,
            fn (string $line) => ! str_contains($line, 'images/legacy/')
        ));

        $handle = fopen($path, 'w');

        foreach ($kept as $line) {
            fwrite($handle, $line."\n");
        }

        foreach ($photographs as $photograph) {
            fputcsv($handle, [
                'Legacy photograph: '.$photograph['original_filename'],
                $photograph['path'],
                'crasa.org',
                '',
                "The client's own material",
                'No',
                now()->toDateString(),
                sprintf(
                    '%s. %dx%d. Downloaded at the largest size the legacy CDN holds, EXIF stripped. Not placed on any page.',
                    str_replace('"', "'", $photograph['subject']),
                    $photograph['width'] ?? 0,
                    $photograph['height'] ?? 0
                ),
            ], ',', '"', '\\');
        }

        fclose($handle);
    }
}
