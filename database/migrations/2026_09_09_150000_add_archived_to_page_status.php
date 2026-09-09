<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fourth page state: archived.
 *
 * A page that came off the public site is not a deleted page. It is still a
 * page somebody may have cited, and the Secretariat has to be able to take
 * one down without destroying it or leaving it live.
 *
 * The column is an enum, so the set has to be rewritten rather than added
 * to. Rebuilt as a string with an explicit check on both engines: the enum
 * syntax differs between MariaDB and SQLite and the suite runs on SQLite,
 * so a raw ALTER written for one 500s on the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function ($table) {
            $table->string('status_new', 16)->default('draft')->after('status');
        });

        DB::table('pages')->update(['status_new' => DB::raw('status')]);

        Schema::table('pages', function ($table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        Schema::table('pages', function ($table) {
            $table->renameColumn('status_new', 'status');
        });

        Schema::table('pages', function ($table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        DB::table('pages')->where('status', 'archived')->update(['status' => 'draft']);
    }
};
