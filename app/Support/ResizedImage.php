<?php

namespace App\Support;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

/**
 * A stored photograph, served at the width the layout draws it.
 *
 * The newsroom and the homepage cards printed the path a news item points
 * at, which is the original as uploaded or downloaded: a 3.3 MB JPEG four
 * thousand pixels wide in a card three hundred wide. Every one of those
 * paths is a media library asset, and the library already wrote WebP
 * renditions of it (480, 960, 1440 and so on) when it was stored. This
 * hands the page those renditions, capped at the width the role needs,
 * with the asset's own width and height so the frame is reserved before
 * the file arrives.
 *
 * A path the library does not know, or an asset with no renditions, falls
 * back to the original, so nothing disappears.
 */
final class ResizedImage
{
    /** The widest rendition each role may use. */
    public const ROLES = [
        'card' => 960,
        'hero' => 1600,
    ];

    /**
     * src, srcset, width and height for an <img>.
     *
     * @return array{src: string, srcset: string|null, width: int|null, height: int|null}
     */
    public static function attributes(string $path, string $role = 'card'): array
    {
        $asset = self::assets()[$path] ?? null;
        $disk = Storage::disk($asset['disk'] ?? 'public');
        $cap = self::ROLES[$role] ?? self::ROLES['card'];

        $renditions = collect($asset['renditions'] ?? [])
            ->filter(fn ($rendition) => is_array($rendition) && isset($rendition['path']))
            ->mapWithKeys(fn ($rendition, $width) => [(int) $width => $rendition['path']])
            ->sortKeys();

        $usable = $renditions->filter(fn ($p, int $width) => $width <= $cap);

        // Even the smallest rendition is wider than the cap: use it anyway,
        // it is still smaller than the original.
        if ($usable->isEmpty() && $renditions->isNotEmpty()) {
            $usable = $renditions->take(1);
        }

        if ($usable->isEmpty()) {
            return [
                'src' => Storage::disk('public')->url($path),
                'srcset' => null,
                'width' => $asset['width'] ?? null,
                'height' => $asset['height'] ?? null,
            ];
        }

        return [
            'src' => $disk->url($usable->last()),
            'srcset' => $usable->map(fn (string $p, int $width) => $disk->url($p).' '.$width.'w')->implode(', '),
            'width' => $asset['width'] ?? null,
            'height' => $asset['height'] ?? null,
        ];
    }

    /** The public-disk path of the card-sized rendition, or the path itself. */
    public static function cardPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return $path;
        }

        $asset = self::assets()[$path] ?? null;

        if ($asset === null || ($asset['disk'] ?? 'public') !== 'public') {
            return $path;
        }

        $best = collect($asset['renditions'] ?? [])
            ->filter(fn ($rendition, $width) => is_array($rendition) && isset($rendition['path']) && (int) $width <= self::ROLES['card'])
            ->sortKeys()
            ->last();

        return $best['path'] ?? $path;
    }

    /**
     * Every asset by path, read once per request: a page of twelve cards
     * asks twelve times, and one query of a few hundred short rows is
     * cheaper than twelve.
     *
     * @return array<string, array{disk: string, width: int|null, height: int|null, renditions: array<mixed>|null}>
     */
    private static function assets(): array
    {
        return RequestMemo::remember('media-assets-by-path', fn () => MediaAsset::query()
            ->get(['disk', 'path', 'width', 'height', 'renditions'])
            ->mapWithKeys(fn (MediaAsset $asset) => [$asset->path => [
                'disk' => $asset->disk ?: 'public',
                'width' => $asset->width,
                'height' => $asset->height,
                'renditions' => $asset->renditions,
            ]])
            ->all());
    }
}
