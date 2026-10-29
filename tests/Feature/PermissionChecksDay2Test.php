<?php

namespace Tests\Feature;

use App\Enums\CommitteeRole;
use App\Enums\SubmissionStatus;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Folder;
use App\Models\Instrument;
use App\Models\NewsItem;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The permission checks PM-01 to PM-06 and PM-08, re-run on 29 October 2026
 * on the real routes with one account per role. A failure here is Critical.
 */
class PermissionChecksDay2Test extends TestCase
{
    use RefreshDatabase;

    private Country $botswana;

    private Country $malawi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->botswana = Country::where('iso_code', 'bw')->sole();
        $this->malawi = Country::where('iso_code', 'mw')->sole();
    }

    private const FRIENDLY = 'This page is not open to your account';

    /** PM-01: a viewer is refused the back office and the member area, kindly. */
    public function test_pm01_a_viewer_gets_a_friendly_refusal_on_admin_and_member_urls(): void
    {
        $viewer = User::factory()->viewer()->create();

        $urls = [
            '/en/admin', '/en/admin/users', '/en/admin/data/news', '/en/admin/settings', '/en/admin/audit',
            '/en/extranet', '/en/extranet/dashboard', '/en/extranet/folders', '/en/extranet/calendar',
            '/en/extranet/search?q=a', '/en/register', '/en/directory', '/en/announcements', '/en/indicators',
        ];

        foreach ($urls as $url) {
            $this->actingAs($viewer)->get($url)->assertForbidden()->assertSee(self::FRIENDLY);
        }

        $this->actingAs($viewer)->get('/fr/extranet/dashboard')->assertForbidden()->assertSee(__('errors.403_title', [], 'fr'));
        $this->actingAs($viewer)->get('/pt/extranet/dashboard')->assertForbidden()->assertSee(__('errors.403_title', [], 'pt'));

        // What a viewer may open still opens.
        $this->actingAs($viewer)->get('/en/account')->assertOk();
    }

    /** PM-02: a representative of one member state cannot open another's submission or contribution. */
    public function test_pm02_a_representative_cannot_open_another_member_states_records(): void
    {
        $botswanaRep = User::factory()->memberStateRep($this->botswana)->create();
        $malawiRep = User::factory()->memberStateRep($this->malawi)->create();

        $theirs = Submission::create([
            'country_id' => $this->malawi->id, 'submitted_by' => $malawiRep->id,
            'period' => '2025', 'status' => SubmissionStatus::Draft->value, 'is_sample' => false,
        ]);
        $this->assertContains($this->actingAs($botswanaRep)->get("/en/submissions/{$theirs->id}")->getStatusCode(), [403, 404]);
        $this->actingAs($malawiRep)->get("/en/submissions/{$theirs->id}")->assertOk();

        $contribution = Instrument::factory()->draft()->create([
            'country_id' => $this->malawi->id, 'contributed_by' => $malawiRep->id,
        ]);
        $this->assertContains($this->actingAs($botswanaRep)->get('/en/contributions/'.$contribution->slug)->getStatusCode(), [403, 404]);
        // Not validated, so not in the register for anybody.
        $this->actingAs($botswanaRep)->get('/en/register/'.$contribution->slug)->assertNotFound();

        $folder = Folder::create(['name' => 'Malawi returns', 'slug' => 'malawi-returns', 'country_id' => $this->malawi->id, 'clearance' => 'member']);
        $this->assertContains($this->actingAs($botswanaRep)->get('/en/extranet/folders/'.$folder->getRouteKey())->getStatusCode(), [403, 404]);
    }

    /** PM-03: a committee member cannot open another committee's workspace. */
    public function test_pm03_a_committee_member_cannot_open_another_committees_workspace(): void
    {
        $member = User::factory()->committeeMember($this->botswana)->create();
        [$mine, $theirs] = Committee::orderBy('id')->limit(2)->get()->all();
        $member->committees()->attach($mine->id, ['role' => CommitteeRole::Member->value, 'is_sample' => false]);

        $this->actingAs($member)->get('/en/extranet/committees/'.$mine->slug)->assertOk();
        $this->actingAs($member)->get('/en/extranet/committees/'.$theirs->slug)->assertForbidden()->assertSee(self::FRIENDLY);
    }

    /** PM-04: an editor is refused users, roles, settings and the audit log. */
    public function test_pm04_an_editor_is_refused_users_roles_settings_and_audit(): void
    {
        $editor = User::factory()->editor()->create();
        $someone = User::factory()->viewer()->create();

        foreach (['/en/admin/users', '/en/admin/users/invite', '/en/admin/settings', '/en/admin/audit'] as $url) {
            $this->actingAs($editor)->get($url)->assertForbidden()->assertSee(self::FRIENDLY);
        }

        // Roles are changed on the user: refused too, and nothing changes.
        $this->actingAs($editor)->put("/en/admin/users/{$someone->id}/role", ['role' => 'administrator'])->assertForbidden();
        $this->assertSame('viewer', $someone->fresh()->role->value);
    }

    /** PM-05: an editor creates, edits and deletes a news item. */
    public function test_pm05_an_editor_creates_edits_and_deletes_a_news_item(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post('/en/admin/data/news', [
            'category' => 'news', 'status' => 'draft',
            'en' => ['title' => 'Workshop news item', 'summary' => 'A summary.', 'body' => 'A body.'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $item = NewsItem::sole();

        $this->actingAs($editor)->put("/en/admin/data/news/{$item->id}", [
            // Day 2 (F2): an editor keeps it a draft; publishing goes
            // through a moderator (ApprovalWorkflowTest).
            'category' => 'press_release', 'status' => 'draft', 'published_at' => '2026-10-28',
            'en' => ['title' => 'Workshop news item, edited', 'summary' => 'A summary.', 'body' => 'A body.'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('press_release', $item->fresh()->category->value);

        $this->actingAs($editor)->delete("/en/admin/data/news/{$item->id}")->assertRedirect();
        $this->assertSoftDeleted('news_items', ['id' => $item->id]);
    }

    /**
     * PM-06: an administrator opens every sidebar item without an error, on
     * an empty database, and an empty list says so.
     */
    public function test_pm06_an_administrator_opens_every_sidebar_item(): void
    {
        $admin = User::factory()->administrator()->create();
        $html = $this->actingAs($admin)->get('/en/admin')->assertOk()->getContent();

        preg_match('/<div class="a-side__scroll">(.*?)<\/div>\s*@?\s*(<div class="a-side__me"|<\/nav>)/s', $html, $side);
        preg_match_all('/class="a-side__link[^"]*"\s*href="([^"]+)"/', $side[1] ?? $html, $m);
        $links = array_unique($m[1]);
        $this->assertGreaterThan(15, count($links), 'The sidebar was not found.');

        foreach ($links as $href) {
            $path = parse_url(html_entity_decode($href), PHP_URL_PATH).(($q = parse_url(html_entity_decode($href), PHP_URL_QUERY)) ? '?'.$q : '');
            $response = $this->actingAs($admin)->get($path);
            $this->assertSame(200, $response->getStatusCode(), $path.' did not open.');
        }

        // An empty list carries an empty state rather than a bare table.
        $this->actingAs($admin)->get('/en/admin/data/news')->assertOk()->assertSee('a-empty', false);
    }

    /** PM-08: a direct POST to a forbidden action is refused by the server. */
    public function test_pm08_direct_writes_to_forbidden_actions_are_refused(): void
    {
        $viewer = User::factory()->viewer()->create();
        $editor = User::factory()->editor()->create();
        $botswanaRep = User::factory()->memberStateRep($this->botswana)->create();
        $malawiFolder = Folder::create(['name' => 'Malawi returns', 'slug' => 'malawi-returns-2', 'country_id' => $this->malawi->id, 'clearance' => 'member']);

        $this->actingAs($viewer)->post('/en/admin/data/news', ['category' => 'news', 'status' => 'draft', 'en' => ['title' => 'x']])->assertForbidden();
        $this->actingAs($botswanaRep)->post('/en/admin/data/news', ['category' => 'news', 'status' => 'draft', 'en' => ['title' => 'x']])->assertForbidden();
        $this->assertSame(0, NewsItem::count());

        $this->actingAs($editor)->post('/en/admin/users/invite', ['name' => 'X', 'email' => 'x@example.org', 'role' => 'administrator'])->assertForbidden();
        $this->actingAs($editor)->put('/en/admin/settings', [])->assertForbidden();
        $this->assertSame(0, User::where('email', 'x@example.org')->count());

        $this->actingAs($viewer)->post('/en/directory', ['name' => 'X'])->assertForbidden();

        $upload = $this->actingAs($botswanaRep)->post('/en/extranet/folders/'.$malawiFolder->getRouteKey().'/upload', []);
        $this->assertContains($upload->getStatusCode(), [403, 404]);
    }
}
