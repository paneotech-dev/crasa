<?php

namespace Tests\Unit;

use App\Console\Commands\WriteLegacyPhotographs;
use PHPUnit\Framework\TestCase;

/**
 * The licence register survives a re-run of legacy:photographs.
 */
class LegacyLicenceLinesTest extends TestCase
{
    private function photograph(): array
    {
        return [
            'original_filename' => 'IMG_2143.jpg',
            'path' => 'images/legacy/crasa-courtesy-call-on-berec/img-2143.jpg',
            'subject' => 'CRASA courtesy call on "BEREC"',
            'width' => 4032,
            'height' => 2268,
        ];
    }

    public function test_a_hand_written_row_about_a_legacy_image_survives(): void
    {
        $manual = '"Legacy article picture: Doc2.png",images/legacy/x/doc2.png,crasa.org,,"The client\'s own material",No,2026-09-17,"Kept by name."';

        $lines = WriteLegacyPhotographs::licenceLines(['asset,where used', $manual], [$this->photograph()], '2026-09-20');

        $this->assertSame('asset,where used', $lines[0]);
        $this->assertSame($manual, $lines[1]);
        $this->assertCount(3, $lines);
    }

    public function test_a_re_run_replaces_its_rows_and_keeps_the_first_obtained_date(): void
    {
        $first = WriteLegacyPhotographs::licenceLines(['asset,where used'], [$this->photograph()], '2026-09-10');
        $second = WriteLegacyPhotographs::licenceLines($first, [$this->photograph()], '2026-09-16');

        $this->assertSame($first, $second);
        $this->assertSame('2026-09-10', str_getcsv($second[1], ',', '"', '\\')[6]);
    }

    public function test_a_new_photograph_is_dated_today_and_quotes_survive_the_round_trip(): void
    {
        $lines = WriteLegacyPhotographs::licenceLines(['asset,where used'], [$this->photograph()], '2026-09-16');
        $row = str_getcsv($lines[1], ',', '"', '\\');

        $this->assertCount(8, $row);
        $this->assertSame('2026-09-16', $row[6]);
        $this->assertStringStartsWith("CRASA courtesy call on 'BEREC'. 4032x2268.", $row[7]);
    }

    public function test_a_photograph_no_longer_imported_loses_its_row(): void
    {
        $first = WriteLegacyPhotographs::licenceLines(['asset,where used'], [$this->photograph()], '2026-09-10');

        $this->assertSame(['asset,where used'], WriteLegacyPhotographs::licenceLines($first, [], '2026-09-16'));
    }
}
