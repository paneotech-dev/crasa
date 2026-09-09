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

            // Last: it reads the indicators, publications and instruments the
            // seeders above create, and adds the gaps and the workflow states
            // the workshop needs to demonstrate.
            DemonstrationSeeder::class,
        ]);
    }
}
