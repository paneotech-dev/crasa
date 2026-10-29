<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Enums\SubmissionStatus;
use App\Models\Announcement;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Indicator;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The member area pages redrawn on Day 3 (8c): the home page per role, the
 * notices from the Secretariat and data submission. Each renders for its
 * roles with its layout classes, refuses the others, and never answers 500.
 */
class MemberAreaDay3Test extends TestCase
{
    use RefreshDatabase;

    private User $rep;

    private User $committee;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(IndicatorSeeder::class);

        $this->rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create(['name' => 'Kagiso Molefe']);
        $this->committee = User::factory()->committeeMember(Country::where('iso_code', 'zm')->sole())->create(['name' => 'Mutale Banda']);
        $this->committee->committees()->attach(Committee::first()->id, ['role' => 'member', 'is_sample' => true]);
        $this->viewer = User::factory()->viewer()->create();
    }

    private function notice(string $slug, string $title, array $overrides = []): Announcement
    {
        $a = Announcement::create(array_merge([
            'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDays(2),
        ], $overrides));
        $a->setTranslation('title', 'en', $title);
        $a->setTranslation('body', 'en', 'The Secretariat asks every member state to file its annual return by the end of March.');

        return $a;
    }

    /* Home page ------------------------------------------------------------ */

    public function test_the_representative_home_has_two_columns_my_return_and_a_plain_greeting(): void
    {
        $html = $this->actingAs($this->rep)->get('/en/extranet/dashboard')->assertOk()->getContent();

        foreach (['class="mh"', 'class="mh-cols"', 'class="mh-main"', 'class="mh-side"', 'data-block="return"', 'data-block="next"', 'data-block="documents"', 'mh-bar', 'css/member-home.css'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        $this->assertStringContainsString('Kagiso Molefe', $html);
        $this->assertStringContainsString('My return', $html);
        $this->assertStringContainsString('Quick actions', $html);
        $this->assertStringNotContainsString('Welcome', $html);
    }

    public function test_my_return_names_the_next_field_and_links_to_it(): void
    {
        $submission = Submission::create([
            'country_id' => $this->rep->country_id, 'submitted_by' => $this->rep->id,
            'period' => (string) now()->year, 'status' => SubmissionStatus::Draft->value, 'is_sample' => false,
        ]);
        $first = Indicator::active()->orderBy('display_order')->first();

        $html = $this->actingAs($this->rep)->get('/en/extranet/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('/submissions/'.$submission->id.'#v-'.$first->id, $html);
        $this->assertStringContainsString(e($first->name), $html);
        $this->assertStringContainsString('aria-valuenow="0"', $html);
    }

    public function test_the_committee_member_home_has_committees_and_no_return(): void
    {
        $html = $this->actingAs($this->committee)->get('/en/extranet/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('class="mh-cols"', $html);
        $this->assertStringContainsString('data-block="committees"', $html);
        $this->assertStringNotContainsString('data-block="return"', $html);
        $this->assertStringContainsString('Mutale Banda', $html);
    }

    public function test_the_home_page_refuses_a_viewer_and_a_guest(): void
    {
        $this->actingAs($this->viewer)->get('/en/extranet/dashboard')->assertForbidden();
        auth()->logout();
        $this->get('/en/extranet/dashboard')->assertRedirect();
    }

    public function test_the_viewer_dashboard_has_no_eyebrow(): void
    {
        $html = $this->actingAs($this->viewer)->get('/en/my-dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('my-dash__who', $html);
        $this->assertStringNotContainsString('class="u-eyebrow"', $html);
    }

    public function test_the_notification_fallback_lists_the_readers_notifications(): void
    {
        $this->rep->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\SubmissionStateChanged',
            'data' => ['type' => 'submission', 'period' => '2025', 'status' => 'returned'],
        ]);

        $html = $this->actingAs($this->rep)->get('/en/extranet/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('Return 2025: Returned', $html);
        $this->assertStringContainsString('Unread', $html);
    }

    /* From the Secretariat --------------------------------------------------- */

    public function test_notices_are_grouped_by_month_with_type_summary_attachments_and_read_state(): void
    {
        $a = $this->notice('returns-window', 'Annual returns window open');
        Document::create([
            'documentable_type' => Announcement::class, 'documentable_id' => $a->id,
            'file_path' => 'x/guide.pdf', 'original_filename' => 'return-guide.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 2048, 'clearance' => Clearance::Public->value, 'is_sample' => false,
        ]);

        $html = $this->actingAs($this->rep)->get('/en/announcements')->assertOk()->getContent();
        foreach (['mh-month', 'mh-notice', 'mh-type', 'mh-notice__summary', 'return-guide.pdf', 'data-read="false"', '>New<', 'All members'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        $this->assertStringContainsString(now()->subDays(2)->isoFormat('MMMM YYYY'), $html);

        $this->actingAs($this->rep)->get('/en/announcements/returns-window')->assertOk();
        $html = $this->actingAs($this->rep)->get('/en/announcements')->assertOk()->getContent();
        $this->assertStringContainsString('data-read="true"', $html);
        $this->assertStringNotContainsString('>New<', $html);
    }

    public function test_a_committee_notice_is_badged_and_hidden_from_a_state_without_the_seat(): void
    {
        $committee = Committee::first();
        $this->notice('committee-only', 'Working papers for the session', ['committee_id' => $committee->id]);

        $this->actingAs($this->committee)->get('/en/announcements')->assertOk()->assertSee($committee->name)->assertSee('Working papers for the session');
        $this->actingAs($this->rep)->get('/en/announcements')->assertOk()->assertDontSee('Working papers for the session');
        $this->actingAs($this->rep)->get('/en/announcements/committee-only')->assertNotFound();
    }

    public function test_notices_refuse_a_viewer(): void
    {
        $this->actingAs($this->viewer)->get('/en/announcements')->assertForbidden();
    }

    /* Data submission -------------------------------------------------------- */

    public function test_data_submission_has_the_period_picker_and_the_returns_as_cards(): void
    {
        Submission::create(['country_id' => $this->rep->country_id, 'submitted_by' => $this->rep->id, 'period' => '2024', 'status' => 'validated', 'is_sample' => false]);
        Submission::create(['country_id' => $this->rep->country_id, 'submitted_by' => $this->rep->id, 'period' => '2025', 'status' => 'draft', 'is_sample' => false]);

        $html = $this->actingAs($this->rep)->get('/en/submissions')->assertOk()->getContent();
        foreach (['id="filter-period"', 'class="mh-returns"', 'data-label=', 'Return for 2024', 'Return for 2025'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }

        $html = $this->actingAs($this->rep)->get('/en/submissions?period=2025')->assertOk()->getContent();
        $this->assertStringContainsString('Return for 2025', $html);
        $this->assertStringNotContainsString('Return for 2024', $html);

        $this->actingAs($this->rep)->get('/en/submissions?period=not-a-period')->assertOk();
    }

    public function test_data_submission_refuses_a_committee_member_and_a_viewer(): void
    {
        $this->actingAs($this->committee)->get('/en/submissions')->assertForbidden();
        $this->actingAs($this->viewer)->get('/en/submissions')->assertForbidden();
    }

    public function test_another_states_return_is_not_listed(): void
    {
        $mz = Country::where('iso_code', 'mz')->sole();
        Submission::withoutGlobalScopes()->create(['country_id' => $mz->id, 'period' => '2023', 'status' => 'draft', 'is_sample' => false]);

        $this->actingAs($this->rep)->get('/en/submissions')->assertOk()->assertDontSee('Return for 2023');
    }
}
