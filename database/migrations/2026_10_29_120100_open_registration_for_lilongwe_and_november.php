<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Registration open for the Lilongwe validation workshop and the November
 * Spectrum and Frequency Management Committee meeting. Only the two
 * registration dates on those two rows, found by slug; a database without
 * them is left as it is. Running it twice writes the same values.
 *
 * The workshop began on 28 October: an explicit closing date keeps its form
 * open until its last day (Meeting::registrationIsOpen).
 */
return new class extends Migration
{
    private const DATES = [
        'validation-workshop-crasa-website-2026' => ['2026-10-26', '2026-10-30'],
        '2nd-ordinary-meeting-of-the-crasa-spectrum-and-frequency-management-committee-2026' => ['2026-10-29', '2026-11-06'],
    ];

    public function up(): void
    {
        foreach (self::DATES as $slug => [$opens, $closes]) {
            DB::table('meetings')->where('slug', $slug)->update([
                'registration_opens_on' => $opens,
                'registration_closes_on' => $closes,
            ]);
        }
    }

    public function down(): void
    {
        // Undo only what this migration wrote.
        foreach (self::DATES as $slug => [$opens, $closes]) {
            DB::table('meetings')->where('slug', $slug)
                ->whereDate('registration_opens_on', $opens)->whereDate('registration_closes_on', $closes)
                ->update(['registration_opens_on' => null, 'registration_closes_on' => null]);
        }
    }
};
