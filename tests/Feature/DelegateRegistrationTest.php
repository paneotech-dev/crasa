<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Mail\DelegateRegistrationFiled;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Delegate registration, WS-07, screen 18.
 *
 * The roster and the CSV export were built and had nothing to list, because
 * the form a delegate fills was never finished. This is that form, and the
 * boundary it sits on.
 *
 * HALF THIS FILE IS ABOUT WHAT DOES NOT EXIST. The website collects, the
 * Event Management Platform manages, and the division is a separate
 * commercial agreement rather than a design preference. Screen 18 draws
 * accommodation, nights required, dietary requirements and a transfer
 * schedule; none of them is built, and the test walks the column names, the
 * form fields and the routes so that adding one is a failing test rather
 * than a quiet commit.
 */
class DelegateRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

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
            'arrival_point' => 'Kamuzu International, Lilongwe',
            'arrival_on' => now()->addMonth()->toDateString(),
            'arrival_at' => '14:20',
            'arrival_reference' => 'KQ 794',
            'departure_on' => now()->addMonth()->addDays(3)->toDateString(),
            'departure_at' => '19:40',
            'departure_reference' => 'KQ 795',
            'notes' => 'Arriving with two colleagues on the same flight.',
        ], $extra);
    }

    private function register(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post("/en/events/{$this->meeting->slug}/register", $this->filing($extra));
    }

    /* The form ----------------------------------------------------------- */

    public function test_the_form_is_public_and_renders_in_every_language(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            $this->get("/{$locale}/events/{$this->meeting->slug}/register")
                ->assertOk()
                ->assertSee(__('delegates.col_arrival_on', [], $locale));
        }
    }

    public function test_a_delegate_with_no_account_files_and_lands_on_the_roster(): void
    {
        $malawi = Country::where('iso_code', 'mw')->sole();

        $this->register(['country_id' => $malawi->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $registration = DelegateRegistration::withoutGlobalScopes()->sole();

        $this->assertSame('Thandiwe Moyo', $registration->delegate_name);
        $this->assertSame($malawi->id, $registration->country_id);
        $this->assertSame('KQ 794', $registration->arrival_reference);
        $this->assertNull($registration->user_id);
        $this->assertSame('filed', $registration->status);

        // The delegate is told it worked.
        $this->followingRedirects()->register(['country_id' => $malawi->id])
            ->assertSee(__('delegates.filed', ['name' => 'Thandiwe Moyo']), false);

        // The Secretariat's roster now has a row, and so does the export.
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get("/en/admin/meetings/{$this->meeting->slug}/delegates")
            ->assertOk()
            ->assertSee('Thandiwe Moyo');

        $csv = $this->actingAs($admin)
            ->get("/en/admin/meetings/{$this->meeting->slug}/delegates.csv")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Thandiwe Moyo', $csv);
        $this->assertStringContainsString('KQ 794', $csv);
    }

    /** A signed-in delegate's member state comes from their account. */
    public function test_a_signed_in_delegate_keeps_their_own_member_state(): void
    {
        $botswana = Country::where('iso_code', 'bw')->sole();
        $mozambique = Country::where('iso_code', 'mz')->sole();
        $rep = User::factory()->memberStateRep($botswana)->create();

        // Even if the form claims another one.
        $this->actingAs($rep)->post(
            "/en/events/{$this->meeting->slug}/register",
            $this->filing(['country_id' => $mozambique->id])
        )->assertRedirect()->assertSessionHasNoErrors();

        $registration = DelegateRegistration::withoutGlobalScopes()->sole();

        $this->assertSame($botswana->id, $registration->country_id);
        $this->assertSame($rep->id, $registration->user_id);
    }

    /** Filing again amends. The unique key is the meeting and the email. */
    public function test_filing_again_amends_rather_than_duplicating(): void
    {
        $this->register()->assertRedirect();
        $this->register(['arrival_reference' => 'SA 172', 'arrival_at' => '09:05'])->assertRedirect();

        $this->assertSame(1, DelegateRegistration::withoutGlobalScopes()->count());
        $this->assertSame('SA 172', DelegateRegistration::withoutGlobalScopes()->sole()->arrival_reference);
    }

    public function test_the_confirmation_is_queued_to_the_delegate(): void
    {
        $this->register()->assertRedirect();

        Mail::assertQueued(
            DelegateRegistrationFiled::class,
            fn (DelegateRegistrationFiled $mail) => $mail->hasTo('thandiwe.moyo@example.test')
        );
    }

    public function test_filing_writes_an_audit_row(): void
    {
        $this->register()->assertRedirect();

        $registration = DelegateRegistration::withoutGlobalScopes()->sole();

        $this->assertDatabaseHas('audit_log', [
            'auditable_type' => DelegateRegistration::class,
            'auditable_id' => $registration->id,
            'action' => AuditLog::CREATED,
        ]);
    }

    /* What keeps it from being a mailing list ---------------------------- */

    public function test_the_honeypot_refuses_a_filing_and_stores_nothing(): void
    {
        $this->register(['website' => 'https://example.test'])
            ->assertSessionHasErrors('website');

        $this->assertSame(0, DelegateRegistration::withoutGlobalScopes()->count());
        Mail::assertNothingQueued();
    }

    public function test_the_form_is_rate_limited(): void
    {
        // Sixty in an hour per address (a room registering on one Wi-Fi),
        // on the registration limiter's own counter. The sixty-first is refused.
        for ($i = 0; $i < 60; $i++) {
            $this->register(['delegate_email' => "delegate{$i}@example.test"])->assertRedirect();
        }

        $this->register(['delegate_email' => 'one-too-many@example.test'])->assertStatus(429);
    }

    public function test_a_departure_before_an_arrival_is_refused(): void
    {
        $this->register([
            'arrival_on' => now()->addMonth()->addDays(3)->toDateString(),
            'departure_on' => now()->addMonth()->toDateString(),
        ])->assertSessionHasErrors('departure_on');
    }

    public function test_registration_closes_once_the_meeting_has_started(): void
    {
        $past = Meeting::create([
            'title' => 'Executive Committee, 2024',
            'slug' => 'exco-2024',
            'kind' => 'committee',
            'starts_on' => now()->subYear()->toDateString(),
            'ends_on' => now()->subYear()->addDay()->toDateString(),
            'clearance' => Clearance::Public,
            'is_sample' => true,
        ]);

        $this->get("/en/events/{$past->slug}/register")->assertStatus(410);
        $this->post("/en/events/{$past->slug}/register", $this->filing())->assertStatus(410);
    }

    /* THE BOUNDARY ------------------------------------------------------- */

    /**
     * The columns that are not there. Screen 18 draws all four and each is
     * the managing half.
     */
    public function test_the_reserved_columns_do_not_exist(): void
    {
        $columns = Schema::getColumnListing('delegate_registrations');

        foreach ([
            'accommodation', 'hotel', 'room', 'room_type', 'nights', 'nights_required',
            'dietary', 'dietary_requirements', 'diet',
            'transfer', 'transfer_group', 'driver', 'vehicle', 'pickup',
            'visa', 'visa_letter', 'feedback', 'rating',
            'reminder_sent_at', 'reminded_at', 'chase_count',
        ] as $reserved) {
            $this->assertNotContains(
                $reserved,
                $columns,
                "delegate_registrations.{$reserved} crosses the line into event management."
            );
        }

        // And the same on meetings, which is where a room block would go.
        foreach (['hotel', 'room_block', 'catering', 'interpretation', 'transfer'] as $reserved) {
            $this->assertNotContains($reserved, Schema::getColumnListing('meetings'));
        }
    }

    /** The fields that are not on the form. */
    public function test_the_reserved_fields_are_not_on_the_form(): void
    {
        $html = $this->get("/en/events/{$this->meeting->slug}/register")->assertOk()->getContent();

        foreach (['accommodation', 'nights', 'dietary', 'transfer', 'visa'] as $reserved) {
            $this->assertStringNotContainsString(
                'name="'.$reserved,
                $html,
                "A {$reserved} field is on the delegate form."
            );
        }
    }

    /** And the routes that do not exist. */
    public function test_no_route_schedules_allocates_or_reminds(): void
    {
        $names = collect(app('router')->getRoutes())
            ->map(fn ($route) => (string) $route->getName())
            ->filter()
            ->values();

        foreach (['transfer', 'accommodation', 'reminder', 'allocate', 'roster.assign', 'feedback'] as $reserved) {
            $this->assertEmpty(
                $names->filter(fn (string $name) => str_contains($name, $reserved))->all(),
                "A route named for {$reserved} exists."
            );
        }
    }

    /**
     * Nothing schedules a second message. The confirmation is sent once,
     * when the form is filed.
     */
    public function test_the_confirmation_is_sent_once_and_never_scheduled(): void
    {
        $this->register()->assertRedirect();

        Mail::assertQueuedCount(1);

        /*
         * Comments stripped before the check. Both files say in prose that
         * they do not remind, and a guard that trips on its own explanation
         * of why it exists is a guard nobody keeps.
         */
        $source = $this->withoutComments(app_path('Mail/DelegateRegistrationFiled.php'))
            .$this->withoutComments(app_path('Http/Controllers/DelegateRegistrationController.php'));

        foreach (['->later(', '->delay(', 'schedule', 'reminder'] as $scheduling) {
            $this->assertStringNotContainsString(
                $scheduling,
                $source,
                'Something in the delegate path schedules a later message.'
            );
        }
    }

    private function withoutComments(string $path): string
    {
        $code = '';

        foreach (token_get_all(file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }
}
