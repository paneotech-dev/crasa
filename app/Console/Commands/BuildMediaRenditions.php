<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Services\MediaLibrary;
use Illuminate\Console\Command;
use Throwable;

/**
 * WebP renditions for media library assets that have none.
 *
 * Uploads get their renditions (400, 800, 1200 and 1600 wide, WebP) when
 * they are stored. Some assets brought in by the legacy import were
 * narrower than the smallest step and were registered with the original
 * PNG or JPEG as their only rendition, so a 434 pixel PNG of 380 KB went
 * to every reader of the newsroom. This builds the WebP files for those,
 * through the same code an upload uses. Safe to run on every deploy: an
 * asset that already has a WebP rendition is left alone.
 */
class BuildMediaRenditions extends Command
{
    protected $signature = 'media:renditions {--all : Rebuild every raster asset at the standard widths, not only those without WebP}';

    protected $description = 'Build WebP renditions for media library assets that have none';

    public function handle(MediaLibrary $library): int
    {
        if (! extension_loaded('imagick')) {
            $this->error('Imagick is not available on this host, so renditions cannot be built.');

            return self::FAILURE;
        }

        $built = 0;
        $skipped = 0;

        foreach (MediaAsset::query()->where('mime_type', '!=', 'image/svg+xml')->orderBy('id')->cursor() as $asset) {
            $hasWebp = collect($asset->renditions ?? [])->contains(fn ($r) => is_array($r) && ($r['format'] ?? null) === 'webp');

            if ($hasWebp && ! $this->option('all')) {
                $skipped++;

                continue;
            }

            try {
                $library->generateRenditions($asset);
                $built++;
                $this->line(sprintf('  #%-5d %s  %s', $asset->id, $asset->path, implode(', ', array_keys($asset->fresh()->renditions ?? []))));
            } catch (Throwable $e) {
                $this->error("  #{$asset->id} {$asset->path}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Built renditions for {$built} asset(s), {$skipped} already had WebP.");

        return self::SUCCESS;
    }
}
