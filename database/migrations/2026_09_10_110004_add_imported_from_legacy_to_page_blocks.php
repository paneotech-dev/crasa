<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Which page blocks carry text taken from the legacy site.
 *
 * The About page's governance, executive committee and secretariat blocks
 * were all standing on a "being prepared" note. The legacy site states all
 * three, so the platform can stop saying they are being prepared. But an
 * editor opening one of those blocks is entitled to know that the words are
 * the old site's rather than newly written for this one, because the first
 * question they will ask is whether they are still true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_blocks', function ($table) {
            $table->boolean('imported_from_legacy')->default(false)->after('needs_content');
        });
    }

    public function down(): void
    {
        Schema::table('page_blocks', function ($table) {
            $table->dropColumn('imported_from_legacy');
        });
    }
};
