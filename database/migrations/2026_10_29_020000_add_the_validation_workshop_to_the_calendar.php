<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INC-08, EV-11, WP-12: the validation workshop running from 28 to 30
 * October 2026 was not on the member calendar, which showed "No meetings"
 * for the month the room was sitting in.
 *
 * Written through the query builder, not the model, so no notification
 * fires. Matched by its slug, so running it twice changes nothing.
 *
 * Content, not schema: the test database is built from these migrations
 * and is meant to start empty, so it is skipped there.
 */
return new class extends Migration
{
    private const SLUG = 'validation-workshop-crasa-website-2026';

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $now = now();

        $row = [
            'title' => 'Validation Workshop for the Design and Re-Development of the CRASA Website',
            'kind' => 'workshop',
            'category' => 'Workshop',
            'starts_on' => '2026-10-28',
            'ends_on' => '2026-10-30',
            'date_unknown' => false,
            'location' => 'Lilongwe, Malawi',
            'venue' => 'Annavilla7 Hotel',
            'venue_country' => 'Malawi',
            'summary' => 'Hosted by COMESA and CRASA.',
            'clearance' => 'member',
            'committee_id' => null,
            'is_sample' => false,
            'updated_at' => $now,
        ];

        if (DB::table('meetings')->where('slug', self::SLUG)->exists()) {
            DB::table('meetings')->where('slug', self::SLUG)->update($row);

            return;
        }

        DB::table('meetings')->insert($row + ['slug' => self::SLUG, 'created_at' => $now]);
    }

    public function down(): void
    {
        DB::table('meetings')->where('slug', self::SLUG)->delete();
    }
};
