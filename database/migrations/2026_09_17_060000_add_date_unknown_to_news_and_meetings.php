<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a news item's or a meeting's date is known.
 *
 * The legacy import had to put something in published_at and starts_on for
 * the pages that state no date, and put the crawl date there, flagged for
 * review. The public pages then printed it: twelve articles and two
 * meetings dated 16 September 2026, at the top of the newsroom, which is a
 * date nobody gave. The column stays filled (ordering and the published
 * scope need a value) and this flag says not to print it.
 *
 * Backfilled from the import's own record of where each date came from. A
 * meeting whose page gave event dates was recorded as crawl-dated by
 * mistake, so a meeting counts as unknown only when its date is the crawl
 * date itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->boolean('date_unknown')->default(false)->after('published_at');
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->boolean('date_unknown')->default(false)->after('ends_on');
        });

        $crawled = fn (string $type) => DB::table('legacy_records')
            ->where('recordable_type', $type)
            ->where('date_source', 'crawl');

        DB::table('news_items')
            ->whereIn('id', $crawled('App\\Models\\NewsItem')->pluck('recordable_id'))
            ->update(['date_unknown' => true]);

        foreach ($crawled('App\\Models\\Meeting')->get(['recordable_id', 'imported_at', 'created_at']) as $record) {
            $crawlDate = substr((string) ($record->imported_at ?? $record->created_at), 0, 10);

            DB::table('meetings')
                ->where('id', $record->recordable_id)
                ->whereDate('starts_on', $crawlDate)
                ->update(['date_unknown' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('news_items', fn (Blueprint $table) => $table->dropColumn('date_unknown'));
        Schema::table('meetings', fn (Blueprint $table) => $table->dropColumn('date_unknown'));
    }
};
