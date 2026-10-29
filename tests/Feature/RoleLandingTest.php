<?php

namespace Tests\Feature;

use App\Enums\CommitteeRole;
use App\Models\Committee;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Where each role lands, and what it can and cannot see when it gets there.
 *
 * Everybody used to land on /en/account: the one screen that says nothing
 * about what the reader signed in to do. A member state representative signs
 * in to file a return and a committee member to read papers, and putting
 * both in front of a form for changing their own password is asking them to
 * go and find the platform.
 *
 * THE ROUTE IS A SUGGESTION AND NOT A GRANT. Every one of these also asserts
 * what the role cannot reach, by hitting the route directly rather than by
 * looking for a missing link. A hidden button is not access control, and a
 * landing page is not a permission.
 */
class RoleLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(HomepageSeeder::class);
    }

    private function botswana(): Country
    {
        return Country::where('iso_code', 'bw')->sole();
    }

    /**
     * Sign in the way a person does, through the form.
     *
     * Not actingAs. Where the reader lands is decided by
     * LoginController::destinationFor, and actingAs never runs it: the
     * whole subject of this file would be skipped by the shortcut that
     * makes the file easy to write.
     */
    private function signIn(User $user): \Illuminate\Testing\TestResponse
    {
        $user->forceFill(['password' => \Illuminate\Support\Facades\Hash::make('correct-horse-battery-42')])->save();

        return $this->post('/en/login', [
            'email' => $user->email,
            'password' => 'correct-horse-battery-42',
        ]);
    }

    /* Administrator ------------------------------------------------------ */

    public function test_an_administrator_lands_on_the_dashboard(): void
    {
        $admin = User::factory()->administrator()->create();

        // A confirmed second factor is a second step, so the form hands the
        // reader to the challenge and the challenge hands them on. Both
        // halves asserted: the redirect, and where it resolves to.
        $this->signIn($admin)->assertRedirect('/en/two-factor/challenge');
        $this->assertSame(
            route('admin.dashboard', ['locale' => 'en']),
            \App\Http\Controllers\Auth\LoginController::destinationFor($admin)
        );

        $html = $this->actingAs($admin)->get('/en/admin')->assertOk()->getContent();

        // Three things that must be there. Screen 09's dashboard.
        $this->assertStringContainsString('<span>'.__('admin.nav_pages').'</span>', $html);
        $this->assertStringContainsString(__('admin.dash_stat_publications'), $html);
        $this->assertStringContainsString('<span>'.__('admin.nav_users').'</span>', $html);
    }

    public function test_an_administrator_reaches_the_privileged_screens(): void
    {
        $admin = User::factory()->administrator()->create();

        foreach (['/en/admin/users', '/en/admin/audit', '/en/admin/pages', '/en/admin/menus'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    /**
     * An administrator who has not enrolled reaches the enrolment page and
     * nothing else. Enforced in two layers, so a route added later without
     * the middleware still cannot publish.
     */
    public function test_an_unenrolled_administrator_is_sent_to_enrolment(): void
    {
        $admin = User::factory()->administrator()->withoutTwoFactor()->create();

        $this->signIn($admin)->assertRedirect('/en/two-factor/enrol');
        $this->actingAs($admin)->get('/en/admin')->assertRedirect('/en/two-factor/enrol');
        $this->actingAs($admin)->get('/en/two-factor/enrol')->assertOk();
    }

    /* Editor ------------------------------------------------------------- */

    public function test_an_editor_lands_on_the_same_shell_without_the_privileged_screens(): void
    {
        $editor = User::factory()->editor()->create();

        $this->signIn($editor)->assertRedirect('/en/admin');

        $html = $this->actingAs($editor)->get('/en/admin')->assertOk()->getContent();

        // Present: the content work an editor signs in to do.
        $this->assertStringContainsString('<span>'.__('admin.nav_pages').'</span>', $html);
        $this->assertStringContainsString('<span>'.__('admin.nav_media').'</span>', $html);
        $this->assertStringContainsString(__('admin.dash_stat_publications'), $html);

        /*
         * Absent from the navigation. Asserted on the sidebar row's own
         * markup rather than on the word: the dashboard's tiles count
         * users, and an editor may see the figure without being able to
         * open the list.
         */
        $this->assertStringNotContainsString('<span>'.__('admin.nav_users').'</span>', $html);
        $this->assertStringNotContainsString('<span>'.__('admin.nav_audit').'</span>', $html);
        $this->assertStringNotContainsString('<span>'.__('admin.nav_settings').'</span>', $html);
    }

    /** And absent from the navigation is not the control. The routes are. */
    public function test_an_editor_is_refused_the_privileged_screens_directly(): void
    {
        $editor = User::factory()->editor()->create();

        foreach (['/en/admin/users', '/en/admin/audit'] as $url) {
            $this->actingAs($editor)->get($url)->assertForbidden();
        }
    }

    /* Member state representative ---------------------------------------- */

    public function test_a_member_state_representative_lands_in_the_extranet(): void
    {
        $rep = User::factory()->memberStateRep($this->botswana())->create();

        $this->signIn($rep)->assertRedirect('/en/extranet/dashboard');

        $html = $this->actingAs($rep)->get('/en/extranet/dashboard')->assertOk()->getContent();

        // Present: their own member state, their folders, their returns.
        // Present: their own member state named in the clearance note, the
        // documents rail, and the submission workspace they signed in for.
        $this->assertStringContainsString('Botswana', $html);
        $this->assertStringContainsString(__('extranet.nav_folders'), $html);
        $this->assertStringContainsString(__('extranet.nav_submissions'), $html);

        // Absent: nothing from the back office, and no other member state.
        $this->assertStringNotContainsString('<span>'.__('admin.nav_users').'</span>', $html);
        $this->assertStringNotContainsString('Mozambique', $html);
        $this->assertStringNotContainsString('<span>'.__('admin.nav_audit').'</span>', $html);
    }

    public function test_a_member_state_representative_is_kept_out_of_the_back_office(): void
    {
        $rep = User::factory()->memberStateRep($this->botswana())->create();

        foreach (['/en/admin', '/en/admin/pages', '/en/admin/users'] as $url) {
            $this->actingAs($rep)->get($url)->assertForbidden();
        }
    }

    /* Committee member --------------------------------------------------- */

    public function test_a_committee_member_lands_in_the_extranet_with_their_workspace(): void
    {
        $zambia = Country::where('iso_code', 'zm')->sole();
        $member = User::factory()->committeeMember($zambia)->create();
        $committee = Committee::where('slug', 'spectrum-and-frequency-management')->sole();

        $member->committees()->attach($committee->id, ['role' => CommitteeRole::Member->value, 'is_sample' => true]);

        $this->signIn($member)->assertRedirect('/en/extranet/dashboard');

        $html = $this->actingAs($member)->get('/en/extranet/dashboard')->assertOk()->getContent();

        // Present: their committee, the calendar, the documents rail.
        $this->assertStringContainsString($committee->name, $html);
        $this->assertStringContainsString(__('extranet.nav_calendar'), $html);
        $this->assertStringContainsString(__('extranet.nav_folders'), $html);

        // Absent: the member state submission workspace, which is not their
        // work and returns 403 to them.
        $this->assertStringNotContainsString(__('extranet.nav_submissions'), $html);
        $this->actingAs($member)->get('/en/submissions')->assertForbidden();
        $this->actingAs($member)->get('/en/admin')->assertForbidden();

        // The workspace itself opens.
        $this->actingAs($member)->get('/en/extranet/committees/'.$committee->slug)->assertOk();
    }

    /** A seat on one committee is not a seat on the others. */
    public function test_a_committee_member_reaches_only_their_own_workspace(): void
    {
        $zambia = Country::where('iso_code', 'zm')->sole();
        $member = User::factory()->committeeMember($zambia)->create();

        $mine = Committee::where('slug', 'spectrum-and-frequency-management')->sole();
        $theirs = Committee::where('slug', 'postal-and-logistics')->sole();

        $member->committees()->attach($mine->id, ['role' => CommitteeRole::Member->value, 'is_sample' => true]);

        $this->actingAs($member)->get('/en/extranet/committees/'.$mine->slug)->assertOk();
        $this->actingAs($member)->get('/en/extranet/committees/'.$theirs->slug)->assertForbidden();

        $html = $this->actingAs($member)->get('/en/extranet/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString($theirs->name, $html);
    }

    /* Viewer ------------------------------------------------------------- */

    public function test_a_viewer_lands_on_their_own_dashboard_signed_in(): void
    {
        $viewer = User::factory()->viewer()->create();

        // F4 (29 October 2026): a viewer lands on their own dashboard, the
        // latest published items and the comparator, not the home page.
        $this->signIn($viewer)->assertRedirect('/en/my-dashboard');

        $html = $this->actingAs($viewer)->get('/en')->assertOk()->getContent();

        // Present: they are signed in and the account is reachable.
        // Present: signed in, with a way to their own account and the
        // public library.
        $this->assertStringContainsString('/en/account', $html);
        $this->actingAs($viewer)->get('/en/account')->assertOk();
        $this->actingAs($viewer)->get('/en/publications')->assertOk();
    }

    /**
     * A viewer has no workspace, and the extranet says so rather than
     * throwing them at a sign-in page they are already past.
     */
    public function test_a_viewer_is_refused_the_workspaces(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin')->assertForbidden();
        $this->actingAs($viewer)->get('/en/extranet/folders')->assertForbidden();
        $this->actingAs($viewer)->get('/en/admin/users')->assertForbidden();
    }
}
