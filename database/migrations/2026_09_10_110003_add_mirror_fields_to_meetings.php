<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The events calendar, mirrored. DATA_MODEL section 6, WS-07.
 *
 * events.crasa.org runs WordPress with The Events Calendar and publishes
 * every event as JSON, with its venue and its category already separated.
 * Forty-three events, from a 2024 spectrum workshop in Dar es Salaam to the
 * 46th EXCO in Malawi.
 *
 * THIS IS A MIRROR AND NOT A MANAGER. external_id and synced_at are what
 * make that true: a row here is a copy of a record whose master is on the
 * events host, and re-running the sync updates it rather than creating a
 * second one. Nothing here schedules, invites, registers, books or
 * reminds. That is a separate commercial agreement and CLAUDE.md forbids
 * it appearing in this codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function ($table) {
            // The events host's own identifier. Unique so a re-sync
            // updates rather than duplicating.
            $table->string('external_id', 80)->nullable()->after('id');

            // "Meeting", "Workshop", "AGM", "Webinar" — the host's own
            // taxonomy, kept because it is the one the Secretariat uses.
            $table->string('category', 60)->nullable()->after('kind');

            // The venue as the host records it, separate from location so
            // a country name and a venue name do not collapse into one.
            $table->string('venue', 200)->nullable()->after('location');
            $table->string('venue_country', 120)->nullable()->after('venue');

            $table->text('summary')->nullable()->after('agenda');
            $table->timestamp('synced_at')->nullable()->after('is_sample');

            $table->unique('external_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function ($table) {
            $table->dropUnique(['external_id']);
            $table->dropIndex(['category']);
            $table->dropColumn(['external_id', 'category', 'venue', 'venue_country', 'summary', 'synced_at']);
        });
    }
};
