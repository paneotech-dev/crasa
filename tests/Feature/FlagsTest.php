<?php

namespace Tests\Feature;

use App\Models\Country;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fourteen member state flags.
 *
 * A wrong flag on an intergovernmental platform is a diplomatic problem
 * rather than a design one, so none of these is drawn here and none is
 * approximated. Every file is an unmodified upstream file from flag-icons
 * 7.2.3, MIT licensed, and each was checked against its authoritative
 * description before installation. What was checked is recorded in
 * public/img/flags/LICENCES.csv.
 */
class FlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(AuthoritySeeder::class);
        $this->seed(PublicPagesSeeder::class);
    }

    public function test_every_member_state_has_a_flag(): void
    {
        foreach (Country::pluck('iso_code') as $iso) {
            $path = public_path('img/flags/'.$iso.'.svg');

            $this->assertFileExists($path, strtoupper($iso).' is a member state with no flag.');
            $this->assertGreaterThan(200, filesize($path), strtoupper($iso).'.svg is too small to be a flag.');
            $this->assertStringContainsString('<svg', file_get_contents($path));
        }
    }

    /**
     * Provenance for every one of them, so a question from COMESA about
     * where a national flag came from is answered from a file rather than
     * from memory.
     */
    public function test_every_flag_has_its_licence_and_its_verification_recorded(): void
    {
        $rows = array_map('str_getcsv', file(public_path('img/flags/LICENCES.csv')));
        $header = array_shift($rows);

        $recorded = [];

        foreach ($rows as $row) {
            $row = array_combine($header, $row);
            $recorded[$row['filename']] = $row;
        }

        foreach (Country::pluck('iso_code') as $iso) {
            $file = $iso.'.svg';

            $this->assertArrayHasKey($file, $recorded, $file.' is installed with no licence record.');
            $this->assertSame('MIT', $recorded[$file]['licence_type']);
            $this->assertNotEmpty($recorded[$file]['source_url'], $file.' records no source URL.');
            $this->assertNotEmpty(
                $recorded[$file]['verified_against'],
                $file.' records no check against an authoritative description.'
            );
        }

        $this->assertFileExists(public_path('img/flags/LICENSE.txt'), 'The MIT licence text is not retained.');
    }

    /**
     * The two with a superseded version still in wide circulation. Both are
     * easy to get wrong from a stale asset pack and neither error would be
     * noticed by anyone who was not from that country.
     */
    public function test_lesotho_is_the_2006_flag_and_malawi_is_the_2012_one(): void
    {
        // Lesotho 2006: blue, white and green with the black mokorotlo. Not
        // the 1987 blue, white, green and red one.
        $lesotho = file_get_contents(public_path('img/flags/ls.svg'));
        $this->assertStringContainsString('#00209f', $lesotho, 'Lesotho is missing its blue band.');
        $this->assertStringContainsString('#009543', $lesotho, 'Lesotho is missing its green band.');
        $this->assertStringContainsString('stroke="#000"', $lesotho, 'Lesotho is missing the black mokorotlo.');

        // Malawi 2012: black, red and green with the red rising sun on the
        // black band. Not the 2010 white full sun on red.
        $malawi = file_get_contents(public_path('img/flags/mw.svg'));
        $this->assertStringContainsString('#21873b', $malawi, 'Malawi is missing its green band.');
        $this->assertStringContainsString('#f31509', $malawi, 'Malawi is missing the red rising sun.');
    }

    /**
     * A flag is not accessible on its own. It cannot be read out, it cannot
     * be searched for, and a reader who does not know fourteen Southern
     * African flags by sight learns nothing from it. So it is decorative,
     * and the country name beside it carries the meaning.
     */
    public function test_a_flag_is_never_the_only_identifier(): void
    {
        foreach (['/en/members', '/en/members/bw'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            preg_match_all('~<img[^>]*class="c-flag[^>]*>~', $html, $flags);
            $this->assertNotEmpty($flags[0], $url.' shows no flags at all.');

            foreach ($flags[0] as $flag) {
                $this->assertStringContainsString(
                    'alt=""',
                    $flag,
                    'A flag carries alt text, which means it is being asked to identify something. The name beside it does that.'
                );
            }
        }

        // And the name is there.
        $this->get('/en/members')->assertSee('Botswana');
    }

    public function test_flags_appear_everywhere_a_member_state_is_named_in_a_list(): void
    {
        $this->get('/en/members')->assertOk()->assertSee('c-flag', false);
        $this->get('/en/members/za')->assertOk()->assertSee('c-flag', false);
        $this->get('/en/comparator?mode=compare&countries=bw,za')->assertOk()->assertSee('c-flag', false);
    }

    /**
     * Served as files, not inlined. Several of these SVGs carry clipPath
     * ids, and fourteen inlined copies on one page is fourteen id
     * collisions and fourteen flags clipped by the wrong mask.
     */
    public function test_flags_are_served_as_files_rather_than_inlined(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        $this->assertStringContainsString('/img/flags/', $html);
        $this->assertStringNotContainsString('flag-icons-bw', $html, 'A flag SVG has been inlined into the page.');
    }

    /**
     * A member state whose flag file is missing renders no image rather than
     * a broken one, and its name is unaffected.
     */
    public function test_a_missing_flag_renders_nothing_rather_than_a_broken_image(): void
    {
        $this->blade('<x-flag iso="xx" />')->assertDontSee('<img', false);
    }
}
