<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\User;
use App\Support\ExtranetNavigation;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The extranet's own navigation.
 *
 * Two surfaces, two approved patterns, and neither borrows the other's.
 * Screen 04 draws the extranet with a navy left rail; screen 09 draws the
 * back office with a horizontal section bar in its header. The extranet had
 * been extending the back office layout, which put screen 09's pattern on a
 * surface that has one of its own.
 */
class ExtranetNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
    }

    public function test_the_extranet_renders_its_own_rail_and_not_the_back_office_header(): void
    {
        $user = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->first())->create();

        $html = $this->actingAs($user)->get('/en/extranet/folders')->assertOk()->getContent();

        $this->assertStringContainsString('class="e-rail"', $html);
        $this->assertStringContainsString('aria-label="Extranet"', $html);

        // The back office's own header must not be on this surface.
        $this->assertStringNotContainsString('a-header', $html);
    }

    /**
     * Screen 09 draws a horizontal bar for the back office and it stays.
     * An approved screen is not overridden by a pattern from another
     * platform, however good the pattern is.
     */
    public function test_the_back_office_keeps_the_horizontal_bar_screen_09_draws(): void
    {
        $html = $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/admin')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('class="e-rail"', $html);
        $this->assertStringContainsString('a-header', $html);
    }

    /**
     * The reader's own clearance, pinned to the bottom of the rail as the
     * screen draws it. A reader who does not know their clearance reads an
     * empty folder list as a fault rather than as a boundary.
     */
    public function test_the_rail_states_the_readers_clearance_and_its_country_scope(): void
    {
        $botswana = Country::where('iso_code', 'bw')->first();
        $user = User::factory()->memberStateRep($botswana)->create();

        $this->actingAs($user)
            ->get('/en/extranet/folders')
            ->assertOk()
            ->assertSee('Your clearance')
            ->assertSee('Member state representative')
            // Named, not implied. Escaped by the default, because the
            // apostrophe renders as an entity.
            ->assertSee("Botswana's own country folders");
    }

    public function test_a_reader_with_no_country_is_told_they_are_regional(): void
    {
        $clearance = ExtranetNavigation::clearance(User::factory()->administrator()->create());

        $this->assertSame('Administrator', $clearance['level']);
        $this->assertStringContainsString('regional material', $clearance['scope']);
    }

    public function test_no_navigation_at_all_when_nobody_is_signed_in(): void
    {
        $this->assertNull(ExtranetNavigation::clearance(null));
    }

    /**
     * Flat, not grouped. Screen 04 draws seven links and no group headings.
     * Grouping five items under three headings adds headings without adding
     * navigation, and the screen governs.
     */
    public function test_the_rail_is_flat_rather_than_grouped(): void
    {
        $user = User::factory()->memberStateRep(Country::first())->create();

        $html = $this->actingAs($user)->get('/en/extranet/folders')->assertOk()->getContent();
        $rail = substr($html, strpos($html, 'class="e-rail"'), strpos($html, 'e-main') - strpos($html, 'class="e-rail"'));

        // One list of links, no group headings inside it.
        $this->assertSame(1, substr_count($rail, 'e-rail__links'));
        $this->assertStringNotContainsString('e-rail__group', $rail);
    }

    /**
     * The current section is marked by ground and a 3px edge, not by colour
     * alone, and it says so to assistive technology.
     */
    public function test_the_current_section_is_marked_in_more_than_colour(): void
    {
        $user = User::factory()->memberStateRep(Country::first())->create();

        $this->actingAs($user)
            ->get('/en/extranet/folders')
            ->assertOk()
            ->assertSee('aria-current="page"', false);

        $css = File::get(public_path('css/admin.css'));
        $this->assertStringContainsString('border-left: 3px solid var(--c-green)', $css);
    }

    /**
     * The rail only lists what a reader can already open, which is a
     * convenience rather than the access control: every route behind these
     * links re-checks its own policy.
     */
    public function test_the_rail_is_a_convenience_and_not_the_access_control(): void
    {
        $namibia = Country::where('iso_code', 'na')->first();
        $rep = User::factory()->memberStateRep($namibia)->create();

        // Reaching a link the rail does not draw is still refused by policy.
        $this->actingAs($rep)->get('/en/admin/users')->assertForbidden();
    }
}
