<?php

namespace App\Support;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

/**
 * Resolving the "media:{id}" reference a block stores in its settings.
 *
 * The reference is a string rather than a bare id so that a settings scan
 * can find it unambiguously, and so a block that stores an ImageLibrary
 * slot name and a block that stores an uploaded asset can share one field.
 */
final class MediaReference
{
    public const PREFIX = 'media:';

    /**
     * The reference for an asset. The companion to resolve(), so nothing
     * outside this class has to know the prefix or build the string.
     */
    public static function to(MediaAsset $asset): string
    {
        return self::PREFIX.$asset->getKey();
    }

    public static function isReference(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::PREFIX);
    }

    /** Resolves in one query, cached for the request. */
    public static function resolve(?string $value): ?MediaAsset
    {
        if (! self::isReference($value)) {
            return null;
        }

        $id = (int) substr($value, strlen(self::PREFIX));

        if ($id <= 0) {
            return null;
        }

        static $cache = [];

        return $cache[$id] ??= MediaAsset::with('translations')->find($id);
    }

    /** Every generated width, so the browser picks. Never the original. */
    public static function srcset(MediaAsset $asset, int $max = 1600): string
    {
        $parts = [];

        foreach (($asset->renditions ?? []) as $width => $rendition) {
            // No page draws a photograph wider than 1600; a wider rendition
            // only costs a high-density screen bytes it cannot show.
            if ((int) $width > $max && $parts !== []) {
                continue;
            }

            $parts[] = Storage::disk($asset->disk)->url($rendition['path']).' '.$width.'w';
        }

        return implode(', ', $parts);
    }
}
