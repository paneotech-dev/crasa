<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Section 2 of docs/DATA_MODEL.md, in dependency order. Idempotent: running
 * it again updates the seeded rows rather than duplicating them.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            SectorSeeder::class,
            AuthoritySeeder::class,
            CommitteeSeeder::class,
        ]);
    }
}
