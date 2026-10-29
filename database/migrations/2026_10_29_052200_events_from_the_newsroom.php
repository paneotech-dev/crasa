<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Past meetings that only the newsroom recorded (E3).
 *
 * Four newsroom items report a meeting. Two of those meetings were already
 * in the calendar from the events host (the 15th AGM in Lilongwe and the
 * Postal Committee in Windhoek): they gain the news link, the host and the
 * place the report gives. Two were not (the AI workshop for HR
 * professionals in Dar es Salaam and the 13th AGM in Ezulwini): they are
 * created from the report and marked source = newsroom, which the back
 * office shows as "Imported from the newsroom".
 *
 * Every fact below is in the newsroom item it links to. Matched by slug on
 * both sides; running it twice changes nothing.
 */
return new class extends Migration
{
    /** Existing meetings: meeting slug => [news slug, organiser, location, country, venue]. */
    private const LINKED = [
        'invitation-to-crasa-15th-annual-general-meeting-16th-to-17th-april-2026-lilongwe-malawi' => [
            'crasa-15th-annual-general-meeting-lilongwe-malawi-16th-to-17th-april-2026',
            'Malawi Communications Regulatory Authority (MACRA)', 'Lilongwe, Malawi', 'Malawi', null,
        ],
        'validation-workshop-for-the-sadc-regional-postal-disaster-risk-management-framework-2nd-meeting-of-the-crasa-postal-committee-202526' => [
            'crasa-postal-committee-advances-regional-resilience-and-cooperation',
            'Communications Regulatory Authority of Namibia (CRAN)', 'Windhoek, Namibia', 'Namibia', null,
        ],
    ];

    /** New meetings, created from the report. */
    private const CREATED = [
        'workshop-on-artificial-intelligence-for-hr-professionals-2025' => [
            'news' => 'crasa-hosts-workshop-on-artificial-intelligenceai-for-human-resourcehr-professionals-in-dar-es-salaam-tanzania',
            'title' => 'Workshop on artificial intelligence for human resource professionals',
            'starts_on' => '2025-08-25', 'ends_on' => '2025-08-25',
            'location' => 'Dar es Salaam, Tanzania', 'venue' => null, 'venue_country' => 'Tanzania',
            'organiser' => 'Tanzania Communications Regulatory Authority (TCRA)',
            'committee_code' => 'HRDC', 'committee' => 'human-and-institutional-capacity', 'kind' => 'workshop',
            'summary' => 'A workshop on artificial intelligence in human resource management, covering AI-driven recruitment, employee performance analytics and workflow automation. Hosted by TCRA.',
        ],
        'crasa-13th-annual-general-meeting-2024' => [
            'news' => 'crasa-13th-annual-general-meeting',
            'title' => 'CRASA 13th Annual General Meeting (AGM)',
            'starts_on' => '2024-04-11', 'ends_on' => '2024-04-12',
            'location' => 'Ezulwini, Eswatini', 'venue' => 'Royal Villas', 'venue_country' => 'Eswatini',
            'organiser' => 'Eswatini Communications Commission (ESCCOM)',
            'committee_code' => 'AGM', 'committee' => null, 'kind' => 'governance',
            'summary' => 'The 13th Annual General Meeting noted progress on the CRASA Strategic Plan 2023/28. Hosted by ESCCOM; twelve CRASA Members attended.',
        ],
    ];

    public function up(): void
    {
        if (app()->runningUnitTests() || ! Schema::hasColumn('meetings', 'news_item_id')) {
            return;
        }

        $news = DB::table('news_items')->pluck('id', 'slug');
        $committees = DB::table('committees')->pluck('id', 'slug');
        $now = now();

        foreach (self::LINKED as $slug => [$newsSlug, $organiser, $location, $country, $venue]) {
            $row = DB::table('meetings')->where('slug', $slug)->first();
            if ($row === null) {
                continue;
            }

            DB::table('meetings')->where('slug', $slug)->update(array_filter([
                'news_item_id' => $news[$newsSlug] ?? null,
                'organiser' => $row->organiser ?? $organiser,
                'location' => $row->location ?? $location,
                'venue_country' => $row->venue_country ?? $country,
                'venue' => $row->venue ?? $venue,
            ], fn ($v) => $v !== null) + ['updated_at' => $now]);
        }

        // The validation workshop's hosts, from its own record (020000).
        DB::table('meetings')->where('slug', 'validation-workshop-crasa-website-2026')->whereNull('organiser')
            ->update(['organiser' => 'COMESA and CRASA']);

        foreach (self::CREATED as $slug => $event) {
            $row = [
                'title' => $event['title'],
                'starts_on' => $event['starts_on'],
                'ends_on' => $event['ends_on'],
                'date_unknown' => false,
                'location' => $event['location'],
                'venue' => $event['venue'],
                'venue_country' => $event['venue_country'],
                'organiser' => $event['organiser'],
                'committee_code' => $event['committee_code'],
                'committee_id' => $event['committee'] ? ($committees[$event['committee']] ?? null) : null,
                'kind' => $event['kind'],
                'category' => $event['kind'] === 'governance' ? 'AGM' : 'Workshop',
                'summary' => $event['summary'],
                'clearance' => 'public',
                'status' => 'scheduled',
                'source' => 'newsroom',
                'news_item_id' => $news[$event['news']] ?? null,
                'is_sample' => false,
                'updated_at' => $now,
            ];

            if (DB::table('meetings')->where('slug', $slug)->exists()) {
                DB::table('meetings')->where('slug', $slug)->update($row);
            } else {
                DB::table('meetings')->insert($row + ['slug' => $slug, 'created_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('meetings', 'news_item_id')) {
            return;
        }

        DB::table('meetings')->whereIn('slug', array_keys(self::CREATED))->where('source', 'newsroom')->delete();

        DB::table('meetings')->whereIn('slug', array_keys(self::LINKED))->update([
            'news_item_id' => null,
            'organiser' => null,
        ]);

        // Windhoek came from the report; the events host had no place.
        DB::table('meetings')
            ->where('slug', 'validation-workshop-for-the-sadc-regional-postal-disaster-risk-management-framework-2nd-meeting-of-the-crasa-postal-committee-202526')
            ->update(['location' => null, 'venue_country' => null]);
    }
};
