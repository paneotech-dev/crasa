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

    /**
     * The extranet and the back office share one shell.
     *
     * Screen 04 draws a navy left rail 220 to 280px with the mark, a
     * label, flat links with Lucide icons and the clearance at the bottom.
     * That is the shell at 264px, so the extranet stopped carrying a second
     * implementation of it on 10 September 2026. Two shells for two
     * signed-in surfaces is two things to keep in step.
     */
    public function test_the_extranet_renders_the_shared_shell(): void
    {
        $user = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->first())->create();

        $html = $this->actingAs($user)->get('/en/extranet/folders')->assertOk()->getContent();

        $this->assertStringContainsString('class="a-side"', $html);
        $this->assertStringContainsString('aria-label="Extranet"', $html);

        /*
         * Its own navigation, not the administration's. Asserted on the
         * heading markup rather than on the words: the extranet's own
         * "Regulatory data" row contains the administration group's label
         * as a substring, and a test that trips on that is testing English
         * rather than navigation.
         */
        $this->assertStringContainsString(
            '>'.__('extranet.side_group_workspace').'</h2>',
            $html
        );
        $this->assertStringNotContainsString(
            '>'.__('admin.side_group_regulatory').'</h2>',
            $html
        );

        // And not the public chrome.
        $this->assertStringNotContainsString('c-footer', $html);
    }

    /**
     * Screen 09's horizontal section bar is overruled.
     *
     * Ruled on 10 September 2026 and logged as a disclosed variation: with
     * twenty-four destinations the bar wrapped two and three rows deep
     * before any content began, and the back office is the surface the
     * Secretariat uses every day. Screen 09's content is untouched, which
     * the tests below this one still hold.
     */
    public function test_the_back_office_carries_the_sidebar_and_not_the_section_bar(): void
    {
        $html = $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/admin')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="a-side"', $html);
        $this->assertStringContainsString('class="a-top"', $html);

        // The old header and the old rail are both gone.
        $this->assertStringNotContainsString('class="e-rail"', $html);
        $this->assertStringNotContainsString('class="a-header"', $html);
    }

    /**
     * The reader's own clearance, stated in their own terms.
     *
     * Screen 04 pins it to the bottom of the rail. It moved to the
     * dashboard with the shell: the rail now carries a row per committee
     * the reader sits on, and a boundary statement under a scrolling list
     * is a boundary statement nobody reads. On the dashboard it sits above
     * the folders it describes.
     *
     * A reader who does not know their clearance reads an empty folder
     * list as a fault rather than as a boundary.
     */
    public function test_the_dashboard_states_the_readers_clearance_and_its_country_scope(): void
    {
        $botswana = Country::where('iso_code', 'bw')->first();
        $user = User::factory()->memberStateRep($botswana)->create();

        $this->actingAs($user)
            ->get('/en/extranet/dashboard')
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
     * Grouped, which is a departure from screen 04 and is stated as one.
     *
     * The screen draws seven links flat, and flat was right while that was
     * all there was. The rail now carries a row per committee the reader
     * sits on, and seven ungrouped items plus a variable list of workspaces
     * is where a reader starts scanning rather than reaching.
     */
    public function test_the_extranet_sidebar_is_grouped(): void
    {
        $user = User::factory()->memberStateRep(Country::first())->create();

        $html = $this->actingAs($user)->get('/en/extranet/folders')->assertOk()->getContent();

        foreach (['workspace', 'region', 'account'] as $group) {
            $this->assertStringContainsString(__('extranet.side_group_'.$group), $html);
        }

        // A reader who sits on no committee gets no committees heading.
        $this->assertStringNotContainsString(__('extranet.side_group_committees'), $html);
    }

    /** And a reader who does sit on one gets a row for it. */
    public function test_a_seat_becomes_a_row_in_the_sidebar(): void
    {
        $this->seed(\Database\Seeders\CommitteeSeeder::class);

        $user = User::factory()->committeeMember(Country::first())->create();
        $committee = \App\Models\Committee::where('slug', 'spectrum-and-frequency-management')->sole();

        $user->committees()->attach($committee->id, [
            'role' => \App\Enums\CommitteeRole::Member->value,
            'is_sample' => true,
        ]);

        $html = $this->actingAs($user)->get('/en/extranet/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString(__('extranet.side_group_committees'), $html);
        $this->assertStringContainsString('<span>'.$committee->name.'</span>', $html);
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
