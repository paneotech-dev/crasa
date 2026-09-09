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
