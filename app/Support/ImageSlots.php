<?php

namespace App\Support;

use App\Models\MediaAsset;

/**
 * The image slots of config/media-slots.php, and whether a photograph is
 * smaller than the slot it is meant for (item 1i, Day 3).
 *
 * A warning, never a refusal: CRASA sometimes has only the photograph it
 * has, and a slightly soft slide is better than none. The same rule runs
 * in the browser before upload (public/js/media-slots.js reads the same
 * figures from the form) and here after the file is stored, from the
 * width and height the media library records.
 */
final class ImageSlots
{
    /** @return array<string, array{width: int, height: int, ratio: string}|null> */
    public static function all(): array
    {
        return config('media-slots', []);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** @return array{width: int, height: int, ratio: string}|null */
    public static function minimum(?string $slot): ?array
    {
        return $slot === null ? null : (self::all()[$slot] ?? null);
    }

    public static function label(string $slot): string
    {
        return __('m3_media.slot_'.$slot);
    }

    /** "Hero slider: at least 1600 x 1200 px, landscape 4:3", or the "other" line. */
    public static function recommendation(string $slot): string
    {
        $min = self::minimum($slot);

        if ($min === null) {
            return __('m3_media.recommend_none', ['slot' => self::label($slot)]);
        }

        return __('m3_media.recommend', [
            'slot' => self::label($slot),
            'width' => $min['width'],
            'height' => $min['height'],
            'shape' => __('m3_media.shape_'.($min['width'] === $min['height'] ? 'square' : ($min['width'] > $min['height'] ? 'landscape' : 'portrait'))),
            'ratio' => $min['ratio'],
        ]);
    }

    /** Smaller than the slot in either direction. Unknown sizes (SVG) are not. */
    public static function tooSmall(?int $width, ?int $height, ?string $slot): bool
    {
        $min = self::minimum($slot);

        if ($min === null || ! $width || ! $height) {
            return false;
        }

        return $width < $min['width'] || $height < $min['height'];
    }

    /** The warning for an asset in a slot, or null when it is large enough. */
    public static function warning(MediaAsset $asset, ?string $slot): ?string
    {
        if (! self::tooSmall($asset->width, $asset->height, $slot)) {
            return null;
        }

        $min = self::minimum($slot);

        return __('m3_media.warning', [
            'width' => $asset->width,
            'height' => $asset->height,
            'slot' => self::label($slot),
            'min_width' => $min['width'],
            'min_height' => $min['height'],
        ]);
    }

    /**
     * The slots an asset is in use in, from MediaUsage, for which it is too
     * small.
     *
     * @param  list<array{slot?: string|null}>  $usage
     * @return list<string>
     */
    public static function smallFor(MediaAsset $asset, array $usage): array
    {
        $slots = array_unique(array_filter(array_map(fn ($u) => $u['slot'] ?? null, $usage)));

        return array_values(array_filter($slots, fn ($slot) => self::tooSmall($asset->width, $asset->height, $slot)));
    }
}
