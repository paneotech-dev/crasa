<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INC-03, INC-01, INC-04, UX-14.
 *
 * published_on may be empty. Five legacy guidelines carried 1 January 2000,
 * 2002 or 2003: a placeholder for "some time that year", read by every
 * reader as a real date. Where the document does not state its date the
 * honest value is none, and the site says "Date not recorded".
 *
 * external_url: a publication that lives elsewhere (another body's site)
 * may be published without a file of its own. Every other publication needs
 * a file before it can be published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->date('published_on')->nullable()->change();
        });

        if (! Schema::hasColumn('publications', 'external_url')) {
            Schema::table('publications', function (Blueprint $table) {
                $table->string('external_url', 2048)->nullable()->after('is_public');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('publications', 'external_url')) {
            Schema::table('publications', function (Blueprint $table) {
                $table->dropColumn('external_url');
            });
        }

        // Back to NOT NULL only if no row needs the empty value; otherwise
        // the column stays nullable rather than inventing dates.
        if (! DB::table('publications')->whereNull('published_on')->exists()) {
            Schema::table('publications', function (Blueprint $table) {
                $table->date('published_on')->nullable(false)->change();
            });
        }
    }
};
