<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fourth confidence: validated.
 *
 * The submission workflow is draft, submitted, validated, returned, and a
 * validated submission writes its figures into indicator_values. Without a
 * value for it, a figure the Secretariat has checked is indistinguishable
 * from one a member state simply typed, which is the distinction the
 * comparator's provenance badge exists to show.
 *
 * The column is an enum, so the set has to be rewritten rather than added
 * to, and it is rebuilt as a string because the enum syntax differs between
 * MariaDB and SQLite and the suite runs on SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicator_values', function ($table) {
            $table->string('confidence_new', 20)->default('reported')->after('confidence');
        });

        DB::table('indicator_values')->update(['confidence_new' => DB::raw('confidence')]);

        Schema::table('indicator_values', function ($table) {
            $table->dropColumn('confidence');
        });

        Schema::table('indicator_values', function ($table) {
            $table->renameColumn('confidence_new', 'confidence');
        });
    }

    public function down(): void
    {
        DB::table('indicator_values')->where('confidence', 'validated')->update(['confidence' => 'reported']);
    }
};
