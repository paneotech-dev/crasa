<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Events\RegistrationConfirmed;
use App\Mail\DelegateRegistrationFiled;
use App\Mail\MeetingChangedForDelegate;
use App\Mail\RegistrationCopyForMailbox;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Day 3 item 1e: delegate registration, the client's additions.
 *
 * Preferred language and interpretation request on the form, a copy of
 * every filing to the CRASA mailbox set in Settings, a country filter,
 * bulk confirm and decline, a notice to every registered delegate when the
 * meeting changes, and the status on the public page. The last test walks
 * the whole chain: filed in public, listed, exported twice, confirmed.
 */
class M3DelegateRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Meeting $meeting;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        // Tests run in the console; the meeting-change mail is for edits made by a person.
        config(['crasa.meeting_change_mail_from_console' => true]);

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->admin = User::factory()->administrator()->create();
        $this->meeting = Meeting::create([
            'title' => 'CRASA Annual General Meeting',
            'slug' => 'agm-2026',
            'kind' => 'agm',
            'starts_on' => now()->addMonth()->toDateString(),
            'ends_on' => now()->addMonth()->addDays(2)->toDateString(),
            'location' => 'Lilongwe, Malawi',
            'clearance' => Clearance::Public,
            'is_sample' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function filing(array $extra = []): array
    {
        return array_merge([
            'delegate_name' => 'Thandiwe Moyo',
            'delegate_email' => 'thandiwe.moyo@example.test',
            'delegate_role' => 'Director, Spectrum',
            'preferred_language' => 'pt',
            'interpretation_into' => 'fr',
            'arrival_on' => now()->addMonth()->toDateString(),
            'departure_on' => now()->addMonth()->addDays(3)->toDateString(),
        ], $extra);
    }

    private function row(string $name, ?string $iso = null, string $status = 'filed'): DelegateRegistration
    {
        return DelegateRegistration::create([
            'meeting_id' => $this->meeting->id,
            'delegate_name' => $name,
            'delegate_email' => strtolower(str_replace(' ', '.', $name)).'@example.org',
            'country_id' => $iso ? Country::where('iso_code', $iso)->value('id') : null,
            'arrival_on' => $this->meeting->starts_on->toDateString(),
            'departure_on' => $this->meeting->starts_on->toDateString(),
            'status' => $status,
        ]);
    }

    /** The text of every cell in the first sheet of a workbook. */
    private function xlsxText(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        return $xml;
    }

    /* Language and interpretation ------------------------------------- */

    public function test_the_form_asks_for_language_and_interpretation_in_every_language(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            $this->get("/{$locale}/events/{$this->meeting->slug}/register")
                ->assertOk()
                ->assertSee('name="preferred_language"', false)
                ->assertSee('name="interpretation_into"', false)
                ->assertSee(__('m3_deleg.interp_none', [], $locale));
        }
    }

    public function test_language_and_interpretation_are_stored_and_checked(): void
    {
        $this->post("/en/events/{$this->meeting->slug}/register", $this->filing())->assertRedirect();

        $row = DelegateRegistration::withoutGlobalScopes()->sole();
        $this->assertSame('pt', $row->preferred_language);
        $this->assertSame('fr', $row->interpretation_into);

        $this->post("/en/events/{$this->meeting->slug}/register", $this->filing([
            'delegate_email' => 'other@example.test', 'preferred_language' => 'de', 'interpretation_into' => 'xx',
        ]))->assertSessionHasErrors(['preferred_language', 'interpretation_into']);

        // None is an empty value, and is allowed.
        $this->post("/en/events/{$this->meeting->slug}/register", $this->filing([
            'delegate_email' => 'third@example.test', 'interpretation_into' => '',
        ]))->assertSessionHasNoErrors();
        $this->assertNull(DelegateRegistration::withoutGlobalScopes()->where('delegate_email', 'third@example.test')->value('interpretation_into'));
    }

    /* The CRASA mailbox ------------------------------------------------ */

    public function test_every_filing_queues_a_copy_to_the_mailbox_set_in_settings(): void
    {
        $this->actingAs($this->admin)
            ->put('/en/admin/settings', ['registrations_mailbox' => 'registrations@crasa.org'])
            ->assertSessionHasNoErrors();
        $this->assertSame('registrations@crasa.org', SiteSetting::get('registrations_mailbox'));
        auth()->logout();

        $this->post("/en/events/{$this->meeting->slug}/register", $this->filing())->assertRedirect();

        Mail::assertQueued(DelegateRegistrationFiled::class, fn ($m) => $m->hasTo('thandiwe.moyo@example.test'));
        Mail::assertQueued(RegistrationCopyForMailbox::class, function ($m) {
            $html = $m->render();

            return $m->hasTo('registrations@crasa.org')
                && str_contains($html, 'Thandiwe Moyo')
                && str_contains($html, __('m3_deleg.interp_into_fr'));
        });
    }

    public function test_with_no_mailbox_set_the_copy_goes_to_the_contact_address(): void
    {
        SiteSetting::updateOrCreate(['key' => 'contact_email'], ['value' => 'crasa@crasa.org', 'type' => 'email', 'group' => 'contact']);

        $this->post("/en/events/{$this->meeting->slug}/register", $this->filing())->assertRedirect();

        Mail::assertQueued(RegistrationCopyForMailbox::class, fn ($m) => $m->hasTo('crasa@crasa.org'));
    }

    public function test_the_mailbox_setting_is_on_the_settings_screen_and_validated(): void
    {
        $this->actingAs($this->admin)->get('/en/admin/settings')
            ->assertOk()
            ->assertSee('name="registrations_mailbox"', false)
            ->assertSee(__('m3_deleg.setting_registrations_mailbox'));

        $this->actingAs($this->admin)->put('/en/admin/settings', ['registrations_mailbox' => 'not an address'])
            ->assertSessionHasErrors('registrations_mailbox');
    }

    /* Country filter --------------------------------------------------- */

    public function test_the_list_and_exports_filter_by_country(): void
    {
        $this->row('Chipo Banda', 'mw');
        $this->row('Lerato Dube', 'za');

        $this->actingAs($this->admin)->get('/en/admin/registrations?country=mw')
            ->assertOk()->assertSee('Chipo Banda')->assertDontSee('Lerato Dube');

        $csv = $this->actingAs($this->admin)->get('/en/admin/registrations/export.csv?country=mw')->assertOk()->streamedContent();
        $this->assertStringContainsString('Chipo Banda', $csv);
        $this->assertStringNotContainsString('Lerato Dube', $csv);

        $xlsx = $this->xlsxText($this->actingAs($this->admin)->get('/en/admin/registrations/export.xlsx?country=za')->assertOk()->getContent());
        $this->assertStringContainsString('Lerato Dube', $xlsx);
        $this->assertStringNotContainsString('Chipo Banda', $xlsx);

        // An unknown code filters nothing out and breaks nothing.
        $this->actingAs($this->admin)->get('/en/admin/registrations?country=zz')->assertOk()->assertSee('Chipo Banda');
    }

    /* Bulk actions ----------------------------------------------------- */

    public function test_confirm_selected_confirms_each_audits_and_fires_the_event_per_row(): void
    {
        Event::fake([RegistrationConfirmed::class]);
        $a = $this->row('Chipo Banda', 'mw');
        $b = $this->row('Lerato Dube', 'za');
        $c = $this->row('Already Confirmed', 'za', 'confirmed');
        $untouched = $this->row('Not Selected', 'za');

        $this->actingAs($this->admin)
            ->post('/en/admin/registrations/bulk', ['action' => 'confirmed', 'ids' => [$a->id, $b->id, $c->id]])
            ->assertRedirect()
            ->assertSessionHas('status', trans_choice('m3_deleg.bulk_done_confirmed', 2, ['count' => 2]));

        $this->assertSame('confirmed', $a->fresh()->status);
        $this->assertSame('confirmed', $b->fresh()->status);
        $this->assertSame('filed', $untouched->fresh()->status);

        // Once per newly confirmed row, never for one already confirmed.
        Event::assertDispatchedTimes(RegistrationConfirmed::class, 2);

        $batch = AuditLog::where('action', 'registrations_bulk_confirmed')->sole();
        $this->assertSame($this->admin->id, $batch->user_id);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $batch->changes['ids']);
    }

    public function test_decline_selected_declines_and_nothing_selected_is_an_error(): void
    {
        Event::fake([RegistrationConfirmed::class]);
        $a = $this->row('Chipo Banda', 'mw');

        $this->actingAs($this->admin)->post('/en/admin/registrations/bulk', ['action' => 'declined', 'ids' => [$a->id]])
            ->assertRedirect();
        $this->assertSame('declined', $a->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'registrations_bulk_declined')->exists());
        Event::assertNotDispatched(RegistrationConfirmed::class);

        $this->actingAs($this->admin)->post('/en/admin/registrations/bulk', ['action' => 'declined'])
            ->assertSessionHasErrors('ids');
        $this->actingAs($this->admin)->post('/en/admin/registrations/bulk', ['action' => 'delete', 'ids' => [$a->id]])
            ->assertSessionHasErrors('action');
    }

    public function test_the_list_shows_select_all_and_the_bulk_buttons(): void
    {
        $this->row('Chipo Banda', 'mw');

        $this->actingAs($this->admin)->get('/en/admin/registrations')
            ->assertOk()
            ->assertSee('data-reg-select-all', false)
            ->assertSee('form="reg-bulk"', false)
            ->assertSee(__('m3_deleg.confirm_selected'))
            ->assertSee(__('m3_deleg.decline_selected'));
    }

    public function test_a_role_without_meeting_rights_cannot_use_bulk_actions(): void
    {
        $a = $this->row('Chipo Banda', 'mw');

        $this->actingAs(User::factory()->viewer()->create())
            ->post('/en/admin/registrations/bulk', ['action' => 'confirmed', 'ids' => [$a->id]])
            ->assertForbidden();
        $this->assertSame('filed', $a->fresh()->status);

        // Signed out: sent to sign in.
        $this->app['auth']->forgetGuards();
        $this->post('/en/admin/registrations/bulk', ['action' => 'confirmed', 'ids' => [$a->id]])
            ->assertRedirect();
        $this->assertSame('filed', $a->fresh()->status);
    }

    /* Meeting changes -------------------------------------------------- */

    public function test_cancelling_queues_a_notice_to_every_registered_delegate(): void
    {
        $this->row('Chipo Banda', 'mw');
        $this->row('Lerato Dube', 'za', 'confirmed');
        $this->row('Declined Person', 'za', 'declined');

        $this->meeting->update(['status' => 'cancelled']);

        Mail::assertQueued(MeetingChangedForDelegate::class, 2);
        Mail::assertQueued(MeetingChangedForDelegate::class, fn ($m) => $m->change === 'cancelled' && $m->hasTo('chipo.banda@example.org'));
        Mail::assertNotQueued(MeetingChangedForDelegate::class, fn ($m) => $m->hasTo('declined.person@example.org'));

        $this->get("/en/events/{$this->meeting->slug}")->assertOk()->assertSee(__('e.cancelled_notice'));
    }

    public function test_postponing_queues_a_notice_and_the_page_says_postponed(): void
    {
        $this->row('Chipo Banda', 'mw');

        $this->meeting->update(['status' => 'postponed']);

        Mail::assertQueued(MeetingChangedForDelegate::class, fn ($m) => $m->change === 'postponed');
        $this->get("/en/events/{$this->meeting->slug}")->assertOk()->assertSee(__('e.postponed_notice'));
    }

    public function test_a_new_date_queues_a_notice_and_the_page_says_postponed_new_date(): void
    {
        $registration = $this->row('Chipo Banda', 'mw');
        $registration->update(['preferred_language' => 'fr']);
        $was = $this->meeting->starts_on->toDateString();

        $this->meeting->update([
            'starts_on' => now()->addMonths(2)->toDateString(),
            'ends_on' => now()->addMonths(2)->addDay()->toDateString(),
        ]);

        $fresh = $this->meeting->fresh();
        $this->assertSame($was, $fresh->rescheduled_from->toDateString());
        $this->assertSame('postponed', $fresh->displayStatus());

        Mail::assertQueued(MeetingChangedForDelegate::class, fn ($m) => $m->change === 'new_date' && $m->locale === 'fr');

        foreach (['en', 'fr', 'pt'] as $locale) {
            app()->setLocale($locale);
            $label = __('m3_deleg.postponed_new_date', ['date' => $fresh->dateRange()], $locale);
            $this->get("/{$locale}/events/{$this->meeting->slug}")->assertOk()->assertSee($label);
        }
    }

    public function test_a_legacy_sync_a_console_save_and_a_sample_registration_send_nothing(): void
    {
        $this->row('Chipo Banda', 'mw');
        $sample = $this->row('Sample Delegate', 'za');
        $sample->update(['is_sample' => true]);

        // The legacy sync sets synced_at on every run.
        $this->meeting->update(['synced_at' => now()]);
        $this->meeting->update(['status' => 'postponed', 'synced_at' => now()->addMinute()]);
        Mail::assertNotQueued(MeetingChangedForDelegate::class);

        // Any other console save, without the explicit setting.
        config(['crasa.meeting_change_mail_from_console' => false]);
        $this->meeting->update(['starts_on' => now()->addMonths(3)->toDateString()]);
        Mail::assertNotQueued(MeetingChangedForDelegate::class);

        // With the setting (as for an edit made by a person): the real delegate only.
        config(['crasa.meeting_change_mail_from_console' => true]);
        $this->meeting->update(['status' => 'cancelled']);
        Mail::assertQueued(MeetingChangedForDelegate::class, 1);
        Mail::assertQueued(MeetingChangedForDelegate::class, fn ($m) => $m->hasTo('chipo.banda@example.org'));
        Mail::assertNotQueued(MeetingChangedForDelegate::class, fn ($m) => $m->hasTo('sample.delegate@example.org'));
    }

    public function test_an_edit_that_does_not_move_the_meeting_sends_nothing(): void
    {
        $this->row('Chipo Banda', 'mw');

        $this->meeting->update(['summary' => 'Agenda published.']);

        Mail::assertNotQueued(MeetingChangedForDelegate::class);
        $this->assertSame('scheduled', $this->meeting->fresh()->displayStatus());
    }

    public function test_registered_delegates_with_accounts_get_the_in_app_notice(): void
    {
        $user = User::factory()->viewer()->create(['email' => 'chipo.banda@example.org']);
        $this->row('Chipo Banda', 'mw')->update(['user_id' => $user->id]);

        $this->meeting->update(['status' => 'cancelled']);

        $this->assertTrue($user->notifications()->exists());
    }

    /* The full chain --------------------------------------------------- */

    public function test_a_public_registration_is_recorded_listed_exported_and_confirmed(): void
    {
        Event::fake([RegistrationConfirmed::class]);
        $malawi = Country::where('iso_code', 'mw')->sole();

        // Filed in public, signed out.
        $this->post("/en/events/{$this->meeting->slug}/register", $this->filing(['country_id' => $malawi->id]))
            ->assertRedirect();
        $this->post("/en/events/{$this->meeting->slug}/register", $this->filing([
            'delegate_name' => 'Kagiso Sithole', 'delegate_email' => 'kagiso@example.test', 'country_id' => $malawi->id,
        ]))->assertRedirect();

        // Recorded.
        $first = DelegateRegistration::withoutGlobalScopes()->where('delegate_email', 'thandiwe.moyo@example.test')->sole();
        $second = DelegateRegistration::withoutGlobalScopes()->where('delegate_email', 'kagiso@example.test')->sole();
        $this->assertSame('filed', $first->status);
        Mail::assertQueued(RegistrationCopyForMailbox::class, 2);

        // Listed, with the language.
        $this->actingAs($this->admin)->get('/en/admin/registrations?country=mw')
            ->assertOk()->assertSee('Thandiwe Moyo')->assertSee('Kagiso Sithole')->assertSee(__('m3_deleg.lang_pt'));

        // In the CSV and in the workbook.
        $csv = $this->actingAs($this->admin)->get('/en/admin/registrations/export.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Thandiwe Moyo', $csv);
        $this->assertStringContainsString(__('m3_deleg.interp_into_fr'), $csv);
        $xlsx = $this->xlsxText($this->actingAs($this->admin)->get('/en/admin/registrations/export.xlsx')->assertOk()->getContent());
        $this->assertStringContainsString('Thandiwe Moyo', $xlsx);
        $this->assertStringContainsString('Kagiso Sithole', $xlsx);

        // Confirmed singly.
        $this->actingAs($this->admin)->put("/en/admin/registrations/{$first->id}/status", ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame('confirmed', $first->fresh()->status);

        // And in bulk.
        $this->actingAs($this->admin)->post('/en/admin/registrations/bulk', ['action' => 'confirmed', 'ids' => [$first->id, $second->id]])
            ->assertRedirect();
        $this->assertSame('confirmed', $second->fresh()->status);
        Event::assertDispatchedTimes(RegistrationConfirmed::class, 2);

        $this->actingAs($this->admin)->get('/en/admin/registrations?status=confirmed')
            ->assertOk()->assertSee('Thandiwe Moyo')->assertSee('Kagiso Sithole');
    }
}
