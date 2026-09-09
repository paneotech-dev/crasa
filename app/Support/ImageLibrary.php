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
        /*
         * The homepage band's slot. THE HOMEPAGE DOES NOT USE IT.
         *
         * It held a market scene in Cape Town until 9 September 2026, which
         * was wrong: CRASA is a treaty body that fourteen national
         * regulatory authorities answer to, and the homepage opened on a
         * stranger shopping. It was then moved to the equipment testing
         * bench, which is at least a regulatory activity and still not an
         * institutional subject.
         *
         * Resolved the same day: the homepage runs with no photograph at
         * all. A hero on the wrong picture is a claim about what CRASA is,
         * made on the first screen, to a regulator. Navy and the typography
         * are the design until the right photograph exists — screen 02 draws
         * a slide with no photograph of its own, so this is the screen's own
         * composition rather than a fallback from it.
         *
         * The slot stays here because it is a working derivative pipeline
         * and the day CRASA supplies a plenary or a signing it is one
         * setting away. The brief for what to supply is in
         * docs/CLIENT_REQUESTS.md.
         */
        'hero' => [
            'path' => 'sector-telecom/telecom-equipment-testing-01.jpg',
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

    /**
     * The aspect a slot is cropped to at a given width.
     *
     * Art direction rather than resizing. The hero is designed around a wide
     * band, but a wide band at 375 is a strip of sky with the subject cut
     * out of it, so narrow renditions get a squarer crop that keeps the
     * person in frame. Null means keep the original proportions.
     */
    public static function aspectFor(string $slot, int $width): ?float
    {
        $orientation = self::orientation($slot);

        // Portrait originals are card and sidebar images; cropping them to a
        // band would be inventing a composition nobody approved.
        if ($orientation === 'portrait') {
            return null;
        }

        return match (true) {
            // Phone: nearly square, subject-first.
            $width <= 480 => 4 / 3,
            // Tablet: a gentler band.
            $width <= 960 => 16 / 9,
            // Desktop: the band the hero is designed around.
            default => 21 / 9,
        };
    }

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
     * The renditions for one slot, grouped by the breakpoint each is cropped
     * for, or null if the slot has no image and the empty state should
     * render instead.
     *
     * Grouped rather than one flat srcset because the crops differ: a single
     * srcset with w descriptors tells the browser these are the same image
     * at different sizes, and picking a 21:9 candidate where a 4:3 one was
     * laid out produces a jump. Art direction needs media-gated sources.
     *
     * @return array{sources: list<array{media: string|null, type: string, srcset: string}>, src: string, orientation: string}|null
     */
    public static function resolve(string $slot): ?array
    {
        if (! self::has($slot)) {
            return null;
        }

        $groups = [
            ['media' => '(max-width: 480px)', 'widths' => [480]],
            ['media' => '(max-width: 960px)', 'widths' => [960]],
            ['media' => null, 'widths' => [1440, 1920]],
        ];

        $sources = [];
        $fallback = null;

        foreach ($groups as $group) {
            foreach (['webp', 'jpg'] as $extension) {
                $candidates = [];

                foreach ($group['widths'] as $width) {
                    $path = self::derivativePath($slot, $width, $extension);

                    if ($path !== null && Storage::disk('public')->exists($path)) {
                        $candidates[] = Storage::disk('public')->url($path).' '.$width.'w';

                        if ($extension === 'jpg' && $fallback === null) {
                            $fallback = Storage::disk('public')->url($path);
                        }
                    }
                }

                if ($candidates !== []) {
                    $sources[] = [
                        'media' => $group['media'],
                        'type' => $extension === 'webp' ? 'image/webp' : 'image/jpeg',
                        'srcset' => implode(', ', $candidates),
                    ];
                }
            }
        }

        // Derivatives have not been built. Rendering the original would ship
        // a 20 MB hero, so the slot reports empty and the designed absence
        // renders instead.
        if ($fallback === null) {
            return null;
        }

        return [
            'sources' => $sources,
            'src' => $fallback,
            'orientation' => self::orientation($slot) ?? 'landscape',
        ];
    }
}
