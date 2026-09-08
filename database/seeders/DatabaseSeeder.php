<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            HomepageSeeder::class,
            PublicPagesSeeder::class,
            LegalPagesSeeder::class,
            NewsroomSeeder::class,
            PublicationSeeder::class,
            IndicatorSeeder::class,
            IndicatorValueSeeder::class,
        ]);
    }
}
