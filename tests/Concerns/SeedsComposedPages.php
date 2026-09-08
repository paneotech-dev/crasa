<?php

namespace Tests\Concerns;

use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\SectorSeeder;

/**
 * The homepage is a page record, not a Blade template, so a test that hits
 * it has to seed it. Reference data comes too, so the member grid renders
 * its fourteen states rather than its empty state.
 */
trait SeedsComposedPages
{
    protected function seedComposedPages(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(HomepageSeeder::class);
    }
}
