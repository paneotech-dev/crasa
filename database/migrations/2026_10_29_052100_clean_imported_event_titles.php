<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The imported event titles, cleaned (S5).
 *
 * The events host wrote invitations as titles: "Invitation to the 1st
 * ordinary meeting of the CRASA Postal Committee (PC) in Gaborone,
 * Botswana, 22nd to 24th July 2026". The dates are already in their own
 * fields and the place belongs in location, so the title keeps only what
 * the meeting is. Where the place was only in the title, it moves to
 * location; where the body that meets is named, it goes to committee_code,
 * and to committee_id where the member area has a record for it.
 *
 * The original title is kept in legacy_title and every other value this
 * changes is kept in imported_original, so down() puts each one back.
 * Matched by slug. Running it twice changes nothing: a row that already
 * has a legacy_title keeps its first original.
 *
 * The 40th EXCO came across as three one-day rows with the same title,
 * one per day of a three-day meeting. They become one row, 7 to 9 October
 * 2024; the two removed rows are kept whole in the first row's
 * imported_original and come back on down().
 */
return new class extends Migration
{
    /**
     * slug => [title, location, venue_country, code, committee slug, kind].
     * null for location or country means "leave as it is".
     *
     * @var array<string, array{0: string, 1: ?string, 2: ?string, 3: string, 4: ?string, 5: string}>
     */
    private const ROWS = [
        '1st-meetings-of-the-crasa-human-resources-development-committee-hrdc-finance-and-audit-committee-fac' => ['1st meetings of the CRASA Human Resources Development Committee (HRDC) and Finance and Audit Committee (FAC)', null, null, 'HRDC', 'human-and-institutional-capacity', 'committee'],
        '1st-meeting-of-the-crasa-universal-access-service-committee' => ['1st meeting of the CRASA Universal Access and Service Committee (UASC)', null, null, 'UASC', null, 'committee'],
        'crasa-g5-regulatory-collaboration-summit-2026' => ['CRASA G5 Regulatory Collaboration Summit 2026', null, null, 'CRASA', null, 'summit'],
        'workshop-on-emerging-issues-on-spectrum-planning-and-management' => ['Workshop on emerging issues in spectrum planning and management', 'Dar es Salaam, Tanzania', 'Tanzania', 'SFMC', 'spectrum-and-frequency-management', 'workshop'],
        '1st-electronic-communications-committee' => ['1st meeting of the Electronic Communications Committee (ECC)', 'Dar es Salaam, Tanzania', 'Tanzania', 'ECC', null, 'committee'],
        '1st-universal-service-and-access-committee-meeting' => ['1st meeting of the Universal Service and Access Committee', 'Kinshasa, DR Congo', 'DR Congo', 'UASC', null, 'committee'],
        'postal-committee' => ['Meeting of the Postal Committee', 'Namibia', 'Namibia', 'PC', 'postal-and-logistics', 'committee'],
        'human-resource-and-development-committee-meeting' => ['Meeting of the Human Resource and Development Committee', 'Mozambique', 'Mozambique', 'HRDC', 'human-and-institutional-capacity', 'committee'],
        '1st-finance-and-audit-committee-meeting' => ['1st meeting of the Finance and Audit Committee', 'Mozambique', 'Mozambique', 'FAC', null, 'committee'],
        '1st-meeting-of-the-legal-and-policy-committee-lpc-202425-windhoek-namibia-16-19-september-2024' => ['1st meeting of the Legal and Policy Committee (LPC) 2024/25', 'Windhoek, Namibia', 'Namibia', 'LPC', 'legal-and-regulatory-affairs', 'committee'],
        'first-meeting-of-consumer-committee-from-30th-september-to-3rd-october-2024' => ['1st meeting of the Consumer Committee', null, null, 'CC', 'consumer-affairs-and-protection', 'committee'],
        '40th-ordinary-meeting-of-the-executive-committee-7th-to-9th-october-2024' => ['40th ordinary meeting of the Executive Committee (EXCO)', 'Eswatini', 'Eswatini', 'EXCO', null, 'governance'],
        'webinar-digital-radio-broadcasting-implementation-in-sadc' => ['Webinar on digital radio broadcasting implementation in SADC', 'Online', null, 'CRASA', null, 'webinar'],
        '1st-meeting-of-the-economic-regulation-committee-erc-202425' => ['1st meeting of the Economic Regulation Committee (ERC) 2024/25', 'Gaborone, Botswana', 'Botswana', 'ERC', 'economics', 'committee'],
        'conference-and-2nd-meeting-of-the-crasa-postal-committee-202425' => ['Conference and 2nd meeting of the CRASA Postal Committee 2024/25', null, null, 'PC', 'postal-and-logistics', 'committee'],
        '2nd-meeting-of-the-crasa-electronic-communications-committee-ecc' => ['2nd meeting of the CRASA Electronic Communications Committee (ECC)', 'Dar es Salaam, Tanzania', 'Tanzania', 'ECC', null, 'committee'],
        'invitation-to-the-2nd-meeting-of-finance-and-audit-committee-fac-202425' => ['2nd meeting of the Finance and Audit Committee (FAC) 2024/25', 'Gaborone, Botswana', 'Botswana', 'FAC', null, 'committee'],
        'invitation-to-the-2nd-meeting-of-human-resource-development-committee-hrdc-202425' => ['2nd meeting of the Human Resource Development Committee (HRDC) 2024/25', 'Gaborone, Botswana', 'Botswana', 'HRDC', 'human-and-institutional-capacity', 'committee'],
        'invitation-to-the-41st-ordinary-meeting-of-the-executive-committee-exco' => ['41st ordinary meeting of the Executive Committee (EXCO)', 'Maun, Botswana', 'Botswana', 'EXCO', null, 'governance'],
        'the-2nd-meeting-of-the-legal-and-policy-committee-lpc-crasa' => ['2nd meeting of the Legal and Policy Committee (LPC)', null, null, 'LPC', 'legal-and-regulatory-affairs', 'committee'],
        'notification-of-crasa-14th-annual-general-meeting-agm' => ['CRASA 14th Annual General Meeting (AGM)', 'Lesotho', 'Lesotho', 'AGM', null, 'governance'],
        'crasa-preparatory-workshop-1st-postal-committee-meeting-202526' => ['CRASA preparatory workshop and 1st Postal Committee meeting 2025/26', null, null, 'PC', 'postal-and-logistics', 'committee'],
        'workshop-on-regional-broadband-targets-1st-meeting-of-crasa-universal-access-and-service-committee-uasc-202526' => ['Workshop on regional broadband targets and 1st meeting of the CRASA Universal Access and Service Committee (UASC) 2025/26', null, null, 'UASC', null, 'committee'],
        'the-1st-meeting-of-the-crasa-consumer-committee-in-kinshasa-drc' => ['1st meeting of the CRASA Consumer Committee', 'Kinshasa, DR Congo', 'DR Congo', 'CC', 'consumer-affairs-and-protection', 'committee'],
        '1st-meeting-of-the-crasa-economic-regulation-committee-erc-in-kinshasa-drc' => ['1st meeting of the CRASA Economic Regulation Committee (ERC)', 'Kinshasa, DR Congo', 'DR Congo', 'ERC', 'economics', 'committee'],
        '1st-meetings-of-the-crasa-human-resources-development-committee-hrdc-and-finance-and-audit-committee-fac-202526-dar-es-salaam-tanzania' => ['1st meetings of the CRASA Human Resources Development Committee (HRDC) and Finance and Audit Committee (FAC) 2025/26', 'Dar es Salaam, Tanzania', 'Tanzania', 'HRDC', 'human-and-institutional-capacity', 'committee'],
        '1st-meeting-of-the-electronic-communications-committee-ecc-202526' => ['1st meeting of the Electronic Communications Committee (ECC) 2025/26', null, null, 'ECC', null, 'committee'],
        '1st-meeting-of-the-crasa-legal-and-policy-committee-202526' => ['1st meeting of the CRASA Legal and Policy Committee 2025/26', null, null, 'LPC', 'legal-and-regulatory-affairs', 'committee'],
        'validation-workshop-for-the-sadc-regional-postal-disaster-risk-management-framework-2nd-meeting-of-the-crasa-postal-committee-202526' => ['Validation workshop for the SADC Regional Postal Disaster Risk Management Framework and 2nd meeting of the CRASA Postal Committee 2025/26', null, null, 'PC', 'postal-and-logistics', 'committee'],
        'second-meeting-of-the-crasa-electronic-communications-committee-ecc-202526' => ['2nd meeting of the CRASA Electronic Communications Committee (ECC) 2025/26', null, null, 'ECC', null, 'committee'],
        '2nd-meetings-of-the-crasa-human-resources-development-committee-hrdc-and-finance-audit-committee-fac-202526' => ['2nd meetings of the CRASA Human Resources Development Committee (HRDC) and Finance and Audit Committee (FAC) 2025/26', null, null, 'HRDC', 'human-and-institutional-capacity', 'committee'],
        '2nd-meeting-of-the-consumer-committee-cc-consumer-protection-workshop-on-digital-platforms' => ['2nd meeting of the Consumer Committee (CC) and consumer protection workshop on digital platforms', 'Zambia', 'Zambia', 'CC', 'consumer-affairs-and-protection', 'committee'],
        '2nd-meeting-of-the-legal-and-policy-committee-lpc' => ['2nd meeting of the Legal and Policy Committee (LPC)', 'Kinshasa, DR Congo', 'DR Congo', 'LPC', 'legal-and-regulatory-affairs', 'committee'],
        '2nd-meeting-of-the-economic-regulation-committee-erc-and-workshop' => ['2nd meeting of the Economic Regulation Committee (ERC) and workshop', 'Zimbabwe', 'Zimbabwe', 'ERC', 'economics', 'committee'],
        '2nd-ordinary-meeting-of-the-crasa-universal-access-and-service-committee-uasc' => ['2nd ordinary meeting of the CRASA Universal Access and Service Committee (UASC)', 'Dar es Salaam, Tanzania', 'Tanzania', 'UASC', null, 'committee'],
        'crasa-g5-summit-2026' => ['CRASA G5 Summit 2026', 'Malawi', 'Malawi', 'CRASA', null, 'summit'],
        'invitation-to-crasa-15th-annual-general-meeting-16th-to-17th-april-2026-lilongwe-malawi' => ['CRASA 15th Annual General Meeting (AGM)', 'Lilongwe, Malawi', 'Malawi', 'AGM', null, 'governance'],
        '1st-meeting-of-the-crasa-information-technology-steering-committee-itsc' => ['1st meeting of the CRASA Information Technology Steering Committee (ITSC)', null, null, 'ITSC', null, 'committee'],
        'invitation-to-the-1st-crasa-universal-access-and-service-committee-uasc-meeting-in-gaborone-botswana-13-17-july-2026' => ['1st meeting of the CRASA Universal Access and Service Committee (UASC)', 'Gaborone, Botswana', 'Botswana', 'UASC', null, 'committee'],
        'invitation-to-the-1st-ordinary-meeting-of-the-crasa-postal-committee-pc-in-gaborone-botswana-22nd-to-24th-july-2026' => ['1st ordinary meeting of the CRASA Postal Committee (PC)', 'Gaborone, Botswana', 'Botswana', 'PC', 'postal-and-logistics', 'committee'],
        '1st-ordinary-meeting-of-the-crasa-legal-and-policy-committee-lpc-johannesburg-south-africa' => ['1st ordinary meeting of the CRASA Legal and Policy Committee (LPC)', 'Johannesburg, South Africa', 'South Africa', 'LPC', 'legal-and-regulatory-affairs', 'committee'],
        '1st-crasa-hrdc-and-frac-meetings-202627' => ['1st CRASA HRDC and FRAC meetings 2026/27', null, null, 'HRDC', 'human-and-institutional-capacity', 'committee'],
        'invitation-to-the-1st-ordinary-meeting-of-the-crasa-strategic-planning-committee-cspc-gaborone-botswana-2nd-to-4th-september-2026' => ['1st ordinary meeting of the CRASA Strategic Planning Committee (CSPC)', 'Gaborone, Botswana', 'Botswana', 'CSPC', null, 'committee'],
        '46th-ordinary-meeting-of-the-crasa-executive-committee-exco' => ['46th ordinary meeting of the CRASA Executive Committee (EXCO)', 'Malawi', 'Malawi', 'EXCO', null, 'governance'],
        '2nd-ordinary-meeting-of-the-crasa-spectrum-and-frequency-management-committee-2026' => ['2nd ordinary meeting of the CRASA Spectrum and Frequency Management Committee', null, null, 'SFMC', 'spectrum-and-frequency-management', 'committee'],
        'closed-committee-session' => ['Closed committee session', null, null, 'SFMC', 'spectrum-and-frequency-management', 'committee'],
        'regional-workshop' => ['Regional workshop of member regulators', null, null, 'CRASA', null, 'workshop'],
        'validation-workshop-crasa-website-2026' => ['Validation workshop for the design and re-development of the CRASA website', null, null, 'CRASA', null, 'workshop'],
    ];

    private const LOCAL = [
        '2nd-ordinary-meeting-of-the-crasa-spectrum-and-frequency-management-committee-2026',
        'closed-committee-session', 'regional-workshop', 'validation-workshop-crasa-website-2026',
    ];

    private const EXCO = '40th-ordinary-meeting-of-the-executive-committee-7th-to-9th-october-2024';

    private const TRACKED = ['title', 'location', 'venue', 'venue_country', 'committee_code', 'committee_id', 'kind', 'source', 'ends_on'];

    public function up(): void
    {
        if (app()->runningUnitTests() || ! Schema::hasColumn('meetings', 'legacy_title')) {
            return;
        }

        $committees = DB::table('committees')->pluck('id', 'slug');
        $now = now();

        foreach (self::ROWS as $slug => [$title, $location, $country, $code, $committee, $kind]) {
            $row = DB::table('meetings')->where('slug', $slug)->first();

            if ($row === null) {
                continue;
            }

            $update = [
                'title' => $title,
                'committee_code' => $code,
                'kind' => $kind,
                // The four rows added on this platform (sample rows and the
                // validation workshop) did not come from the events host.
                'source' => $row->source ?? (in_array($slug, self::LOCAL, true) ? null : 'events_host'),
                'updated_at' => $now,
            ];

            if ($location !== null) {
                $update['location'] = $location;
                $update['venue_country'] = $country;

                // The host's "venue" was the city again; a city is not a venue.
                if ($row->venue !== null && (str_contains(strtolower($location), strtolower((string) $row->venue)) || $row->venue === $row->location)) {
                    $update['venue'] = null;
                }
            }

            if ($committee !== null && $row->committee_id === null && isset($committees[$committee])) {
                $update['committee_id'] = $committees[$committee];
            }

            if ($slug === self::EXCO) {
                $update['ends_on'] = '2024-10-09';
            }

            if ($row->legacy_title === null) {
                $original = [];
                foreach (self::TRACKED as $field) {
                    if (array_key_exists($field, $update)) {
                        $original[$field] = $row->{$field};
                    }
                }

                if ($slug === self::EXCO) {
                    $original['merged'] = DB::table('meetings')
                        ->whereIn('slug', [self::EXCO.'-2', self::EXCO.'-3'])
                        ->get()->map(fn ($r) => (array) $r)->all();
                }

                $update['legacy_title'] = $row->title;
                $update['imported_original'] = json_encode($original, JSON_UNESCAPED_UNICODE);
            }

            DB::table('meetings')->where('slug', $slug)->update($update);
        }

        // The two duplicate EXCO days, once the merged row holds them.
        if (DB::table('meetings')->where('slug', self::EXCO)->whereNotNull('imported_original')->exists()) {
            $ids = DB::table('meetings')->whereIn('slug', [self::EXCO.'-2', self::EXCO.'-3'])->pluck('id');
            if ($ids->isNotEmpty() && DB::table('delegate_registrations')->whereIn('meeting_id', $ids)->doesntExist()) {
                DB::table('meetings')->whereIn('id', $ids)->delete();
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('meetings', 'legacy_title')) {
            return;
        }

        foreach (array_keys(self::ROWS) as $slug) {
            $row = DB::table('meetings')->where('slug', $slug)->first();

            if ($row === null || $row->legacy_title === null) {
                continue;
            }

            $original = json_decode((string) $row->imported_original, true) ?: [];
            $merged = $original['merged'] ?? [];
            unset($original['merged']);

            DB::table('meetings')->where('slug', $slug)->update($original + [
                'title' => $row->legacy_title,
                'legacy_title' => null,
                'imported_original' => null,
            ]);

            foreach ($merged as $removed) {
                if (DB::table('meetings')->where('slug', $removed['slug'])->doesntExist()) {
                    $keep = array_intersect_key($removed, array_flip(\Illuminate\Support\Facades\Schema::getColumnListing('meetings')));
                    DB::table('meetings')->insert($keep);
                }
            }
        }
    }
};
