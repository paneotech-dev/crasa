<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Page;
use App\Models\Publication;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The thirteenth block type.
 *
 * Six committees existed as records that no public page could show. A reader
 * could find which committees a member state sat on, from that state's page,
 * and could not go the other way.
 */
class CommitteeGridTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(PublicPagesSeeder::class);
    }

    public function test_the_committees_page_lists_the_committees(): void
    {
        $html = $this->get('/en/committees')->assertOk()->getContent();

        $this->assertStringContainsString('b-committees', $html);

        foreach (Committee::where('is_active', true)->pluck('name') as $name) {
            $this->assertStringContainsString($name, $html, $name.' is a committee and is not listed.');
        }
    }

    /**
     * An inactive committee is history rather than membership. A page about
     * who does the work should not list one that has been wound up unless
     * it is asked to.
     */
    public function test_an_inactive_committee_is_absent_unless_the_block_asks_for_it(): void
    {
        $retired = Committee::create([
            'slug' => 'retired-working-group',
            'name' => 'Retired working group',
            'mandate' => 'Wound up after its final report.',
            'is_active' => false,
        ]);

        $this->get('/en/committees')->assertOk()->assertDontSee($retired->name);

        $block = Page::where('slug', 'committees')->sole()
            ->blocks()->where('block_type', BlockType::CommitteeGrid->value)->sole();
        $block->update(['settings' => ['include_inactive' => true, 'filter' => []]]);

        $this->get('/en/committees')->assertOk()->assertSee($retired->name);
    }

    /**
     * Screen 04 writes this state out: a committee that has been constituted
     * and has not yet met says so. Three zeroes would report three absences
     * where the truth is one fact.
     */
    public function test_a_committee_that_has_not_started_says_so_rather_than_showing_zeroes(): void
    {
        Committee::query()->delete();
        Committee::create([
            'slug' => 'human-and-institutional-capacity',
            'name' => 'Human and Institutional Capacity',
            'mandate' => 'Builds regulatory capability across the member states.',
            'is_active' => true,
        ]);

        $this->get('/en/committees')
            ->assertOk()
            ->assertSee('has not yet scheduled its first meeting')
            ->assertDontSee('b-committee__tally', false);
    }

    public function test_a_committee_with_work_behind_it_states_its_counts(): void
    {
        $committee = Committee::where('is_active', true)->first();
        Publication::factory()->count(3)->create(['committee_id' => $committee->id, 'is_public' => true]);

        $html = $this->get('/en/committees')->assertOk()->getContent();

        $this->assertStringContainsString('b-committee__tally', $html);
        // The count links to the documents it counts.
        $this->assertStringContainsString('publications?committee='.$committee->slug, $html);
    }

    /**
     * ZERO IS INFORMATION WHEN IT IS MEASURED.
     *
     * Nobody has told us which member states sit on which committee, so the
     * pivot is empty. That makes "0 member states" true of every committee
     * and "0 committees" true of every member state: fourteen identical
     * zeroes that are a fact about our data rather than about the region.
     * The count is withheld and the page says why, once.
     */
    public function test_the_seat_count_is_withheld_while_nobody_has_supplied_the_seats(): void
    {
        $this->assertFalse(Committee::seatsRecorded());

        $this->get('/en/committees')
            ->assertOk()
            ->assertSee('still to be confirmed by the Secretariat')
            ->assertDontSee('Member states</dt>', false);
    }

    public function test_the_seat_count_appears_as_soon_as_one_seat_is_recorded(): void
    {
        Committee::where('is_active', true)->first()
            ->countries()->attach(Country::first(), ['role' => 'chair']);

        // The memoised answer is per-request, so a fresh request sees it.
        $this->get('/en/committees')
            ->assertOk()
            ->assertSee('Member states')
            ->assertDontSee('still to be confirmed by the Secretariat');
    }

    /**
     * The same rule from the other side: a member card must not print "0
     * committees" fourteen times when the seats have simply not been given
     * to us.
     */
    public function test_a_member_card_withholds_its_committee_count_for_the_same_reason(): void
    {
        $this->get('/en/members')->assertOk()->assertDontSee('b-member__committees', false);
    }

    public function test_the_block_renders_its_empty_state_rather_than_a_blank_section(): void
    {
        Committee::query()->delete();

        $this->get('/en/committees')
            ->assertOk()
            ->assertSee('No committees are listed yet')
            ->assertDontSee('b-committees', false);
    }

    /** Derived from member_grid, so it inherits the geometry rule too. */
    public function test_committee_card_heights_follow_their_own_content(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression('~\.b-committees \{[^}]*align-items: start~', $css);
    }
}
