<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * CRASA's own accounts, supplied by the client on 9 September 2026.
 *
 * These replace three hardcoded URLs in a Blade template, two of which were
 * wrong: x.com/Crasa2C, facebook.com/pages/CRASA and
 * linkedin.com/company/crasa. None of those is CRASA's account. A link on a
 * regulator's site pointing at an account that is not theirs is worse than
 * no link.
 */
class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['social_x', 'https://x.com/CRASA_ORG', 'url', 'social'],
            ['social_linkedin', 'https://www.linkedin.com/company/crasa-secretariat-communications-regulators-association-of-southern-africa/', 'url', 'social'],
            ['social_facebook', 'https://www.facebook.com/people/CRASA/100064573337016/', 'url', 'social'],
            // Registered and empty. An empty slot renders nothing; it is
            // here so the Secretariat can fill it without a deploy.
            ['social_youtube', null, 'url', 'social'],
            // The legacy site, for the migration record and the redirect
            // notice a moved page shows.
            ['legacy_website', 'https://www.crasa.org', 'url', 'general'],
        ] as [$key, $value, $type, $group]) {
            SiteSetting::updateOrCreate(['key' => $key], [
                'value' => $value,
                'type' => $type,
                'group' => $group,
            ]);
        }
    }
}
