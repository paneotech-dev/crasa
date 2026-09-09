<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * The web app manifest, WS-02, screen 15.
 *
 * Served from a route rather than as a static file because the start URL has
 * to carry the reader's language. A manifest with a fixed start_url installs
 * an English app on a Portuguese reader's phone, and the whole point of the
 * trilingual layer is that it does not do that.
 *
 * Everything else here is from the tokens: the theme colour is --c-navy,
 * which is the header's ground, and the background colour is --c-cream,
 * which is the page ground behind the splash. A splash screen in a colour
 * the site does not use is the tell that a manifest was filled in from a
 * generator.
 */
class AppManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $locale = app()->getLocale();

        return response()->json([
            'name' => __('site.full_name'),
            'short_name' => __('site.acronym'),
            // The footer blurb, which is the one line of self-description the
            // platform already has approved in three languages.
            'description' => __('footer.blurb'),

            // The reader's own language, which is why this is a route.
            'start_url' => url($locale),
            'scope' => url('/').'/',
            'id' => '/?locale='.$locale,

            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => '#0F1B2D',
            'background_color' => '#F8F6F1',
            'lang' => $locale,
            'dir' => 'ltr',

            'icons' => [
                [
                    'src' => asset('img/app/icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('img/app/icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                /*
                 * Maskable, which Android crops to the launcher's own shape.
                 * The mark is inset to the middle 80 percent so a circular
                 * mask cannot clip it; without a maskable icon Android puts
                 * the square one inside a white rounded box, which looks
                 * like a screenshot of an icon rather than an icon.
                 */
                [
                    'src' => asset('img/app/icon-maskable-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],

            // Long-press the installed icon. Three destinations a delegate
            // actually opens, in their own language.
            'shortcuts' => [
                [
                    'name' => __('nav.publications'),
                    'url' => route('publications', ['locale' => $locale]),
                ],
                [
                    'name' => __('nav.comparator'),
                    'url' => route('comparator', ['locale' => $locale]),
                ],
                [
                    'name' => __('nav.members'),
                    'url' => route('members', ['locale' => $locale]),
                ],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }
}
