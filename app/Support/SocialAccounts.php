<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * CRASA's own accounts, from site settings.
 *
 * They were three hardcoded URLs in a Blade template and two of the three
 * were wrong. Every surface reads from here now: the footer, the "Follow
 * us" section, and the share buttons' own network list.
 */
final class SocialAccounts
{
    /** The order they appear in, everywhere. */
    public const NETWORKS = ['x', 'linkedin', 'facebook', 'youtube'];

    /**
     * The accounts that have a URL. An empty slot renders nothing rather
     * than a dead link.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $out = [];

        foreach (self::NETWORKS as $network) {
            $url = SiteSetting::get('social_'.$network);

            if (filled($url)) {
                $out[$network] = $url;
            }
        }

        return $out;
    }

    /**
     * The handle a reader would search for, read off the configured URL so
     * it can never disagree with the link: "@CRASA_ORG" for X, the page
     * name for Facebook, the company slug for LinkedIn.
     */
    public static function handle(string $network, string $url): string
    {
        $segments = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH))));

        return match (true) {
            $segments === [] => (string) parse_url($url, PHP_URL_HOST),
            $network === 'x' => '@'.$segments[0],
            // facebook.com/people/<name>/<id>/ and linkedin.com/company/<slug>/
            in_array($segments[0], ['people', 'pages', 'company', 'in'], true) && isset($segments[1]) => $segments[1],
            default => $segments[0],
        };
    }

    /**
     * The address Facebook's page plugin accepts. The plugin renders
     * nothing for a /people/<name>/<id>/ URL, which is the form the
     * CRASA page has, but it does render the same page addressed by its
     * numeric id. Any other form is passed through unchanged.
     */
    public static function facebookPluginHref(string $url): string
    {
        if (preg_match('~facebook\.com/people/[^/]+/(\d+)~', $url, $m)) {
            return 'https://www.facebook.com/'.$m[1];
        }

        return $url;
    }

    /** The network's own name, for the accessible name of a link. */
    public static function label(string $network): string
    {
        return match ($network) {
            'x' => 'X',
            'linkedin' => 'LinkedIn',
            'facebook' => 'Facebook',
            'youtube' => 'YouTube',
            default => ucfirst($network),
        };
    }
}
