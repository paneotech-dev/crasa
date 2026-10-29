<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Instrument;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Screen 11's landing page.
 *
 * A member used to sign in and be redirected past this into the folder list,
 * so the extranet's first sentence to a national regulator was a file
 * browser.
 *
 * A dashboard is the classic place a permission layer leaks, because it
 * assembles records from several sources at once and it is tempting to trust
 * the query rather than the policy. Most of what is below is about that.
 */
class ExtranetDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
    }

    public function test_signing_in_lands_on_the_dashboard_rather_than_a_file_browser(): void
    {
        $user = User::factory()->memberStateRep(Country::first())->create();

        $this->actingAs($user)
            ->get('/en/extranet')
            ->assertRedirect('http://localhost/en/extranet/dashboard');
    }

    /**
     * The panels screen 11 draws, plus the country panels added on
     * 10 September 2026. The meetings panel became "your next meeting"
     * rather than a list of five: a dashboard that lists five meetings and
     * five documents above what the state actually owes is answering the
     * wrong question.
     */
    public function test_the_dashboard_carries_the_panels_it_is_meant_to(): void
    {
        $user = User::factory()->memberStateRep(Country::first())->create();

        $this->actingAs($user)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            // Day 3 (8c): the cards carry CRASA's headings, My return,
            // Meetings, From the Secretariat and My folders.
            ->assertSee('My return')
            ->assertSee('Meetings')
            ->assertSee('Your next meeting')
            ->assertSee('My folders')
            ->assertSee('Latest files')
            ->assertSee('Notifications')
            ->assertSee('Returns due')
            ->assertSee('From the Secretariat');
    }

    /**
     * Every panel has an empty state. A member state that has filed nothing
     * and sits on no committee should meet a sentence, not three blank
     * boxes.
     */
    public function test_every_panel_has_an_empty_state(): void
    {
        $user = User::factory()->memberStateRep(Country::first())->create();

        $this->actingAs($user)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertSee('No meetings scheduled')
            ->assertSee('Nothing announced')
            ->assertSee('Nothing filed yet')
            ->assertSee('Nothing needs you');
    }

    public function test_an_upcoming_meeting_appears_with_its_date_and_committee(): void
    {
        $committee = Committee::first();
        $user = User::factory()->memberStateRep(Country::first())->create();
        $user->committees()->attach($committee);

        Meeting::factory()->create([
            'committee_id' => $committee->id,
            'title' => 'Ninth spectrum session',
            'starts_on' => now()->addDays(9),
        ]);

        $this->actingAs($user)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertSee('Ninth spectrum session')
            ->assertSee($committee->name);
    }

    /**
     * THE LEAK THIS PAGE COULD HAVE BEEN.
     *
     * A dashboard that queried the five most recent documents and printed
     * them would print a Confidential document to anyone who signed in.
     * Every record here goes through the same Gate the page behind it uses.
     */
    public function test_a_document_above_the_readers_clearance_never_reaches_the_dashboard(): void
    {
        $instrument = Instrument::factory()->inForce()->create(['slug' => 'restricted-note']);

        $secret = Document::factory()->create([
            'documentable_type' => Instrument::class,
            'documentable_id' => $instrument->id,
            'clearance' => Clearance::Confidential,
            'original_filename' => 'board-only-annex.pdf',
            'file_path' => 'documents/board-only-annex.pdf',
        ]);
        Storage::disk('local')->put($secret->file_path, '%PDF-1.4');

        $rep = User::factory()->memberStateRep(Country::first())->create();

        // Refused at the document itself, which the policy already holds.
        $this->actingAs($rep)->get('/en/documents/'.$secret->id)->assertForbidden();

        // And it must not appear on the page that lists recent filings.
        $this->actingAs($rep)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertDontSee('board-only-annex.pdf');
    }

    /**
     * A member state representative sees their own committees' meetings.
     * A meeting of a committee they do not sit on is not theirs to plan
     * around.
     */
    public function test_a_meeting_of_another_committee_is_not_listed(): void
    {
        $committees = Committee::limit(2)->get();
        $user = User::factory()->memberStateRep(Country::first())->create();
        $user->committees()->attach($committees->first());

        Meeting::factory()->create([
            'committee_id' => $committees->last()->id,
            'title' => 'Another committee entirely',
            'starts_on' => now()->addDays(4),
        ]);

        $this->actingAs($user)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertDontSee('Another committee entirely');
    }

    public function test_the_secretariat_sees_every_committees_meetings(): void
    {
        $meeting = Meeting::factory()->create([
            'committee_id' => Committee::first()->id,
            'title' => 'Regionwide planning session',
            'starts_on' => now()->addDays(3),
        ]);

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertSee($meeting->title);
    }

    public function test_the_dashboard_is_behind_authentication(): void
    {
        $this->get('/en/extranet/dashboard')->assertRedirect();
    }

    /**
     * Clearance is carried as a label, never as colour alone. Screen 04's
     * rule for the confidentiality key applies wherever a clearance is
     * drawn, and this page draws one on every row.
     */
    public function test_a_clearance_is_labelled_and_not_only_coloured(): void
    {
        $instrument = Instrument::factory()->inForce()->create(['slug' => 'open-note']);
        $document = Document::factory()->create([
            'documentable_type' => Instrument::class,
            'documentable_id' => $instrument->id,
            'clearance' => Clearance::Public,
            'original_filename' => 'public-guidance.pdf',
            'file_path' => 'documents/public-guidance.pdf',
        ]);
        Storage::disk('local')->put($document->file_path, '%PDF-1.4');

        $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->assertSee('public-guidance.pdf')
            ->assertSee(Clearance::Public->label());
    }
}
