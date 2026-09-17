<?php

namespace App\Console\Commands;

use App\Support\Legacy\Mapping;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Throwable;

/**
 * The legacy site's documents and photographs, downloaded. WS-12.
 *
 * TWO DESTINATIONS, FOR ONE REASON. Documents go to the local disk, which
 * is outside the web root, because DATA_MODEL section 3 forbids serving a
 * document by filesystem URL: every download goes through a controller that
 * checks role and country. Photographs go to the public disk, because an
 * image on a published page is public by definition and routing every one
 * through PHP would put the whole picture library behind the framework.
 *
 * LARGEST SIZE, ALWAYS. Webflow serves a derivative ladder and the `src`
 * attribute often points at a 500-pixel copy. The parser already reads the
 * srcset and returns the widest candidate, which is the original upload.
 * Anything narrower is a copy of a copy and cannot be re-enlarged.
 *
 * EXIF IS STRIPPED. A photograph taken at a CRASA meeting can carry the
 * photographer's device, and GPS coordinates of a room in a ministry
 * building. Publishing that is a disclosure nobody asked for. Imagick's
 * stripImage removes every profile; the ICC profile is put back, because
 * dropping it shifts colour.
 *
 * ONE REQUEST A SECOND against the client's CDN, same as the crawl, and
 * resumable for the same reason: an asset already on disk is not fetched
 * again.
 */
class FetchLegacyAssets extends Command
{
    protected $signature = 'legacy:assets
                            {--only= : documents or images}
                            {--seconds=1 : Seconds between requests}
                            {--timeout=120 : Seconds to wait for one response}';

    protected $description = 'Download the legacy documents and photographs, strip EXIF and build derivatives';

    private const AGENT = 'CRASA-migration/1.0 (+website replacement for the CRASA Secretariat; contact crasa@crasa.org)';

    private const MANIFEST = 'legacy/assets.json';

    private const DOCUMENT_ROOT = 'legacy/documents';

    private const IMAGE_ROOT = 'images/legacy';

    /** Widths matching the rest of the image library. */
    private const WIDTHS = [480, 960, 1440, 1920];

    /**
     * Chrome, not content. Webflow's own furniture appears inside the
     * page body as well as in the navigation, and a run that keeps it
     * fills the library with breadcrumb arrows.
     */
    private const CHROME = ['icon-', 'favicon', 'webclip', 'np_', 'logo', 'placeholder', '-thumbnail'];

    /**
     * Article pictures that look like chrome. Each is the only illustration
     * of a news item (a partnership lock-up, a committee banner), so the
     * filename and size filters would leave the article with a gap. Named
     * one by one, after the Secretariat's own material was checked against
     * the article it sits in; see docs/LICENCES.csv. A name here is kept
     * even if an earlier run recorded it as skipped.
     */
    public const ARTICLE_CONTENT = [
        'CRASA Logo - High Resolution.jpg',
        'CRASA LOGS.png',
        'Doc2.png',
        '66daaea809fb08282926d972_LOGOS.png',
    ];

    /** @var array<string, mixed> */
    private array $manifest;

