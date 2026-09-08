<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FULLTEXT indexes for the MariaDB search driver.
 *
 * Searchable text lives in the translations table, so that is where the
 * index goes: one index serves instruments, publications, news and anything
 * else translatable, in all three languages, without a per-model index.
 *
 * SQLite has no FULLTEXT. The driver falls back to LIKE there, which is
 * correct for a test database and would be wrong in production; the driver
 * reports which mode it is in.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE translations ADD FULLTEXT translations_fulltext (value)');
        DB::statement('ALTER TABLE indicators ADD FULLTEXT indicators_fulltext (name, definition)');
        DB::statement('ALTER TABLE countries ADD FULLTEXT countries_fulltext (name)');
        DB::statement('ALTER TABLE authorities ADD FULLTEXT authorities_fulltext (name, acronym)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ([
            'translations' => 'translations_fulltext',
            'indicators' => 'indicators_fulltext',
            'countries' => 'countries_fulltext',
            'authorities' => 'authorities_fulltext',
        ] as $table => $index) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE {$table} DROP INDEX {$index}");
            }
        }
    }
};
