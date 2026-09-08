<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stable fragment id for a block.
 *
 * The footer in screen 02 draws five links in its About column and the About
 * page is one page, so four of them are sections of it. The screen draws
 * every footer link as href="#", so it is silent on the destination; this is
 * the mechanism that gives those links somewhere real to land, and it is
 * also what lets an editor deep-link to a section without hand-writing
 * anchors into body copy.
 *
 * Unique per page rather than globally: two pages may both have a "contact"
 * section.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_blocks', function (Blueprint $table) {
            $table->string('anchor', 64)->nullable()->after('position');
            $table->unique(['page_id', 'anchor']);
        });
    }

    public function down(): void
    {
        Schema::table('page_blocks', function (Blueprint $table) {
            $table->dropUnique(['page_id', 'anchor']);
            $table->dropColumn('anchor');
        });
    }
};
