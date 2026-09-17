<?php

namespace App\Support\Legacy;

use Illuminate\Support\Facades\Storage;

/**
 * The legacy manifest's derivative list, in the media library's shape.
 *
 * legacy:assets records derivatives as "480.jpg" => path, one entry per
 * width and format. The media library, and everything that reads it
 * (MediaAsset::url, the srcset, the rendition table on the edit screen),
 * expects width => {path, bytes, format}. Catalogued as it was, the
 * library screen failed on the first legacy row with "Undefined array key
 * 480".
 *
 * WebP is preferred where both were built, as the library's own uploads
 * are WebP. An image narrower than the smallest step has no derivative;
 * its original is listed at its own width so it is still servable, which
 * is what the upload path does for the same case.
 */
final class Renditions
{
    /**
     * @param  array<string, string>  $manifest  "480.jpg" => path
     * @return array<string, array{path: string, bytes: int, format: string}>
     */
    public static function fromManifest(array $manifest, string $original, ?int $width, string $disk = 'public'): array
    {
        $out = [];

        foreach ($manifest as $key => $path) {
            if (! is_string($path) || ! preg_match('/^(\d+)\.([a-z0-9]+)$/i', (string) $key, $m)) {
                continue;
            }

            $format = strtolower($m[2]) === 'jpeg' ? 'jpg' : strtolower($m[2]);

            if (isset($out[$m[1]]) && $out[$m[1]]['format'] === 'webp') {
                continue;
            }

            $out[$m[1]] = self::entry($path, $format, $disk);
        }

        if ($out === [] && $width !== null && $width > 0) {
            $out[(string) $width] = self::entry($original, strtolower(pathinfo($original, PATHINFO_EXTENSION)) ?: 'jpg', $disk);
        }

        ksort($out, SORT_NUMERIC);

        return $out;
    }

    /** Whether a stored value is still in the manifest's shape. */
    public static function isManifestShape(?array $renditions): bool
    {
        foreach ($renditions ?? [] as $key => $value) {
            if (! is_array($value)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{path: string, bytes: int, format: string} */
    private static function entry(string $path, string $format, string $disk): array
    {
        $storage = Storage::disk($disk);

        return [
            'path' => $path,
            'bytes' => $storage->exists($path) ? $storage->size($path) : 0,
            'format' => $format === 'jpeg' ? 'jpg' : $format,
        ];
    }
}
