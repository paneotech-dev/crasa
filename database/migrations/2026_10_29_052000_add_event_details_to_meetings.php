<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the events pages and the event form need beyond a title and dates.
 *
 * legacy_title keeps the title exactly as the events host published it,
 * before the clean-up that removed "Invitation to" and the place and dates
 * already held in their own fields. imported_original keeps the other
 * fields the clean-up filled, so it can be undone.
 *
 * committee_code is the body that meets (PC, ECC, EXCO, AGM): CRASA has
 * more standing committees than the committee records drawn for the
 * member area, and the calendar colours and letters follow the body.
 *
 * Nothing here books, transports or accommodates anybody.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'legacy_title')) {
                $table->string('legacy_title', 255)->nullable()->after('title');
                $table->json('imported_original')->nullable()->after('legacy_title');
                $table->string('committee_code', 12)->nullable()->after('committee_id');
                $table->string('organiser', 200)->nullable()->after('committee_code');
                $table->string('source', 20)->nullable()->after('organiser');
                $table->foreignId('news_item_id')->nullable()->after('source')->constrained('news_items')->nullOnDelete();
                $table->string('status', 20)->default('scheduled')->after('clearance');
                $table->string('status_note', 255)->nullable()->after('status');
                $table->time('starts_at')->nullable()->after('ends_on');
                $table->time('ends_at')->nullable()->after('starts_at');
                $table->string('timezone', 40)->nullable()->after('ends_at');
                $table->string('online_url', 255)->nullable()->after('venue_country');
                $table->date('registration_opens_on')->nullable()->after('registration_url');
                $table->date('registration_closes_on')->nullable()->after('registration_opens_on');
                $table->unsignedSmallInteger('capacity')->nullable()->after('registration_closes_on');
                $table->index('committee_code');
                $table->index('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('meetings', 'legacy_title')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['news_item_id']);
            $table->dropIndex(['committee_code']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'legacy_title', 'imported_original', 'committee_code', 'organiser', 'source', 'news_item_id',
                'status', 'status_note', 'starts_at', 'ends_at', 'timezone', 'online_url',
                'registration_opens_on', 'registration_closes_on', 'capacity',
            ]);
        });
    }
};
