<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where every imported record came from, WS-12.
 *
 * ONE TABLE RATHER THAN FIVE SETS OF COLUMNS. Publications, news items,
 * meetings, projects and page blocks all need the same four facts: the
 * legacy URL, the legacy grouping the new taxonomy has no room for, whether
 * the mapping needs a human to look at it, and where the date came from.
 * Adding those to five tables writes the same migration five times and
 * leaves the 301 map as a five-way union. Here the redirect map is one
 * query.
 *
 * WHY THE LEGACY URL IS UNIQUE. It is the redirect's key. Two records
 * claiming one legacy URL is a mapping fault, and the database is where
 * that should surface rather than in whichever row the map happened to
 * read last.
 *
 * date_source is recorded because the ruling ranks three sources: a date
 * stated on the legacy page wins, then a year in the document filename,
 * then the crawl date flagged unknown. A reader looking at a 2000
 * publication is entitled to know which of those produced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_records', function (Blueprint $table) {
            $table->id();

            $table->string('legacy_url', 500)->unique();

            // The path alone, normalised: no host, no trailing slash. The
            // legacy site answers on crasa.org and www.crasa.org and a
            // citation may carry either, so the redirect looks up the path
            // rather than the whole URL. Indexed, because this is the
            // lookup every 404 makes.
            $table->string('legacy_path', 500)->index();
            $table->string('legacy_category', 120)->nullable();

            $table->string('recordable_type')->nullable();
            $table->unsignedBigInteger('recordable_id')->nullable();

            // Where the 301 sends a reader. Null means the page was read,
            // mapped to nothing, and should answer 410 rather than 404.
            $table->string('new_path', 500)->nullable();

            $table->boolean('needs_review')->default(false);
            $table->string('review_note', 500)->nullable();

            $table->string('date_source', 20)->default('page');
            $table->timestamp('imported_at')->nullable();

            $table->timestamps();

            $table->index(['recordable_type', 'recordable_id']);
            $table->index('needs_review');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_records');
    }
};
