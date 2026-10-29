<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Committee;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The events calendar, event page, home block and the event form (E1 to
 * E4, C12).
 */
class EventsDay2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->admin = User::factory()->administrator()->create();
        $this->travelTo('2026-10-29 10:00:00');
    }

    private function mainContent(string $html): string
    {
        $start = strpos($html, '<main id="content"');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '</main>', $start) - $start);
    }

    private function meeting(array $over = []): Meeting
    {
        static $n = 0;
        $n++;

        return Meeting::create($over + [
            'title' => 'Meeting '.$n,
            'slug' => 'meeting-'.$n,
            'starts_on' => '2026-11-10',
            'ends_on' => '2026-11-12',
            'location' => 'Gaborone, Botswana',
            'clearance' => Clearance::Public,
            'committee_code' => 'PC',
        ]);
    }

    public function test_the_calendar_opens_on_the_next_month_with_a_meeting(): void
    {
        $this->meeting(['title' => 'Postal Committee in November']);

        $html = $this->get('/en/events')->assertOk()->getContent();

        $this->assertStringContainsString('November 2026', $html);
        $this->assertStringContainsString('<table class="e-grid"', $html);
        $this->assertStringContainsString('<th scope="col">', $html);
        $this->assertStringContainsString('e-chip__code', $html);
        // The code is printed, so colour is never the only signal.
        $this->assertMatchesRegularExpression('/e-chip__code" aria-hidden="true">PC</', $html);
        $this->assertStringContainsString('Postal Committee', $html);
    }

    public function test_today_is_marked_in_gaborone_time(): void
    {
        $this->meeting(['starts_on' => '2026-10-30', 'ends_on' => '2026-10-30']);
        // 23:30 UTC on 29 October is already 30 October in Gaborone.
        $this->travelTo('2026-10-29 23:30:00');

        $html = $this->get('/en/events?month=2026-10')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-date="2026-10-30">\s*<span class="e-day__num"\s+aria-current="date"/', $html);
    }

    public function test_upcoming_cards_offer_google_outlook_and_ics(): void
    {
        $this->meeting(['title' => 'Card meeting', 'slug' => 'card-meeting']);

        $this->get('/en/events')->assertOk()
            ->assertSee('https://calendar.google.com/calendar/render?action=TEMPLATE', false)
            ->assertSee('https://outlook.live.com/calendar/0/deeplink/compose?', false)
            ->assertSee(url('/en/events/card-meeting/calendar.ics'), false)
            ->assertSee('Register');
    }

    public function test_past_meetings_are_grouped_by_year_with_a_filter(): void
    {
        $this->meeting(['title' => 'Old one', 'starts_on' => '2024-05-20', 'ends_on' => '2024-05-21']);
        $this->meeting(['title' => 'Recent one', 'starts_on' => '2026-02-02', 'ends_on' => '2026-02-03']);

        // The most recent year opens; the others are one chip away.
        $html = $this->get('/en/events')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-year="2024"\s+hidden/', $html);
        $this->assertStringContainsString('data-year-filter="2024"', $html);

        $all = $this->get('/en/events?year=all')->getContent();
        $this->assertDoesNotMatchRegularExpression('/data-year="2024"\s+hidden/', $all);
    }

    public function test_the_committee_filter_keeps_one_committee(): void
    {
        $this->meeting(['title' => 'Postal one', 'committee_code' => 'PC']);
        $this->meeting(['title' => 'Legal one', 'committee_code' => 'LPC']);

        // The Concept 3 header's Events menu features the next event on
        // every page, so only the page's own content is read.
        $main = $this->mainContent($this->get('/en/events?committee=LPC')->assertOk()->getContent());
        $this->assertStringContainsString('Legal one', $main);
        $this->assertStringNotContainsString('Postal one', $main);
    }

    public function test_a_members_only_event_shows_only_to_signed_in_members(): void
    {
        $this->meeting(['title' => 'Validation workshop', 'slug' => 'validation', 'clearance' => Clearance::Member, 'starts_on' => '2026-10-28', 'ends_on' => '2026-10-30']);

        $this->get('/en/events')->assertOk()->assertDontSee('Validation workshop');
        $this->get('/en/events/validation')->assertNotFound();

        $this->actingAs($this->admin)->get('/en/events')->assertOk()
            ->assertSee('Validation workshop')
            ->assertSee('Members only');
        $this->actingAs($this->admin)->get('/en/events/validation')->assertOk();
    }

    public function test_the_event_page_shows_time_zone_map_and_neighbours(): void
    {
        $this->meeting(['title' => 'Before', 'slug' => 'before', 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-02']);
        $this->meeting(['title' => 'This one', 'slug' => 'this-one', 'organiser' => 'CRASA Secretariat', 'starts_at' => '09:00', 'ends_at' => '17:00', 'timezone' => 'Africa/Gaborone']);
        $this->meeting(['title' => 'After', 'slug' => 'after', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-02']);

        foreach (['en', 'fr', 'pt'] as $locale) {
            $this->get("/{$locale}/events/this-one")->assertOk()
                ->assertSee('09:00')
                ->assertSee('CAT')
                ->assertSee('Africa/Gaborone')
                ->assertSee('tile.openstreetmap.org', false)
                ->assertSee('CRASA Secretariat')
                ->assertSee('/events/before', false)
                ->assertSee('/events/after', false)
                ->assertSee(__('e.file_arrival', [], $locale));
        }
    }

    public function test_a_cancelled_event_says_so_and_offers_no_calendar_or_registration(): void
    {
        $this->meeting(['title' => 'Called off', 'slug' => 'called-off', 'status' => 'cancelled', 'status_note' => 'Moved to 2027.']);

        $html = $this->get('/en/events/called-off')->assertOk()
            ->assertSee('This meeting is cancelled.')
            ->assertSee('Moved to 2027.')
            ->getContent();
        // The header's Events menu names Add to my calendar on every page.
        $main = $this->mainContent($html);
        $this->assertStringNotContainsString(__('events.add_to_calendar'), $main);
        $this->assertStringNotContainsString(__('e.file_arrival'), $main);
        $this->get('/en/events')->assertSee('Cancelled');
    }

    public function test_the_home_block_fills_with_past_meetings_when_little_is_to_come(): void
    {
        $this->meeting(['title' => 'Only upcoming']);
        $this->meeting(['title' => 'Last spring', 'starts_on' => '2026-04-16', 'ends_on' => '2026-04-17']);
        $this->meeting(['title' => 'Last winter', 'starts_on' => '2026-02-02', 'ends_on' => '2026-02-03']);

        $html = view('partials.home.upcoming')->render();
        $this->assertStringContainsString('Only upcoming', $html);
        $this->assertStringContainsString('Last spring', $html);
        $this->assertStringContainsString('Last winter', $html);
        $this->assertStringContainsString('>Past<', $html);
    }

    /* Back office ------------------------------------------------------------- */

    private function valid(array $over = []): array
    {
        return $over + [
            'title' => 'Workshop on numbering',
            'committee_code' => 'ECC',
            'kind' => 'workshop',
            'starts_on' => '2026-12-07',
            'ends_on' => '2026-12-08',
            'starts_at' => '09:00',
            'ends_at' => '16:30',
            'timezone' => 'Africa/Gaborone',
            'location' => 'Lusaka, Zambia',
            'clearance' => 'public',
            'capacity' => 40,
            'agenda' => 'Opening',
        ];
    }

    public function test_the_secretariat_creates_an_event_with_a_document(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->get('/en/admin/meetings/new')->assertOk()
            ->assertSee(__('e.step_basics'))->assertSee(__('e.step_when'))
            ->assertSee(__('e.step_people'))->assertSee(__('e.step_documents'))
            ->assertSee(__('e.step_summary'));

        $this->actingAs($this->admin)->post('/en/admin/meetings', $this->valid([
            'files' => [UploadedFile::fake()->create('agenda.pdf', 20, 'application/pdf')],
        ]))->assertRedirect();

        $meeting = Meeting::where('title', 'Workshop on numbering')->firstOrFail();
        $this->assertSame('admin', $meeting->source);
        $this->assertSame('ECC', $meeting->committee_code);
        $this->assertSame(1, $meeting->documents()->count());

        $this->get('/en/events/'.$meeting->slug)->assertOk()->assertSee('agenda.pdf')->assertSee('09:00');
    }

    public function test_the_form_explains_what_is_wrong_next_to_the_field(): void
    {
        $this->actingAs($this->admin)
            ->from('/en/admin/meetings/new')
            ->post('/en/admin/meetings', $this->valid(['title' => '', 'ends_on' => '2026-12-01']))
            ->assertRedirect('/en/admin/meetings/new')
            ->assertSessionHasErrors(['title', 'ends_on']);

        $this->actingAs($this->admin)
            ->from('/en/admin/meetings/new')
            ->followingRedirects()
            ->post('/en/admin/meetings', $this->valid(['title' => '', 'ends_on' => '2026-12-01']))
            ->assertSee('aria-invalid="true"', false)
            ->assertSee(__('e.error_ends_on'));
    }

    public function test_postponing_shows_on_the_public_page(): void
    {
        $meeting = $this->meeting(['slug' => 'to-move']);

        $this->actingAs($this->admin)->put('/en/admin/meetings/to-move/status', ['status' => 'postponed', 'status_note' => 'New dates in January.'])
            ->assertRedirect();

        $this->get('/en/events/to-move')->assertSee('This meeting is postponed.')->assertSee('New dates in January.');
        $this->get('/en/events')->assertSee('Postponed');
    }

    public function test_editing_and_deleting_with_a_confirmation(): void
    {
        $this->meeting(['slug' => 'to-edit']);

        $this->actingAs($this->admin)->get('/en/admin/meetings')->assertOk()
            ->assertSee('data-delete-event', false)
            ->assertSee('<dialog', false)
            ->assertSee(__('e.admin_new'));

        $this->actingAs($this->admin)->put('/en/admin/meetings/to-edit', $this->valid(['title' => 'Renamed']))->assertRedirect();
        $this->assertSame('Renamed', Meeting::where('slug', 'to-edit')->value('title'));

        $this->actingAs($this->admin)->get('/en/admin/meetings/to-edit/delete')->assertOk()->assertSee(__('e.admin_delete_confirm'));
        $this->actingAs($this->admin)->delete('/en/admin/meetings/to-edit')->assertRedirect('/en/admin/meetings');
        $this->assertDatabaseMissing('meetings', ['slug' => 'to-edit']);
    }

    public function test_delegates_are_confirmed_or_declined_and_exported_with_their_status(): void
    {
        $meeting = $this->meeting(['slug' => 'roster']);
        $row = DelegateRegistration::create([
            'meeting_id' => $meeting->id, 'delegate_name' => 'A delegate', 'delegate_email' => 'a@example.org',
            'arrival_on' => '2026-11-09',
        ]);

        $this->actingAs($this->admin)->put("/en/admin/meetings/roster/delegates/{$row->id}", ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame('confirmed', $row->fresh()->status);

        $csv = $this->actingAs($this->admin)->get('/en/admin/meetings/roster/delegates.csv')->streamedContent();
        $this->assertStringContainsString('Confirmed', $csv);

        $viewer = User::factory()->viewer()->create();
        $this->actingAs($viewer)->put("/en/admin/meetings/roster/delegates/{$row->id}", ['status' => 'declined'])->assertForbidden();
    }
}
