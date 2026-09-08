<?php

namespace App\Console\Commands;

use App\Support\ImageLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

/**
 * Builds width-stepped derivatives from the supplied archive originals.
 *
 * Run after any change to the image library. Derivatives are ignored by git
 * and rebuilt on deploy; the originals are the only thing that needs to be
 * kept.
 */
class BuildImageDerivatives extends Command
{
    protected $signature = 'images:derivatives {--force : Rebuild derivatives that already exist}';

    protected $description = 'Build responsive derivatives from the supplied image originals';

    public function handle(): int
    {
        if (! class_exists(Imagick::class)) {
            $this->error('Imagick is not available on this host, so derivatives cannot be built.');

            return self::FAILURE;
        }

        $built = 0;
        $skipped = 0;

        foreach (ImageLibrary::slots() as $slot) {
            if (! ImageLibrary::has($slot)) {
                $this->warn("Slot [{$slot}] has no source image. Skipped.");

                continue;
            }

            $source = Storage::disk('public')->path(ImageLibrary::sourcePath($slot));

            foreach (ImageLibrary::WIDTHS as $width) {
                foreach (['jpg', 'webp'] as $extension) {
                    $target = ImageLibrary::derivativePath($slot, $width, $extension);

                    if (! $this->option('force') && Storage::disk('public')->exists($target)) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $image = new Imagick($source);
                        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
                        // Strip EXIF: an archive original can carry GPS
                        // coordinates and a photographer's name, neither of
                        // which belongs on a public page.
                        $image->stripImage();
                        $image->thumbnailImage($width, 0);
                        $image->setImageFormat($extension === 'webp' ? 'webp' : 'jpeg');
                        $image->setImageCompressionQuality($extension === 'webp' ? 80 : 82);
                        $image->setInterlaceScheme(Imagick::INTERLACE_PLANE);

                        Storage::disk('public')->put($target, $image->getImageBlob());
                        $image->clear();

                        $built++;
                        $this->line(sprintf(
                            '  %-14s %5dpx %-4s  %s',
                            $slot, $width, $extension,
                            $this->readableSize(Storage::disk('public')->size($target))
                        ));
                    } catch (Throwable $e) {
                        $this->error("  {$slot} {$width}px {$extension}: ".$e->getMessage());
                    }
                }
            }
        }

        $this->newLine();
        $this->info("Built {$built} derivative(s), skipped {$skipped}.");

        return self::SUCCESS;
    }

    private function readableSize(int $bytes): string
    {
        return $bytes >= 1_048_576
            ? round($bytes / 1_048_576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
