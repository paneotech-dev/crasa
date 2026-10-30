<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Country;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Events and delegate registration, WS-07.
 *
 * HALF THIS FILE IS ABOUT WHAT IS NOT BUILT, and that is the point. The
 * line protects a separate commercial agreement: the website collects the
 * information, the Event Management Platform manages the operation. Screen
 * 18 draws several things on the managing side, and they are deliberately
 * absent.
 *
 * A test that fails when somebody adds an accommodation column is worth
 * more than a comment saying not to.
 */
class EventsAndDelegatesTest extends TestCase
{
    use RefreshDatabase;

    private Meeting $meeting;

    private User $secretariat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->secretariat = User::factory()->administrator()->create();

        $this->meeting = Meeting::create([
            'title' => 'CRASA 15th Annual General Meeting',
            'slug' => 'agm-15',
            'kind' => 'agm',
            'starts_on' => now()->addMonth(),
            'ends_on' => now()->addMonth()->addDays(4),
            'location' => 'Gaborone, Botswana',
            'clearance' => Clearance::Public,
            'registration_url' => 'https://events.example/agm-15',
        ]);
    }

    /* The events section is read only ---------------------------------------- */

    public function test_the_events_section_lists_public_meetings(): void
    {
        $this->get('/en/events')
            ->assertOk()
            ->assertSee('CRASA 15th Annual General Meeting')
            ->assertSee('Gaborone, Botswana');
    }

    public function test_a_restricted_meeting_is_not_in_the_public_events_section(): void
    {
        Meeting::create([
            'title' => 'Closed committee session', 'slug' => 'closed-session',
            'starts_on' => now()->addWeek(), 'clearance' => Clearance::Confidential,
        ]);

        $this->get('/en/events')->assertOk()->assertDontSee('Closed committee session');
        $this->get('/en/events/closed-session')->assertNotFound();
    }

    public function test_the_events_section_links_out_to_the_organisers(): void
    {
        // The website displays and links out. It does not run registration
        // for the meeting itself.
        $this->get('/en/events/agm-15')
            ->assertOk()
            ->assertSee('https://events.example/agm-15');
    }

    /**
     * Since Day 2 (E4) the back office creates, edits, cancels and deletes
     * events. Every write on an event is under /admin and needs the
     * meetings permission; the one public write is a delegate filing their
     * own arrival. Travel, rooms and transport are still not built.
     */
    public function test_only_the_back_office_manages_an_event(): void
    {
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_contains($uri, 'events') && ! str_contains($uri, 'meetings')) {
                continue;
            }

            $writes = array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);

            if ($writes !== [] && ! str_contains($uri, 'register') && ! str_contains($uri, '/admin/')) {
                $offenders[] = implode('|', $writes).' '.$uri;
            }
        }

        $this->assertSame([], $offenders, 'These public routes write to an event: '.implode(', ', $offenders));

        $viewer = User::factory()->viewer()->create();
        $this->actingAs($viewer)->post('/en/admin/meetings', ['title' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->delete('/en/admin/meetings/agm-15')->assertForbidden();
        $this->assertDatabaseHas('meetings', ['slug' => 'agm-15']);
    }

    /* Delegate registration: what is built ----------------------------------- */

    public function test_a_delegate_files_an_arrival_and_a_departure(): void
    {
        $this->post('/en/events/agm-15/register', [
            'delegate_name' => 'A delegate',
            'delegate_email' => 'delegate@example.org',
            'delegate_role' => 'Director of Regulation',
            'arrival_point' => 'Sir Seretse Khama International',
            'arrival_on' => now()->addMonth()->toDateString(),
            'arrival_at' => '14:35',
            'arrival_reference' => 'BP 202',
            'departure_on' => now()->addMonth()->addDays(4)->toDateString(),
            'departure_at' => '09:10',
            'departure_reference' => 'BP 205',
        ])->assertRedirect();

        $registration = DelegateRegistration::withoutGlobalScopes()->sole();

        $this->assertSame('A delegate', $registration->delegate_name);
        $this->assertSame('BP 202', $registration->arrival_reference);
    }

    public function test_a_departure_before_an_arrival_is_refused(): void
    {
        // A typo somebody should be told about now rather than at the
        // airport.
        $this->from('/en/events/agm-15/register')
            ->post('/en/events/agm-15/register', [
                'delegate_name' => 'A delegate',
                'delegate_email' => 'delegate@example.org',
                'arrival_on' => now()->addMonth()->addDays(4)->toDateString(),
                'departure_on' => now()->addMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('departure_on');
    }

    /*
     * Filing twice signed in updates the delegate's own filing. This used to
     * file twice signed out and expect the second to replace the first;
     * matching on the address alone let anybody replace another delegate's
     * filing, so a signed-out second filing is now refused and stores nothing.
     */
    public function test_filing_twice_updates_rather_than_duplicates(): void
    {
        $payload = [
            'delegate_name' => 'A delegate',
            'delegate_email' => 'delegate@example.org',
            'arrival_on' => now()->addMonth()->toDateString(),
            'departure_on' => now()->addMonth()->addDays(4)->toDateString(),
        ];
        $delegate = User::factory()->create(['email' => 'delegate@example.org']);

        $this->actingAs($delegate)->post('/en/events/agm-15/register', $payload);
        $this->actingAs($delegate)->post('/en/events/agm-15/register', $payload + ['arrival_reference' => 'BP 202']);

        $this->assertSame(1, DelegateRegistration::withoutGlobalScopes()->count());
        $this->assertSame('BP 202', DelegateRegistration::withoutGlobalScopes()->sole()->arrival_reference);

        auth()->logout();
        $this->post('/en/events/agm-15/register', $payload + ['arrival_reference' => 'XX 1'])->assertSessionHasErrors('delegate_email');
        $this->assertSame('BP 202', DelegateRegistration::withoutGlobalScopes()->sole()->arrival_reference);
    }

    public function test_registration_closes_once_the_meeting_has_happened(): void
    {
        $past = Meeting::create([
            'title' => 'Last year', 'slug' => 'last-year',
            'starts_on' => now()->subYear(), 'clearance' => Clearance::Public,
        ]);

        $this->get('/en/events/last-year/register')->assertStatus(410);
        $this->post('/en/events/last-year/register', [])->assertStatus(410);
    }

    /* The Secretariat's list -------------------------------------------------- */

    public function test_the_secretariat_reads_a_plain_sortable_list(): void
    {
        DelegateRegistration::create([
            'meeting_id' => $this->meeting->id,
            'country_id' => Country::where('iso_code', 'bw')->value('id'),
            'delegate_name' => 'A delegate', 'delegate_email' => 'a@example.org',
            'arrival_on' => now()->addMonth(),
        ]);

        $this->actingAs($this->secretariat)
            ->get('/en/admin/meetings/agm-15/delegates?sort=delegate_name&direction=desc')
            ->assertOk()
            ->assertSee('A delegate')
            ->assertSee('aria-sort', false);
    }

    public function test_the_list_exports_as_csv(): void
    {
        DelegateRegistration::create([
            'meeting_id' => $this->meeting->id,
            'delegate_name' => 'A delegate', 'delegate_email' => 'a@example.org',
            'arrival_on' => now()->addMonth(), 'arrival_reference' => 'BP 202',
        ]);

        $csv = $this->actingAs($this->secretariat)
            ->get('/en/admin/meetings/agm-15/delegates.csv')
            ->assertOk()
            ->streamedContent();

        // The handover point: this is how the information reaches the
        // platform that schedules with it.
        $this->assertStringContainsString('A delegate', $csv);
        $this->assertStringContainsString('BP 202', $csv);
    }

    public function test_the_delegate_list_needs_the_meetings_permission(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin/meetings/agm-15/delegates')->assertForbidden();
        $this->actingAs($viewer)->get('/en/admin/meetings/agm-15/delegates.csv')->assertForbidden();
    }

    /* The boundary ------------------------------------------------------------ */

    /**
     * Screen 18 draws accommodation, nights required, dietary requirements
     * and a transfer schedule. Each is the managing half rather than the
     * collecting half, and each is deliberately absent.
     */
    public function test_the_registration_record_holds_nothing_that_belongs_to_event_management(): void
    {
        $columns = Schema::getColumnListing('delegate_registrations');

        foreach ([
            'accommodation', 'hotel', 'nights', 'room',
            'dietary', 'diet', 'meal', 'catering',
            'transfer', 'driver', 'vehicle', 'pickup',
            // 'interpretation' left this list on 30 October 2026: client
            // item 1e asked for the delegate's interpretation request
            // (interpretation_into), a recorded need, not a booking.
            'feedback', 'reminder',
        ] as $forbidden) {
            foreach ($columns as $column) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $column,
                    'delegate_registrations.'.$column.' crosses into event management, which is reserved commercial scope.'
                );
            }
        }
    }

    public function test_the_form_does_not_ask_for_anything_on_the_managing_side(): void
    {
        $html = $this->get('/en/events/agm-15/register')->assertOk()->getContent();

        foreach (['accommodation', 'nights', 'dietary', 'transfer', 'hotel'] as $forbidden) {
            $this->assertStringNotContainsString(
                'name="'.$forbidden,
                $html,
                'The registration form asks for '.$forbidden.', which the Event Management Platform handles.'
            );
        }
    }

    /**
     * Nothing on this platform sends a reminder or a chase.
     *
     * The platform grew a second and a third notification class on
     * 10 September 2026, both about regulatory content: an announcement to
     * the membership, and activity in a reader's own sector or committee.
     * A count of one would have stopped saying anything the day that
     * happened, so this reads what the classes are about instead.
     *
     * Every notification either names an existing class or fires a trigger
     * from a closed enum, and neither the class names nor the trigger
     * values may mention a delegate, a journey or a room. Event and travel
     * management is a separate commercial agreement.
     */
    public function test_nothing_notifies_a_delegate(): void
    {
        $forbidden = [
            'delegate', 'travel', 'flight', 'hotel', 'accommodation',
            'transfer', 'catering', 'reminder', 'visa', 'room', 'itinerary',
        ];

        $classes = array_map(
            fn ($file) => $file->getFilenameWithoutExtension(),
            File::files(app_path('Notifications'))
        );

        foreach (array_merge($classes, \App\Enums\NotificationTrigger::values()) as $name) {
            foreach ($forbidden as $word) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $word,
                    $name,
                    "[{$name}] notifies about logistics. That is the Event Management Platform's work and a separate commercial agreement."
                );
            }
        }

        // And the registration itself notifies nobody: a delegate who files
        // an arrival hears nothing back from this platform.
        $this->assertNotContains('DelegateRegistered', $classes);
    }

    /** It is called delegate registration everywhere, including in the copy. */
    public function test_it_is_never_called_travel_logistics(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach (['delegates', 'events'] as $file) {
                $strings = require lang_path($locale.'/'.$file.'.php');

                foreach ($strings as $key => $value) {
                    $this->assertStringNotContainsStringIgnoringCase(
                        'travel logistics',
                        (string) $value,
                        $locale.'/'.$file.'.php:'.$key
                    );
                }
            }
        }
    }
}
