<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Projects (S8, 29 October 2026): fill the fields each project's own text
 * already states, so the cards and the facts panel can show them. Nothing
 * is added that the published description does not say.
 *
 * - The postal quality of service project is run "in collaboration with the
 *   Universal Postal Union (UPU)".
 * - The IMT 2020 roadmap "has drawn partnership with" ITU, ICNIRP, GSMA,
 *   Ericsson and Detecon, and is a telecommunications project (5G).
 *
 * Only empty fields are filled, matched by slug, so a value the Secretariat
 * has entered since is kept. down() clears exactly what this wrote.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'crasa-upu-regional-postal-quality-of-service-project' => [
                'partner' => 'Universal Postal Union (UPU)',
            ],
            'imt-2020-including-5g-technologies-deployment-roadmap-for-sadc' => [
                'partner' => 'ITU, ICNIRP, GSMA, Ericsson, Detecon',
                'sector' => 'telecommunications',
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->rows() as $slug => $row) {
            DB::table('projects')->where('slug', $slug)->whereNull('partner')
                ->update(['partner' => $row['partner'], 'updated_at' => now()]);

            if (isset($row['sector'])) {
                $sectorId = DB::table('sectors')->where('slug', $row['sector'])->value('id');
                if ($sectorId) {
                    DB::table('projects')->where('slug', $slug)->whereNull('sector_id')
                        ->update(['sector_id' => $sectorId, 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->rows() as $slug => $row) {
            DB::table('projects')->where('slug', $slug)->where('partner', $row['partner'])->update(['partner' => null]);

            if (isset($row['sector'])) {
                $sectorId = DB::table('sectors')->where('slug', $row['sector'])->value('id');
                DB::table('projects')->where('slug', $slug)->where('sector_id', $sectorId)->update(['sector_id' => null]);
            }
        }
    }
};
