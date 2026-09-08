<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The supplied image library, and the derivatives served from it.
 *
 * The originals are archive masters: the best of them is 8000 by 5336 and
 * 20 MB. Serving one to a reader on a mobile connection in Southern Africa
 * would be indefensible on a platform for this audience, so nothing here
 * ever points at an original. Pages get width-stepped derivatives built by
 * `php artisan images:derivatives`.
 *
 * A slot with no image resolves to null, and the block renders the designed
 * absence rather than a broken img or a grey box.
 */
final class ImageLibrary
{
    /** Widths built for every image, in CSS pixels. */
    public const WIDTHS = [480, 960, 1440, 1920];

    private const SOURCE_ROOT = 'images/crasa-images';

    private const DERIVATIVE_ROOT = 'images/derivatives';

    /**
     * The accepted images, by slot. Only these five are cleared for use.
     * The eight in _review-needed are held: two are photographed outside the
     * region entirely, which on a Southern African regulatory platform is
     * not a small problem.
     *
     * @var array<string, array{path: string, orientation: string}>
     */
    private const ACCEPTED = [
        'hero' => [
            'path' => 'people/people-market-mobile-01.jpg',
            'orientation' => 'landscape',
        ],
        'people' => [
            'path' => 'people/people-courier-mobile-01.jpg',
            'orientation' => 'landscape',
        ],
        'sector-telecom' => [
            'path' => 'sector-telecom/telecom-equipment-testing-01.jpg',
            'orientation' => 'landscape',
        ],
        'sector-postal' => [
            'path' => 'sector-postal/postal-parcel-handover-01.jpg',
            'orientation' => 'landscape',
        ],
        // Portrait, 3537 by 5306. Card and sidebar use only; it cannot carry
        // a full-width band without a crop nobody has approved.
        'institutional' => [
            'path' => 'institutional/institutional-team-review-01.jpg',
            'orientation' => 'portrait',
        ],
    ];

    /** @return list<string> */
    public static function slots(): array
    {
        return array_keys(self::ACCEPTED);
    }

    public static function has(string $slot): bool
    {
        $source = self::ACCEPTED[$slot]['path'] ?? null;

        return $source !== null
            && Storage::disk('public')->exists(self::SOURCE_ROOT.'/'.$source);
    }

    public static function orientation(string $slot): ?string
    {
        return self::ACCEPTED[$slot]['orientation'] ?? null;
    }

    public static function sourcePath(string $slot): ?string
    {
        $source = self::ACCEPTED[$slot]['path'] ?? null;

        return $source === null ? null : self::SOURCE_ROOT.'/'.$source;
    }

    /** The stem a slot's derivatives are named from. */
    public static function stem(string $slot): ?string
    {
        $source = self::ACCEPTED[$slot]['path'] ?? null;

        return $source === null ? null : pathinfo($source, PATHINFO_FILENAME);
    }

    public static function derivativePath(string $slot, int $width, string $extension): ?string
    {
        $stem = self::stem($slot);

        return $stem === null ? null : self::DERIVATIVE_ROOT."/{$stem}-{$width}.{$extension}";
    }

    /**
     * Everything a template needs to render one slot responsibly, or null if
     * the slot has no image and the empty state should render instead.
     *
     * @return array{src: string, srcset: string, webpsrcset: string, orientation: string}|null
     */
    public static function resolve(string $slot): ?array
    {
        if (! self::has($slot)) {
            return null;
        }

        $jpeg = [];
        $webp = [];

        foreach (self::WIDTHS as $width) {
            $jpegPath = self::derivativePath($slot, $width, 'jpg');
            $webpPath = self::derivativePath($slot, $width, 'webp');

            if (Storage::disk('public')->exists($jpegPath)) {
                $jpeg[$width] = Storage::disk('public')->url($jpegPath).' '.$width.'w';
            }

            if (Storage::disk('public')->exists($webpPath)) {
                $webp[$width] = Storage::disk('public')->url($webpPath).' '.$width.'w';
            }
        }

        // Derivatives have not been built. Rendering the original would ship
        // a 20 MB hero, so the slot reports empty and the designed absence
        // renders instead.
        if ($jpeg === []) {
            return null;
        }

        return [
            'src' => Storage::disk('public')->url(self::derivativePath($slot, 960, 'jpg')),
            'srcset' => implode(', ', $jpeg),
            'webpsrcset' => implode(', ', $webp),
            'orientation' => self::orientation($slot) ?? 'landscape',
        ];
    }
}
