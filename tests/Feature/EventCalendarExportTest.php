<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Meeting;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Add to my calendar" on the public events pages: one event as an .ics
 * file, from the generator the committee calendars use, and as a Google
 * Calendar link. Public, dated events only.
 */
class EventCalendarExportTest extends TestCase
{
    use RefreshDatabase;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->meeting = Meeting::create([
            'title' => 'CRASA 15th Annual General Meeting; plenary',
            'slug' => 'agm-15',
            'kind' => 'agm',
            'starts_on' => '2027-03-10',
            'ends_on' => '2027-03-13',
            'location' => 'Gaborone, Botswana',
            'clearance' => Clearance::Public,
        ]);
    }

    public function test_a_public_event_downloads_as_a_calendar_file(): void
    {
        $response = $this->get('/en/events/agm-15/calendar.ics')->assertOk();

        $this->assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="crasa-agm-15.ics"', $response->headers->get('Content-Disposition'));

        $ics = $response->getContent();
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20270310\r\n", $ics);
        // DTEND is exclusive for an all-day event.
        $this->assertStringContainsString("DTEND;VALUE=DATE:20270314\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:CRASA 15th Annual General Meeting\; plenary', $ics);
        $this->assertStringContainsString('LOCATION:Gaborone\, Botswana', $ics);
        $this->assertStringContainsString('URL:'.url('/en/events/agm-15'), $ics);
        $this->assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
    }

    public function test_only_public_dated_events_have_a_calendar_file(): void
    {
        Meeting::create(['title' => 'Closed session', 'slug' => 'closed', 'starts_on' => '2027-03-10', 'clearance' => Clearance::Confidential]);
        Meeting::create(['title' => 'Member session', 'slug' => 'members-only', 'starts_on' => '2027-03-10', 'clearance' => Clearance::Member]);
        Meeting::create(['title' => 'Undated', 'slug' => 'undated', 'starts_on' => '2027-03-10', 'date_unknown' => true, 'clearance' => Clearance::Public]);

        foreach (['closed', 'members-only', 'undated', 'no-such-event'] as $slug) {
            $this->get("/en/events/{$slug}/calendar.ics")->assertNotFound();
        }
    }

    public function test_the_event_page_and_card_offer_both_calendar_actions_in_every_locale(): void
    {
        $this->travelTo('2027-01-15');

        foreach (['en', 'fr', 'pt'] as $locale) {
            app()->setLocale($locale);
            $ics = "/{$locale}/events/agm-15/calendar.ics";

            foreach (["/{$locale}/events/agm-15", "/{$locale}/events"] as $page) {
                $this->get($page)->assertOk()
                    ->assertSee(__('events.add_to_calendar'))
                    ->assertSee(url($ics), false)
                    ->assertSee('https://calendar.google.com/calendar/render?action=TEMPLATE&amp;text=CRASA%2015th%20Annual%20General%20Meeting%3B%20plenary&amp;dates=20270310%2F20270314', false)
                    ->assertSee('location=Gaborone%2C%20Botswana', false);
            }
        }
    }

    public function test_a_past_event_offers_no_calendar_actions(): void
    {
        $this->travelTo('2027-06-01');

        $html = $this->get('/en/events/agm-15')->assertOk()->getContent();

        // The Concept 3 header's Events menu names Add to my calendar on
        // every page, so only the page's own content is read.
        $this->assertStringNotContainsString(__('events.add_to_calendar'), $this->mainContent($html));
    }

    private function mainContent(string $html): string
    {
        $start = strpos($html, '<main id="content"');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '</main>', $start) - $start);
    }
}