    public function handle(): int
    {
        $content = $this->read('legacy/content.json');

        if ($content === null) {
            $this->error('No content manifest. Run legacy:content first.');

            return self::FAILURE;
        }

        $this->manifest = $this->read(self::MANIFEST) ?? ['documents' => [], 'images' => [], 'skipped' => []];

        $only = $this->option('only');

        if ($only === null || $only === 'documents') {
            $this->documents($content['pages']);
        }

        if ($only === null || $only === 'images') {
            $this->images($content['pages']);
        }

        $this->save();

        $this->line('');
        $this->info(sprintf(
            '%d documents and %d photographs on disk. %d skipped as chrome.',
            count($this->manifest['documents']),
            count($this->manifest['images']),
            count($this->manifest['skipped'])
        ));

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $pages */
    private function documents(array $pages): void
    {
        $this->line('Documents.');

        foreach ($pages as $url => $page) {
            foreach ($page['documents'] as $document) {
                if (isset($this->manifest['documents'][$document])) {
                    continue;
                }

                $original = Mapping::originalFilename($document);
                $path = self::DOCUMENT_ROOT.'/'.sha1($document).'-'.Str::slug(pathinfo($original, PATHINFO_FILENAME), '-')
                    .'.'.strtolower(pathinfo($original, PATHINFO_EXTENSION) ?: 'pdf');

                $bytes = $this->fetch($document);

                if ($bytes === null) {
                    $this->warn('  failed: '.$original);

                    continue;
                }

                Storage::disk('local')->put($path, $bytes);

                $this->manifest['documents'][$document] = [
                    'path' => $path,
                    'original_filename' => $original,
                    'size_bytes' => strlen($bytes),
                    'mime_type' => $this->mime($original),
                    'found_on' => $url,
                    'year' => Mapping::yearInFilename($original),
                ];

                $this->line(sprintf('  %s (%s)', $original, $this->human(strlen($bytes))));
                $this->save();
                $this->pause();
            }
        }
    }

    /** @param array<string, mixed> $pages */
    private function images(array $pages): void
    {
        $this->line('');
        $this->line('Photographs.');

        foreach ($pages as $url => $page) {
            $folder = Str::slug(basename(parse_url($url, PHP_URL_PATH) ?: 'home')) ?: 'home';

            foreach ($page['images'] as $image) {
                $original = Mapping::originalFilename($image);
                $kept = in_array($original, self::ARTICLE_CONTENT, true);

                if (isset($this->manifest['images'][$image]) || (isset($this->manifest['skipped'][$image]) && ! $kept)) {
                    continue;
                }

                unset($this->manifest['skipped'][$image]);

                if (! $kept && ($this->isChrome($original) || str_ends_with(strtolower($original), '.svg'))) {
                    $this->manifest['skipped'][$image] = ['reason' => 'chrome', 'name' => $original];

                    continue;
                }

                $bytes = $this->fetch($image);

                if ($bytes === null) {
                    $this->warn('  failed: '.$original);

                    continue;
                }

                $path = self::IMAGE_ROOT.'/'.$folder.'/'.Str::slug(pathinfo($original, PATHINFO_FILENAME), '-')
                    .'.'.strtolower(pathinfo($original, PATHINFO_EXTENSION) ?: 'jpg');

                Storage::disk('public')->put($path, $bytes);

                $shape = $this->cleanAndMeasure(Storage::disk('public')->path($path));

                /*
                 * A logo or an icon that got past the filename filter shows
                 * up as a very small image. Recorded rather than deleted,
                 * because the write-up has to say what was seen.
                 */
                if (! $kept && (($shape['width'] ?? 0) < 320 || ($shape['height'] ?? 0) < 240)) {
                    $this->manifest['skipped'][$image] = [
                        'reason' => 'too small: '.($shape['width'] ?? 0).'x'.($shape['height'] ?? 0),
                        'name' => $original,
                        'path' => $path,
                    ];

                    $this->pause();

                    continue;
                }

                $this->manifest['images'][$image] = [
                    'path' => $path,
                    'original_filename' => $original,
                    'size_bytes' => strlen($bytes),
                    'mime_type' => $this->mime($original),
                    'found_on' => $url,
                    'page_title' => $page['title'] ?? null,
                    'width' => $shape['width'] ?? null,
                    'height' => $shape['height'] ?? null,
                    'renditions' => $this->derivatives($path),
                    'is_hero' => ($page['hero_image'] ?? null) === $image,
                ];

                $this->line(sprintf('  %s  %dx%d', $original, $shape['width'] ?? 0, $shape['height'] ?? 0));
                $this->save();
                $this->pause();
            }
        }
    }

    /** @return array{width?: int, height?: int} */
    private function cleanAndMeasure(string $absolute): array
    {
        try {
            $image = new Imagick($absolute);

            $profiles = $image->getImageProfiles('icc', true);
            $image->stripImage();

            if (isset($profiles['icc'])) {
                $image->profileImage('icc', $profiles['icc']);
            }

            $shape = ['width' => $image->getImageWidth(), 'height' => $image->getImageHeight()];
            $image->writeImage($absolute);
            $image->clear();

            return $shape;
        } catch (Throwable $e) {
            $this->warn('  could not read: '.basename($absolute).' ('.$e->getMessage().')');

            return [];
        }
    }

    /** @return array<string, string> */
    private function derivatives(string $path): array
    {
        $built = [];
        $absolute = Storage::disk('public')->path($path);

        foreach (self::WIDTHS as $width) {
            foreach (['jpg', 'webp'] as $extension) {
                $target = preg_replace('/\.[a-z0-9]+$/i', '', $path)."-{$width}.{$extension}";

                try {
                    $image = new Imagick($absolute);

                    if ($image->getImageWidth() < $width) {
                        $image->clear();

                        continue;
                    }

                    $image->setImageFormat($extension === 'webp' ? 'webp' : 'jpeg');
                    $image->setImageCompressionQuality($extension === 'webp' ? 80 : 82);
                    $image->resizeImage($width, 0, Imagick::FILTER_LANCZOS, 1);
                    $image->stripImage();

                    Storage::disk('public')->put($target, $image->getImageBlob());
                    $image->clear();

                    $built[$width.'.'.$extension] = $target;
                } catch (Throwable) {
                    // A derivative that cannot be built is not a reason to
                    // lose the original, which is already on disk.
                }
            }
        }

        return $built;
    }

    private function isChrome(string $name): bool
    {
        $lower = Str::lower($name);

        foreach (self::CHROME as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function mime(string $name): string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    private function human(int $bytes): string
    {
        return $bytes > 1_048_576
            ? round($bytes / 1_048_576, 1).' MB'
            : round($bytes / 1024).' kB';
    }

    private function pause(): void
    {
        usleep(max(0, (int) $this->option('seconds')) * 1_000_000);
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

    private function save(): void
    {
        $this->manifest['updated_at'] = now()->toIso8601String();

        Storage::disk('local')->put(
            self::MANIFEST,
            json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    protected function fetch(string $url): ?string
    {
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => (int) $this->option('timeout'),
            CURLOPT_USERAGENT => self::AGENT,
            CURLOPT_ACCEPT_ENCODING => '',
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return $body === false || $status >= 400 || $body === '' ? null : (string) $body;
    }
}
