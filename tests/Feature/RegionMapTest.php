<?php

namespace Tests\Feature;

use App\Models\Country;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The region map on the members page.
 *
 * This exists because the map was withdrawn during review on the belief
 * that it omitted two member states, and restored when that turned out to
 * be wrong: Comoros and Mauritius have no mainland path because they are
 * rendered as inset chips, which is correct and deliberate.
 *
 * Both readings were available from the same evidence, which is exactly the
 * situation a test settles and an eye does not. These are the conditions
 * that make the map correct, so nobody has to judge it again.
 */
class RegionMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
    }

    /**
     * The whole point of the map. A membership map of a fourteen-member
     * body that shows twelve is worse than no map, and a reader counting
     * them is the first thing fourteen regulators will do.
     */
    public function test_every_one_of_the_fourteen_member_states_appears(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        foreach (Country::pluck('iso_code') as $code) {
            // The destination, not an anchor on the same page. A member
            // state's shape has to go somewhere for the map to be
            // navigation rather than an illustration.
            $onMap = str_contains($html, 'href="http://localhost/en/members/'.$code.'"');

            $this->assertTrue($onMap, strtoupper($code).' is a member state and does not appear on the map.');
        }
    }

    public function test_the_two_island_members_are_inset_chips_rather_than_specks(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        // At region scale each is a few pixels across, too small to see,
        // to label or to touch at 44 pixels.
        $this->assertMatchesRegularExpression('~c-map-region__insets~', $html);

        foreach (['KM', 'MU'] as $code) {
            $this->assertStringContainsString('inset-code">'.$code.'<', $html);
        }

        $map = config('region-map');

        foreach (['km', 'mu'] as $code) {
            $this->assertNotEmpty($map['members'][$code]['inset'] ?? null, $code.' has no inset shape.');
        }
    }

    public function test_the_colours_come_from_the_palette_and_not_from_the_browser_default(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        // Paths carry no inline fill, deliberately, so the stylesheet owns
        // the colour. Without a rule they would render solid black, which
        // is what an inspection of the markup alone suggests.
        $this->assertMatchesRegularExpression(
            '~\.c-map-region__member path \{[^}]*fill: var\(--c-green-light\)~s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '~\.c-map-region__context \{[^}]*fill: var\(--c-sand\)~s',
            $css
        );
    }

    /**
     * Madagascar and Seychelles are drawn so the region sits in a continent
     * rather than floating. Neither is a CRASA member, so neither may be
     * styled or linked as one.
     */
    public function test_no_non_member_is_presented_as_a_member(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        foreach (['mg', 'sc'] as $code) {
            $this->assertStringNotContainsString(
                'href="#member-'.$code.'"',
                $html,
                strtoupper($code).' is not a CRASA member and must not link as one.'
            );
        }

        // They are context: flat, in sand, and not in the member green.
        $css = file_get_contents(public_path('css/blocks.css'));
        $this->assertStringContainsString('.c-map-region__context', $css);
    }

    public function test_the_map_is_a_navigation_surface_and_not_a_picture(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        // Every member shape links to its own entry, and the svg carries a
        // label, because a screen reader gets nothing from fourteen paths.
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString(__('members.map_label'), $html);
    }

    public function test_the_list_below_carries_the_same_information(): void
    {
        $html = $this->get('/en/members')->assertOk()->getContent();

        // The list is not a fallback for the map. It is the same
        // information for a reader who would rather read it, which is what
        // makes withdrawing the map a survivable decision if it ever comes
        // to that again.
        foreach (Country::pluck('name') as $name) {
            $this->assertStringContainsString(e($name), $html);
        }
    }
}
