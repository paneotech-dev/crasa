<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\Country;
use App\Models\User;
use App\Support\RoleDashboard;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A dashboard per role (F4): each role opens its own blocks first, and an
 * empty block says why it is empty and what to do.
 */
class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
    }

    public function test_the_administrator_dashboard_opens_with_needs_your_attention_counts_and_shortcuts(): void
    {
        $admin = User::factory()->administrator()->create();

        $html = $this->actingAs($admin)->get('/en/admin')->assertOk()->getContent();

        $this->assertStringContainsString('data-role="administrator"', $html);
        $this->assertStringContainsString('data-block="attention"', $html);
        $this->assertStringContainsString('class="rd__spark"', $html);
        $this->assertStringContainsString(__('fb.dash_new_news'), $html);
        // Mail status, administrators only.
        $this->assertMatchesRegularExpression('/Mail is (not )?sent/', $html);
        $this->assertLessThan(strpos($html, 'a-tiles'), strpos($html, 'data-block="attention"'));
    }

    public function test_the_editor_sees_drafts_live_items_and_the_two_new_buttons_but_no_mail_status(): void
    {
        $editor = User::factory()->editor()->create();

        $html = $this->actingAs($editor)->get('/en/admin')->assertOk()->getContent();

        $this->assertStringContainsString('data-role="editor"', $html);
        $this->assertStringContainsString('data-block="drafts"', $html);
        $this->assertStringContainsString('data-block="live"', $html);
        $this->assertStringContainsString(__('fb.dash_new_news'), $html);
        $this->assertStringContainsString(__('fb.dash_new_publication'), $html);
        $this->assertStringNotContainsString('data-block="attention"', $html);
        $this->assertStringNotContainsString('Mail is', $html);
    }

    public function test_a_member_state_representative_sees_the_country_return_with_its_next_action(): void
    {
        $country = Country::where('iso_code', 'ZA')->first() ?? Country::first();
        $rep = User::factory()->memberStateRep($country)->create();

        $html = $this->actingAs($rep)->get('/en/extranet/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-role="member_state_rep"', $html);
        $this->assertStringContainsString('data-block="return"', $html);
        $this->assertStringContainsString(e(__('fb.dash_next_none')), $html);
        $this->assertStringContainsString('data-block="indicators"', $html);
        $this->assertStringContainsString('data-block="upcoming"', $html);
    }

    public function test_a_committee_member_sees_their_committees_and_next_meeting(): void
    {
        $member = User::factory()->committeeMember(Country::first())->create();
        $committee = Committee::first();
        $member->committees()->attach($committee->id, ['role' => 'member']);

        $html = $this->actingAs($member)->get('/en/extranet/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-block="committees"', $html);
        $this->assertStringContainsString(e($committee->name), $html);
        $this->assertStringContainsString('data-block="next"', $html);
    }

    public function test_a_viewer_has_their_own_dashboard_and_lands_on_it(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->assertStringEndsWith('/en/my-dashboard', \App\Http\Controllers\Auth\LoginController::destinationFor($viewer));

        $this->actingAs($viewer)->get('/en/my-dashboard')->assertOk()
            ->assertSee(__('fb.dash_heading_viewer'))
            ->assertSee(__('fb.dash_open_comparator'));

        $this->actingAs($viewer)->get('/en/admin')->assertForbidden();
    }

    public function test_readers_with_a_workspace_are_sent_to_it_from_my_dashboard(): void
    {
        $this->actingAs(User::factory()->editor()->create())->get('/en/my-dashboard')->assertRedirect('/en/admin');
        $this->actingAs(User::factory()->memberStateRep(Country::first())->create())->get('/en/my-dashboard')->assertRedirect('/en/extranet/dashboard');
        $this->get('/en/my-dashboard')->assertRedirect();
    }

    public function test_every_empty_block_says_why_and_what_to_do(): void
    {
        \Illuminate\Support\Facades\URL::defaults(['locale' => 'en']);

        foreach (['administrator', 'editor', 'viewer'] as $role) {
            $user = User::factory()->{$role}()->create();
            foreach (RoleDashboard::for($user)['blocks'] as $block) {
                $this->assertNotSame('', trim((string) $block['empty']), $role.' '.$block['key']);
                $this->assertGreaterThan(40, mb_strlen($block['empty']), $role.' '.$block['key']);
            }
        }
    }
}
