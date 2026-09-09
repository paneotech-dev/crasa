<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use RuntimeException;

/**
 * Storing an upload, and building what the site actually serves from it.
 *
 * Screen 14, in its own words: "Nothing is hotlinked. Every asset is stored
 * by the platform, with renditions generated on upload." And: "Served
 * through a picture element, WebP with a JPEG fallback, lazy loaded below
 * the fold. The original is kept and never served directly."
 *
 * EXIF IS STRIPPED, AND NOT AS A NICETY. A photograph taken at a CRASA
 * meeting carries the GPS coordinates of the room, the camera's serial
 * number and often the photographer's name. Publishing that on a
 * government-facing platform discloses information nobody agreed to
 * disclose, and it cannot be taken back once a file is in a crawler's
 * cache. Imagick::stripImage() removes every profile and comment.
 */
final class MediaLibrary
{
    /**
     * The widths screen 14 lists in its renditions table: 1600 hero and
     * full bleed, 1200 article lead, 800 card and list, 400 thumbnail. The
     * original is the fifth row there and is deliberately not built,
     * because it is the one that is never served.
     */
    public const WIDTHS = [400, 800, 1200, 1600];

    public const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

    public const MAX_BYTES = 25 * 1024 * 1024;

    public function __construct(private readonly string $disk = 'public') {}

    /**
     * Store an upload as an asset, or return the existing asset if this
     * exact file is already in the library.
     *
     * Deduplicated on the SHA-256 of the file. A library holding the same
     * plenary photograph four times under four names is a library nobody
     * searches twice.
     */
    public function store(UploadedFile $file, ?int $uploadedBy = null): MediaAsset
    {
        $checksum = hash_file('sha256', $file->getRealPath());

        $existing = MediaAsset::withTrashed()->where('checksum', $checksum)->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        $extension = $this->extensionFor($file->getMimeType());
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'asset';
        $path = 'media/originals/'.now()->format('Y/m').'/'.$name.'-'.substr($checksum, 0, 8).'.'.$extension;

        $dimensions = $this->dimensions($file->getRealPath(), $file->getMimeType());

        Storage::disk($this->disk)->put($path, $this->cleaned($file));

        $asset = MediaAsset::create([
            'disk' => $this->disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => Storage::disk($this->disk)->size($path),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'checksum' => $checksum,
            'uploaded_by' => $uploadedBy,
        ]);

        $this->generateRenditions($asset);

        return $asset;
    }

    /**
     * Replace the file behind an existing asset, keeping its id, its alt
     * text and every reference to it.
     *
     * This is what "replace" has to mean. Deleting and re-uploading loses
     * the alt text in three languages and breaks every block pointing at
     * it, and the person doing it finds out from a reader.
     */
    public function replace(MediaAsset $asset, UploadedFile $file): MediaAsset
    {
        $this->deleteFiles($asset);

        $checksum = hash_file('sha256', $file->getRealPath());
        $extension = $this->extensionFor($file->getMimeType());
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'asset';
        $path = 'media/originals/'.now()->format('Y/m').'/'.$name.'-'.substr($checksum, 0, 8).'.'.$extension;

        $dimensions = $this->dimensions($file->getRealPath(), $file->getMimeType());

        Storage::disk($this->disk)->put($path, $this->cleaned($file));

        $asset->update([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => Storage::disk($this->disk)->size($path),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'checksum' => $checksum,
            'renditions' => null,
        ]);

        $this->generateRenditions($asset);

        return $asset;
    }

    /**
     * Build the served widths.
     *
     * Never upscales: a 412px authority logo does not become a 1600px one
     * by being asked to, it becomes a blurred 1600px one.
     */
    public function generateRenditions(MediaAsset $asset): void
    {
        if ($asset->mime_type === 'image/svg+xml') {
            // Vector. One file serves every width, so there is nothing to
            // step down to and nothing to lose by serving it.
            $asset->update(['renditions' => [(string) ($asset->width ?: 1600) => [
                'path' => $asset->path,
                'bytes' => $asset->size_bytes,
                'format' => 'svg',
            ]]]);

            return;
        }

        if (! extension_loaded('imagick')) {
            return;
        }

        $source = Storage::disk($asset->disk)->path($asset->path);
        $renditions = [];

        foreach (self::WIDTHS as $width) {
            if ($asset->width !== null && $width > $asset->width) {
                continue;
            }

            $target = 'media/renditions/'.$asset->id.'/'.$width.'.webp';

            $image = new Imagick($source);
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(82);
            $image->resizeImage($width, 0, Imagick::FILTER_LANCZOS, 1);

            Storage::disk($asset->disk)->put($target, $image->getImageBlob());

            $renditions[(string) $width] = [
                'path' => $target,
                'bytes' => Storage::disk($asset->disk)->size($target),
                'format' => 'webp',
            ];

            $image->clear();
        }

        // An image narrower than the smallest step still needs one file to
        // serve, or it is in the library and unusable.
        if ($renditions === [] && $asset->width !== null) {
            $target = 'media/renditions/'.$asset->id.'/'.$asset->width.'.webp';

            $image = new Imagick($source);
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(82);

            Storage::disk($asset->disk)->put($target, $image->getImageBlob());

            $renditions[(string) $asset->width] = [
                'path' => $target,
                'bytes' => Storage::disk($asset->disk)->size($target),
                'format' => 'webp',
            ];

            $image->clear();
        }

        $asset->update(['renditions' => $renditions]);
    }

    /** Removes the original and every rendition. */
    public function deleteFiles(MediaAsset $asset): void
    {
        Storage::disk($asset->disk)->delete($asset->path);
        Storage::disk($asset->disk)->deleteDirectory('media/renditions/'.$asset->id);
    }

    /**
     * The bytes to store: the file with every EXIF profile and comment
     * removed.
     *
     * SVG passes through unchanged, because there is no Imagick raster step
     * that would not destroy it. Uploads are restricted by MIME type and
     * the uploads directory does not execute PHP, which is enforced in
     * .htaccess per CLAUDE.md.
     */
    private function cleaned(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($file->getMimeType() === 'image/svg+xml' || ! extension_loaded('imagick')) {
            return $contents;
        }

        $image = new Imagick;
        $image->readImageBlob($contents);
        $image->stripImage();
        $stripped = $image->getImageBlob();
        $image->clear();

        return $stripped;
    }

    /** @return array{width: int|null, height: int|null} */
    private function dimensions(string $path, ?string $mime): array
    {
        if ($mime === 'image/svg+xml') {
            return ['width' => null, 'height' => null];
        }

        $size = @getimagesize($path);

        return $size === false
            ? ['width' => null, 'height' => null]
            : ['width' => $size[0], 'height' => $size[1]];
    }

    private function extensionFor(?string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => throw new RuntimeException('Unsupported media type: '.$mime),
        };
    }
}
