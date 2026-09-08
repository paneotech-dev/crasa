<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

/**
 * The three regulated sectors.
 *
 * accent_token names a custom property from public/css/tokens.css. The design
 * foundations do not define per-sector accents, so these three are placeholders
 * chosen to avoid the status colours: green, amber and red carry fixed
 * meanings and must not double as a sector marker. Changing them is a data
 * edit, not a code change.
 */
class SectorSeeder extends Seeder
{
    /** @var list<array{slug: string, name: string, icon: string, accent_token: string}> */
    private const SECTORS = [
        [
            'slug' => Sector::TELECOMMUNICATIONS,
            'name' => 'Telecommunications',
            'icon' => 'radio-tower',
            'accent_token' => '--c-navy',
        ],
        [
            'slug' => Sector::BROADCASTING,
            'name' => 'Broadcasting',
            'icon' => 'tv',
            'accent_token' => '--c-navy-mid',
        ],
        [
            'slug' => Sector::POSTAL,
            'name' => 'Postal',
            'icon' => 'package',
            'accent_token' => '--c-slate',
        ],
    ];

    public function run(): void
    {
        foreach (self::SECTORS as $sector) {
            Sector::updateOrCreate(['slug' => $sector['slug']], $sector);
        }
    }
}
