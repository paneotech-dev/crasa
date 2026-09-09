<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Meeting;
use App\Models\User;
use App\Services\CalendarFeed;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Screen 12's calendar.
 *
 * The build had an ordered table where the screen draws a month grid with
 * meetings on their dates, a committee filter and an export. All the data
 * was right; the view was not built.
 */
class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->user = User::factory()->administrator()->create();
    }

    public function test_the_calendar_draws_a_month_grid(): void
    {
        $html = $this->actingAs($this->user)->get('/en/extranet/calendar')->assertOk()->getContent();

        $this->assertStringContainsString('e-cal__grid', $html);
        // Every day of the month has a cell.
        $this->assertSame(now()->daysInMonth, substr_count($html, 'e-cal__daynum'));
    }

    /**
     * The month is in the query string, so a particular month is a URL a
     * chair can send.
     */
    public function test_a_month_is_a_url(): void
    {
        Meeting::factory()->create([
            'title' => 'February planning session',
            'starts_on' => '2027-02-11',
            'ends_on' => '2027-02-11',
            'clearance' => 'member',
        ]);

        $this->actingAs($this->user)
            ->get('/en/extranet/calendar?month=2027-02')
            ->assertOk()
            ->assertSee('February 2027')
            ->assertSee('February planning session');
    }

    /**
     * A mistyped link should still open a calendar.
     *
     * "2027-99" is the one that mattered: Carbon parses it happily to March
     * 2035 rather than refusing it, so a try/catch alone caught nothing and
     * a reader who mistyped a month would have landed nine years out with
     * no indication. The month is now checked with a pattern before it is
     * parsed.
     */
    public function test_an_unparseable_month_falls_back_rather_than_erroring(): void
    {
        foreach (['banana', '2027-99', '2027-00', '2027-13', '', '2027', '99999-01'] as $month) {
            $this->actingAs($this->user)
                ->get('/en/extranet/calendar?month='.$month)
                ->assertOk()
                ->assertSee(now()->isoFormat('MMMM YYYY'));
        }
    }

    /**
     * Screen 12: "Type is carried by colour and by the label in every cell."
     * Colour alone is a meeting type a colour-blind chair cannot read.
     */
    public function test_a_meeting_type_is_labelled_in_the_cell_and_not_only_coloured(): void
    {
        Meeting::factory()->create([
            'title' => 'Annual general meeting',
            'kind' => 'agm',
            'starts_on' => now()->startOfMonth()->addDays(9),
            'ends_on' => now()->startOfMonth()->addDays(9),
            'clearance' => 'member',
        ]);

        $html = $this->actingAs($this->user)->get('/en/extranet/calendar')->assertOk()->getContent();
        $cell = substr($html, strpos($html, 'e-cal__grid'), 6000);

        $this->assertStringContainsString('data-kind="agm"', $cell);
        $this->assertStringContainsString('Annual General Meeting', $cell, 'The meeting type is carried by colour alone.');
    }

    public function test_the_committee_filter_narrows_the_month(): void
    {
        $committees = Committee::limit(2)->get();

        foreach ($committees as $i => $committee) {
            Meeting::factory()->create([
                'committee_id' => $committee->id,
                'title' => 'Session for '.$committee->slug,
                'starts_on' => now()->startOfMonth()->addDays(3 + $i),
                'ends_on' => now()->startOfMonth()->addDays(3 + $i),
                'clearance' => 'member',
            ]);
        }

        $this->actingAs($this->user)
            ->get('/en/extranet/calendar?committee='.$committees->first()->slug)
            ->assertOk()
            ->assertSee('Session for '.$committees->first()->slug)
            ->assertDontSee('Session for '.$committees->last()->slug);
    }

    public function test_an_empty_month_says_so(): void
    {
        $this->actingAs($this->user)
            ->get('/en/extranet/calendar?month=2029-12')
            ->assertOk()
            ->assertSee('No meetings scheduled this month');
    }

    /* The export ------------------------------------------------------------- */

    public function test_the_month_downloads_as_a_calendar_file(): void
    {
        Meeting::factory()->create([
            'title' => 'Spectrum session, Gaborone',
            'starts_on' => now()->startOfMonth()->addDays(5),
            'ends_on' => now()->startOfMonth()->addDays(6),
            'location' => 'Gaborone, Botswana',
            'clearance' => 'member',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/en/extranet/calendar.ics?month='.now()->format('Y-m'))
            ->assertOk();

        $this->assertStringContainsString('text/calendar', $response->headers->get('content-type'));

        $ics = $response->getContent();
        $this->assertStringStartsWith('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        // Commas inside a text value have to be escaped or the client reads
        // the location as two fields.
        $this->assertStringContainsString('LOCATION:Gaborone\\, Botswana', $ics);

        // CRLF, which some clients require absolutely.
        $this->assertStringContainsString("\r\n", $ics);
        $this->assertStringNotContainsString("\n\n", $ics);
    }

    /**
     * Screen 12: "The feed carries only the meetings your category can see."
     */
    public function test_the_feed_carries_only_what_the_reader_may_see(): void
    {
        Meeting::factory()->create([
            'title' => 'Closed session, chairs only',
            'starts_on' => now()->startOfMonth()->addDays(7),
            'ends_on' => now()->startOfMonth()->addDays(7),
            'clearance' => Clearance::Confidential->value,
        ]);

        $rep = User::factory()->memberStateRep(Country::first())->create();

        $ics = $this->actingAs($rep)
            ->get('/en/extranet/calendar.ics?month='.now()->format('Y-m'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Closed session, chairs only', $ics);
    }

    /**
     * DTEND is exclusive for an all-day event, so a one-day meeting ends the
     * following day. Getting this wrong shows every meeting a day short.
     */
    public function test_a_one_day_meeting_does_not_lose_its_day(): void
    {
        $meeting = Meeting::factory()->make([
            'id' => 1,
            'title' => 'One day only',
            'starts_on' => '2026-09-14',
            'ends_on' => '2026-09-14',
        ]);

        $ics = app(CalendarFeed::class)->ics(collect([$meeting]));

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260914', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260915', $ics);
    }

    public function test_the_calendar_is_behind_authentication(): void
    {
        $this->get('/en/extranet/calendar')->assertRedirect();
        $this->get('/en/extranet/calendar.ics')->assertRedirect();
    }
}
