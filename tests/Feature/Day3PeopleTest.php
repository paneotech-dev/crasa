<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Events\RegistrationConfirmed;
use App\Events\RegistrationReceived;
use App\Models\AuditLog;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Day 3, people: the users screen (delete after suspend made visible, "You",
 * the two-factor banner, edit, the account page) and delegate registrations
 * (the list across events, confirm, resend, exports, the event page block,
 * the member area's ways in, the confirmation screen, computed status).
 */
class Day3PeopleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $admin2;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        Notification::fake();
        Mail::fake();

        $this->admin = User::factory()->administrator()->create(['name' => 'Admin One']);
        $this->admin2 = User::factory()->administrator()->create(['name' => 'Admin Two']);
    }

    /* Users ---------------------------------------------------------------- */

    public function test_an_active_account_shows_a_disabled_delete_with_its_reason_and_your_own_row_says_you(): void
    {
        $editor = User::factory()->editor()->create(['name' => 'Active Editor']);

        $html = $this->actingAs($this->admin)->get('/en/admin/users')->assertOk()->getContent();

        $this->assertStringContainsString('aria-disabled="true" aria-describedby="del-why-'.$editor->id.'"', $html);
        $this->assertStringContainsString('id="del-why-'.$editor->id.'">Suspend first<', $html);
        $this->assertStringContainsString('<span class="d3p-you">You</span>', $html);

        $this->actingAs($this->admin)->get('/fr/admin/users')->assertOk()->assertSee('Suspendre d&#039;abord', false);
        $this->actingAs($this->admin)->get('/pt/admin/users')->assertOk()->assertSee('Suspender primeiro');
    }

    public function test_the_unenrolled_banner_follows_the_two_factor_flag(): void
    {
        // Two administrators who have not enrolled.
        User::factory()->administrator()->count(2)->create(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
        $title = trans_choice('admin.unenrolled_title', 2, ['count' => 2]);

        $this->actingAs($this->admin)->get('/en/admin/users')->assertOk()->assertDontSee($title);

        config(['crasa.two_factor_required' => true]);

        $this->actingAs($this->admin)->get('/en/admin/users')->assertOk()->assertSee($title);
    }

    public function test_an_administrator_edits_an_account_and_it_is_audited(): void
    {
        $country = Country::where('iso_code', 'ZM')->first() ?? Country::first();
        $authority = Authority::factory()->for($country)->create();
        $committee = Committee::factory()->create();
        $rep = User::factory()->create(['name' => 'Old Name', 'role' => Role::Viewer]);

        $this->actingAs($this->admin)->get("/en/admin/users/{$rep->id}/edit")->assertOk();

        $this->actingAs($this->admin)->put("/en/admin/users/{$rep->id}", [
            'name' => 'New Name',
            'email' => 'new.name@example.org',
            'country_id' => $country->id,
            'authority_id' => $authority->id,
            'role' => 'editor',
            'committees' => [$committee->id => 'chair'],
        ])->assertRedirect("/en/admin/users/{$rep->id}");

        $rep->refresh();
        $this->assertSame('New Name', $rep->name);
        $this->assertSame(Role::Editor, $rep->role);
        $this->assertSame($authority->id, $rep->authority_id);
        $this->assertSame('chair', $rep->committees()->first()->pivot->role);

        $about = AuditLog::where('auditable_type', $rep->getMorphClass())->where('auditable_id', $rep->id);
        $this->assertTrue((clone $about)->where('action', AuditLog::ROLE_CHANGED)->exists());
        $this->assertTrue((clone $about)->where('action', AuditLog::UPDATED)->where('user_id', $this->admin->id)->count() >= 2);
    }

    public function test_an_authority_from_another_member_state_is_refused_with_a_message(): void
    {
        [$a, $b] = Country::query()->take(2)->get()->all();
        $authority = Authority::factory()->for($a)->create();
        $user = User::factory()->create();

        $this->actingAs($this->admin)->from("/en/admin/users/{$user->id}/edit")->put("/en/admin/users/{$user->id}", [
            'name' => $user->name, 'email' => $user->email, 'country_id' => $b->id, 'authority_id' => $authority->id,
        ])->assertSessionHasErrors('authority_id');
    }

    public function test_the_edit_form_keeps_the_two_administrator_rule_and_your_own_role(): void
    {
        $this->actingAs($this->admin)->put("/en/admin/users/{$this->admin2->id}", [
            'name' => $this->admin2->name, 'email' => $this->admin2->email, 'role' => 'editor',
        ])->assertSessionHasErrors('role');
        $this->assertSame(Role::Administrator, $this->admin2->fresh()->role);

        // One's own role is not changed here either.
        $this->actingAs($this->admin)->put("/en/admin/users/{$this->admin->id}", [
            'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'viewer',
        ])->assertForbidden();
    }

    public function test_the_account_page_lists_entries_about_and_by_the_account_newest_first(): void
    {
        $user = User::factory()->editor()->create();
        AuditLog::record(AuditLog::SUSPENDED, $user, null, $this->admin);
        AuditLog::record(AuditLog::LOGIN, null, null, $user);

        $this->actingAs($this->admin)->get("/en/admin/users/{$user->id}")->assertOk()
            ->assertSee(__('d3_people.about_this_account'))
            ->assertSee(__('d3_people.by_this_account'))
            ->assertSee(__('audit.action_suspended'))
            ->assertSee(__('audit.action_login'));

        $this->actingAs($this->admin)->get("/en/admin/users/{$user->id}?show=by")->assertOk()
            ->assertDontSee(__('audit.action_suspended'));
    }

    public function test_user_pages_are_refused_to_other_roles(): void
    {
        $editor = User::factory()->editor()->create();
        $target = User::factory()->create();

        $this->actingAs($editor)->get("/en/admin/users/{$target->id}")->assertForbidden();
        $this->actingAs($editor)->get("/en/admin/users/{$target->id}/edit")->assertForbidden();
        $this->actingAs($editor)->put("/en/admin/users/{$target->id}", ['name' => 'x', 'email' => 'x@example.org'])->assertForbidden();
        $this->actingAs($this->admin)->get('/en/admin/users/999999')->assertNotFound();
    }

    /* Delegate registrations ------------------------------------------------ */

    private function meeting(array $attributes = []): Meeting
    {
        return Meeting::factory()->upcoming()->create($attributes + ['clearance' => 'public']);
    }

    private function registration(Meeting $meeting, array $attributes = []): DelegateRegistration
    {
        return DelegateRegistration::create($attributes + [
            'meeting_id' => $meeting->id,
            'delegate_name' => 'Thandiwe Phiri',
            'delegate_email' => 'thandiwe@example.org',
            'arrival_on' => $meeting->starts_on->copy()->subDay()->toDateString(),
            'departure_on' => $meeting->starts_on->toDateString(),
            'status' => 'filed',
        ]);
    }

    public function test_the_list_groups_registrations_by_event_and_links_the_public_form(): void
    {
        $m = $this->meeting(['title' => 'Spectrum committee']);
        $this->registration($m);

        $this->actingAs($this->admin)->get('/en/admin/registrations')->assertOk()
            ->assertSee('Spectrum committee')
            ->assertSee('Thandiwe Phiri')
            ->assertSee(route('events.register', ['locale' => 'en', 'meeting' => $m->slug]), false)
            ->assertSee(__('d3_people.mail_off_note'));

        // The events list's count goes there.
        $this->actingAs($this->admin)->get('/en/admin/meetings')->assertOk()
            ->assertSee(route('admin.registrations', ['locale' => 'en', 'meeting' => $m->slug]), false);
    }

    public function test_confirming_dispatches_the_contract_event_once(): void
    {
        Event::fake([RegistrationConfirmed::class]);
        $row = $this->registration($this->meeting());

        $this->actingAs($this->admin)->put("/en/admin/registrations/{$row->id}/status", ['status' => 'confirmed'])->assertRedirect();
        $this->actingAs($this->admin)->put("/en/admin/registrations/{$row->id}/status", ['status' => 'confirmed'])->assertRedirect();

        $this->assertSame('confirmed', $row->fresh()->status);
        Event::assertDispatchedTimes(RegistrationConfirmed::class, 1);
        Event::assertDispatched(RegistrationConfirmed::class, fn ($e) => $e->registration->is($row));
    }

    public function test_resend_queues_the_copy_and_says_mail_is_off(): void
    {
        config(['mail.default' => 'log']);
        $row = $this->registration($this->meeting());

        $this->actingAs($this->admin)->post("/en/admin/registrations/{$row->id}/resend")
            ->assertSessionHas('status', __('d3_people.resent_mail_off', ['name' => 'Thandiwe Phiri']));

        Mail::assertQueued(\App\Mail\DelegateRegistrationFiled::class);
        $this->assertTrue(AuditLog::where('action', 'registration_resent')->exists());
    }

    public function test_exports_as_csv_and_excel(): void
    {
        $this->registration($this->meeting());

        $csv = $this->actingAs($this->admin)->get('/en/admin/registrations/export.csv')->assertOk();
        $this->assertStringContainsString('Thandiwe Phiri', $csv->streamedContent());

        $xlsx = $this->actingAs($this->admin)->get('/en/admin/registrations/export.xlsx')->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->getContent());
    }

    public function test_registration_screens_are_refused_to_roles_without_meetings(): void
    {
        $viewer = User::factory()->viewer()->create();
        $row = $this->registration($this->meeting());

        $this->actingAs($viewer)->get('/en/admin/registrations')->assertForbidden();
        $this->actingAs($viewer)->get('/en/admin/registrations/export.csv')->assertForbidden();
        $this->actingAs($viewer)->get('/en/admin/registrations/export.xlsx')->assertForbidden();
        $this->actingAs($viewer)->put("/en/admin/registrations/{$row->id}/status", ['status' => 'confirmed'])->assertForbidden();
        $this->actingAs($viewer)->post("/en/admin/registrations/{$row->id}/resend")->assertForbidden();
    }

    public function test_the_sidebar_moves_member_state_records_under_regulatory(): void
    {
        $html = $this->actingAs($this->admin)->get('/en/admin/registrations')->getContent();
        $regulatory = strpos($html, __('admin.nav_group_regulatory') ?: 'REGULATORY');
        $records = strpos($html, 'Member state records');
        $people = strpos($html, route('admin.users', ['locale' => 'en']));

        $this->assertNotFalse($records);
        $this->assertLessThan($people, $records);
        $this->assertStringNotContainsString('>Confirmations<', $html);
    }

    /* Public side ----------------------------------------------------------- */

    public function test_the_event_page_says_who_files_and_what_happens_next(): void
    {
        $m = $this->meeting();

        $this->get('/en/events/'.$m->slug)->assertOk()
            ->assertSee(__('d3_people.reg_who'))
            ->assertSee(__('d3_people.reg_next_copy'))
            ->assertSee(__('e.file_arrival'));
    }

    public function test_a_members_only_meeting_asks_a_signed_out_reader_to_sign_in(): void
    {
        $m = $this->meeting(['clearance' => 'member']);

        $this->get('/en/events/'.$m->slug.'/register')->assertRedirect('/en/login');
    }

    public function test_filing_dispatches_received_and_shows_the_confirmation_screen(): void
    {
        Event::fake([RegistrationReceived::class]);
        $m = $this->meeting();

        $this->post('/en/events/'.$m->slug.'/register', [
            'delegate_name' => 'Kagiso Molefe',
            'delegate_email' => 'kagiso@example.org',
            'arrival_on' => $m->starts_on->copy()->subDay()->toDateString(),
            'departure_on' => $m->starts_on->toDateString(),
        ])->assertRedirect('/en/events/'.$m->slug.'/register/done');

        Event::assertDispatched(RegistrationReceived::class);

        $this->get('/en/events/'.$m->slug.'/register/done')->assertOk()
            ->assertSee('Kagiso Molefe')
            ->assertSee(__('d3_people.done_edit'));
    }

    public function test_a_confirmed_filing_is_not_overwritten_from_the_form(): void
    {
        $m = $this->meeting();
        $this->registration($m, ['status' => 'confirmed']);

        $this->post('/en/events/'.$m->slug.'/register', [
            'delegate_name' => 'Someone Else',
            'delegate_email' => 'thandiwe@example.org',
            'arrival_on' => $m->starts_on->copy()->subDay()->toDateString(),
            'departure_on' => $m->starts_on->toDateString(),
        ])->assertSessionHasErrors('delegate_email');

        $this->assertSame('Thandiwe Phiri', DelegateRegistration::first()->delegate_name);
    }

    public function test_a_past_meeting_shows_held_and_a_cancelled_one_cancelled(): void
    {
        $past = Meeting::factory()->create(['clearance' => 'public', 'starts_on' => now()->subDays(20), 'status' => 'scheduled']);
        $cancelled = $this->meeting(['status' => 'cancelled']);

        $this->assertSame('held', $past->displayStatus());
        $this->assertSame('cancelled', $cancelled->displayStatus());
        $this->get('/en/events/'.$past->slug)->assertOk()->assertSee('Held');
        $this->get('/pt/events/'.$past->slug)->assertOk()->assertSee('Realizada');
        $this->actingAs($this->admin)->get('/en/admin/meetings')->assertOk()
            ->assertSee('data-status="archived">Held<', false);
    }

    /* Member area ----------------------------------------------------------- */

    public function test_the_member_area_offers_the_form_on_the_calendar_and_the_dashboard(): void
    {
        $country = Country::first();
        $member = User::factory()->create(['role' => Role::MemberStateRep, 'country_id' => $country->id]);
        $m = $this->meeting(['clearance' => 'member', 'title' => 'Lilongwe workshop', 'committee_id' => null]);

        $register = route('events.register', ['locale' => 'en', 'meeting' => $m->slug]);

        $this->actingAs($member)->get('/en/extranet/calendar')->assertOk()
            ->assertSee(__('d3_people.register_attendance'))->assertSee($register, false);
        $this->actingAs($member)->get('/en/extranet/dashboard')->assertOk()
            ->assertSee(__('d3_people.next_title'))->assertSee($register, false);
        $this->actingAs($member)->get($register)->assertOk();
    }

    public function test_an_explicit_closing_date_keeps_a_running_meeting_open(): void
    {
        $running = Meeting::factory()->create([
            'clearance' => 'public',
            'starts_on' => now()->subDay(), 'ends_on' => now()->addDay(),
            'registration_closes_on' => now()->addDay(),
        ]);
        $this->assertTrue($running->registrationIsOpen());

        $running->update(['registration_closes_on' => null]);
        $this->assertFalse($running->fresh()->registrationIsOpen());
    }
}
