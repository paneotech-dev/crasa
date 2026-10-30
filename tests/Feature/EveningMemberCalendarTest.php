<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Meeting;
use App\Models\User;
use App\Support\EventGroup;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Evening pass: the member-area calendar on the UI system, with the public calendar's committee codes and colours. */
class EveningMemberCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->user = User::factory()->administrator()->create();
        $this->travelTo('2026-11-02 10:00:00');

        Meeting::create(['title' => 'Postal Committee session', 'slug' => 'pc-session', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-10',
            'location' => 'Windhoek', 'clearance' => Clearance::Member, 'committee_code' => 'PC']);
        Meeting::create(['title' => 'Spectrum session', 'slug' => 'sfmc-session', 'starts_on' => '2026-11-17', 'ends_on' => '2026-11-17',
            'location' => 'Gaborone', 'clearance' => Clearance::Member, 'committee_code' => 'SFMC']);
    }

    public function test_cells_chips_legend_and_cards_carry_the_public_codes_and_colours(): void
    {
        $html = $this->actingAs($this->user)->get('/en/extranet/calendar?month=2026-11')->assertOk()->getContent();
        $pc = EventGroup::code('PC');

        $this->assertStringContainsString('css/member-calendar.css', $html);
        $this->assertMatchesRegularExpression('~<a class="e-cal__meeting mc-chip" data-kind="[a-z]+" data-group="PC" style="--e-c: '.$pc->colour.'"\s+href="http://localhost/en/events/pc-session">~', $html);
        $this->assertStringContainsString('class="mc-legend"', $html);
        $this->assertStringContainsString('class="u-chip"', $html);
        $this->assertStringContainsString('u-card mc-card', $html);
        $this->assertStringContainsString('class="u-btn u-btn--secondary"', $html);
        $this->assertStringNotContainsString('c-btn', substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main')));
    }

    public function test_the_filter_takes_the_public_committee_code(): void
    {
        $this->actingAs($this->user)->get('/en/extranet/calendar?month=2026-11&committee=SFMC')->assertOk()
            ->assertSee('Spectrum session')->assertDontSee('Postal Committee session');
    }
}
