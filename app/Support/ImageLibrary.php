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
         * The homepage hero, slide 1.
         *
         * This slot spent a day pointing at nothing usable. It held a market
         * scene in Cape Town, which was wrong -- CRASA is a treaty body that
         * fourteen national regulatory authorities answer to, and the
         * homepage opened on a stranger shopping. It was moved to an
         * equipment testing bench, which is at least a regulatory activity
         * and still not an institutional subject. The homepage then ran with
         * no photograph at all, on navy and typography, because a hero on
         * the wrong picture is a claim about what CRASA is made on the first
         * screen to a regulator.
         *
         * Batch two closed it. 6000 by 4168, delegates around a glass
         * boardroom table, one taking notes, natural light from full-height
         * windows. It is the register the band has been waiting for: a
         * formal meeting of people who look like they decide things.
         *
         * The composition suits screen 02's overlay as drawn. The gradient
         * runs .94 navy at the left to .34 at the right, so the delegates
         * sit under near-solid navy behind the headline and the right third
         * -- the note-taking hand, the sunburst, the reflection -- reads as
         * texture. NOT FLIPPED. Mirroring it would put the subject under the
         * opaque end and the empty end under the type.
         */
        'hero' => [
            'path' => 'hero/hero-delegates-boardroom-01.jpg',
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
        /*
         * Household connectivity, not infrastructure. 4629 by 3086, a
         * wireless router sharp in the foreground with two people blurred
         * behind.
         *
         * It belongs on universal access and consumer protection content and
         * nowhere else. A router is not spectrum and it is not licensing,
         * and putting it on either would say something about the subject
         * that is not true. The shallow focus makes it usable behind a band
         * at heavy overlay. Unused today: no such surface exists yet.
         */
        'sector-telecom-access' => [
            'path' => 'sector-telecom/telecom-home-broadband-router-01.jpg',
            'orientation' => 'landscape',
        ],
        'sector-postal' => [
            'path' => 'sector-postal/postal-parcel-handover-01.jpg',
            'orientation' => 'landscape',
        ],
        /*
         * The same boardroom photograph as the hero, for the About,
         * Committees and Events bands.
         *
         * It was a portrait team-review shot, 3537 by 5306, which could not
         * carry a full-width band without a crop nobody had approved. This
         * is landscape and it can. Three pages sharing one photograph is
         * thin and is stated as such in the image brief; it is acceptable
         * for the workshop and should be replaced by CRASA's own meeting
         * photography when Form 1 comes back.
         */
        'institutional' => [
            'path' => 'institutional/institutional-delegates-boardroom-01.jpg',
            'orientation' => 'landscape',
        ],
        /*
         * Two people at a laptop in an atrium. 5472 by 3648.
         *
         * A real photograph, competently shot, and generic: it would pass
         * the substitution test for any company on earth. Fallback only.
         * Never leads a page.
         */
        'institutional-fallback' => [
            'path' => 'institutional/institutional-two-person-meeting-01.jpg',
            'orientation' => 'landscape',
        ],
        /*
         * Three colleagues comparing a printed chart against a tablet.
         * 7373 by 4147. Candid, and nobody is smiling at the camera.
         *
         * The data surfaces: the comparator band and the completeness
         * dashboard. People looking at figures is what those pages are
         * about.
         */
        'data' => [
            'path' => 'data/data-team-reviewing-charts-01.jpg',
            'orientation' => 'landscape',
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
