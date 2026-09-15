<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\Announcement;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Sector;
use App\Models\Submission;
use App\Models\User;
use App\Support\CountryDashboard;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The country dashboard, RP-12 and extranet item 1.
 *
 * A national regulator signing in wants one question answered: what does my
 * authority owe, and what has changed since I was last here. The page
 * opened on a welcome and three panels of recent things, which answered
 * neither.
 *
 * ORDER IS THE DESIGN, and it is not recency. A returned submission is the
 * only item on the page blocked on this reader, so it is first, and that is
 * asserted here rather than left to the view.
 */
class CountryDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $botswanaRep;

    private User $mozambiqueRep;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $this->botswanaRep = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => Country::where('iso_code', 'bw')->value('id'),
        ]);

        $this->mozambiqueRep = User::factory()->create([
            'role' => 'member_state_rep',
            'country_id' => Country::where('iso_code', 'mz')->value('id'),
        ]);
    }

    public function test_the_dashboard_opens_on_this_state_rather_than_a_welcome(): void
    {
        $this->actingAs($this->botswanaRep)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertSee('Botswana');
    }

    public function test_a_returned_submission_is_listed_before_everything_else(): void
    {
        Submission::create([
            'country_id' => $this->botswanaRep->country_id,
            'submitted_by' => $this->botswanaRep->id,
            'period' => '2025',
            'status' => SubmissionStatus::Returned->value,
            'reviewed_at' => now()->subWeeks(3),
            'review_note' => 'Row 14: the termination rate must be a number.',
            'is_sample' => false,
        ]);

        $html = $this->actingAs($this->botswanaRep)->get('/en/extranet/dashboard')->assertOk()->getContent();

        $returned = strpos($html, 'Row 14: the termination rate must be a number.');
        $meeting = strpos($html, __('extranet.dash_next_meeting'));

        $this->assertNotFalse($returned, 'The returned submission is not on the dashboard.');
        $this->assertNotFalse($meeting);
        $this->assertLessThan(
            $meeting,
            $returned,
            'A returned submission has to come before the panels of recent activity. It is the only item on the page blocked on this reader.'
        );
    }

    public function test_the_completeness_figure_carries_a_rank_among_the_fourteen(): void
    {
        $indicator = Indicator::active()->firstOrFail();

        IndicatorValue::create([
            'indicator_id' => $indicator->id,
            'country_id' => $this->botswanaRep->country_id,
            'period' => '2025',
            'value_numeric' => 12,
            'confidence' => 'reported',
            'is_sample' => false,
        ]);

        $dashboard = CountryDashboard::for($this->botswanaRep->fresh());

        $this->assertSame(14, $dashboard['completeness']['of']);
        $this->assertSame(1, $dashboard['completeness']['rank'], 'The only state with a figure should rank first.');

        $behind = CountryDashboard::for($this->mozambiqueRep->fresh());
        $this->assertSame(2, $behind['completeness']['rank']);
    }

    /** States on the same percentage share a rank rather than being ordered arbitrarily. */
    public function test_states_on_the_same_figure_share_a_rank(): void
    {
        $a = CountryDashboard::for($this->botswanaRep);
        $b = CountryDashboard::for($this->mozambiqueRep);

        $this->assertSame($a['completeness']['rank'], $b['completeness']['rank']);
        $this->assertSame(1, $a['completeness']['rank']);
    }

    public function test_a_period_with_no_return_at_all_is_still_shown_as_due(): void
    {
        $dashboard = CountryDashboard::for($this->botswanaRep);

        $this->assertNotEmpty($dashboard['due'], 'A period nobody has started has no row, so a query over submissions cannot see it.');
        $this->assertSame('not_started', $dashboard['due'][0]['status']);
        $this->assertNotNull($dashboard['due'][0]['due_on']);
    }

    public function test_a_filed_and_validated_period_drops_off_the_due_list(): void
    {
        $year = (string) now()->year;

        Submission::create([
            'country_id' => $this->botswanaRep->country_id,
            'submitted_by' => $this->botswanaRep->id,
            'period' => $year,
            'status' => SubmissionStatus::Validated->value,
            'is_sample' => false,
        ]);

        $periods = collect(CountryDashboard::for($this->botswanaRep)['due'])->pluck('period');

        $this->assertNotContains($year, $periods);
    }

    /* The boundary ---------------------------------------------------------- */

    public function test_botswana_is_not_shown_mozambiques_returned_submission(): void
    {
        Submission::create([
            'country_id' => $this->mozambiqueRep->country_id,
            'submitted_by' => $this->mozambiqueRep->id,
            'period' => '2025',
            'status' => SubmissionStatus::Returned->value,
            'review_note' => 'A note only Mozambique should read.',
            'is_sample' => false,
        ]);

        $this->actingAs($this->botswanaRep)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertDontSee('A note only Mozambique should read.');
    }

    /* Announcements --------------------------------------------------------- */

    public function test_a_general_announcement_reaches_every_member_state(): void
    {
        $this->announcement('Every member state should read this');

        $this->actingAs($this->botswanaRep)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertSee('Every member state should read this');
    }

    public function test_an_announcement_narrowed_to_a_committee_is_not_shown_to_a_state_without_that_seat(): void
    {
        $committee = \App\Models\Committee::firstOrFail();
        $this->announcement('Only the postal committee should read this', ['committee_id' => $committee->id]);

        $this->actingAs($this->botswanaRep)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertDontSee('Only the postal committee should read this');

        // And it is not reachable by guessing the slug either.
        $this->actingAs($this->botswanaRep)
            ->get('/en/announcements/targeted')
            ->assertNotFound();
    }

    public function test_an_expired_announcement_stops_showing(): void
    {
        $this->announcement('The deadline was yesterday', ['expires_on' => now()->subDay()->toDateString()]);

        $this->actingAs($this->botswanaRep)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertDontSee('The deadline was yesterday');
    }

    /** @param array<string, mixed> $overrides */
    private function announcement(string $title, array $overrides = []): Announcement
    {
        $announcement = Announcement::create(array_merge([
            'slug' => 'targeted',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));

        $announcement->setTranslation('title', 'en', $title);

        return $announcement;
    }
}
