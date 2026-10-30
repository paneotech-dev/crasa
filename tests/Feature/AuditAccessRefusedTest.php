<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Submission;
use App\Models\User;
use App\Support\AuditChain;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Day 3: auditdeny. A signed-in user refused by the server is written to the
 * audit log as access_refused: a 403 on a screen, and a 404 that hides
 * another member state's record or a file above the reader's clearance. One
 * entry per user, route and target per minute; guests are not recorded;
 * nothing from the query string or the body is kept. The audit screen and
 * its CSV show the entry in plain words in three languages.
 */
class AuditAccessRefusedTest extends TestCase
{
    use RefreshDatabase;

    private Country $botswana;

    private Country $malawi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->botswana = Country::where('iso_code', 'bw')->sole();
        $this->malawi = Country::where('iso_code', 'mw')->sole();
    }

    private function refusals()
    {
        return AuditLog::query()->where('action', AuditLog::ACCESS_REFUSED)->orderBy('id')->get();
    }

    private function malawiSubmission(string $period = '2025'): Submission
    {
        return Submission::create(['country_id' => $this->malawi->id, 'period' => $period, 'status' => 'draft', 'is_sample' => false]);
    }

    public function test_a_viewer_refused_an_admin_screen_is_recorded_once(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin/users')->assertForbidden();

        $entries = $this->refusals();
        $this->assertCount(1, $entries);
        $entry = $entries->first();

        $this->assertSame($viewer->id, $entry->user_id);
        $this->assertSame('127.0.0.1', $entry->ip_address);
        $this->assertNotNull($entry->created_at);
        $this->assertSame('forbidden', $entry->changes['reason']);
        $this->assertSame(403, $entry->changes['status']);
        $this->assertSame('GET', $entry->changes['method']);
        $this->assertSame('admin.users', $entry->changes['route']);
        $this->assertSame('/en/admin/users', $entry->changes['path']);
        $this->assertArrayHasKey('ability', $entry->changes);

        // The chain applies: the row is hashed and the head moved to it.
        $this->assertNotNull($entry->row_hash);
        $this->assertSame($entry->row_hash, DB::table(AuditChain::HEAD)->where('id', 1)->value('hash'));
    }

    public function test_a_policy_refusal_names_the_ability_and_the_record_type(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin/audit')->assertForbidden();

        $entry = $this->refusals()->sole();
        $this->assertSame('viewAny', $entry->changes['ability']);
        $this->assertSame(AuditLog::class, $entry->auditable_type);
        $this->assertNull($entry->auditable_id);
    }

    public function test_a_rep_opening_another_countrys_submission_is_recorded_and_still_sees_a_404(): void
    {
        $rep = User::factory()->memberStateRep($this->botswana)->create();
        $submission = $this->malawiSubmission();

        $response = $this->actingAs($rep)->get('/en/submissions/'.$submission->id)->assertNotFound();
        // The page says nothing about the record or the log.
        $this->assertStringNotContainsString('Malawi', $response->getContent());

        $entry = $this->refusals()->sole();
        $this->assertSame($rep->id, $entry->user_id);
        $this->assertSame(Submission::class, $entry->auditable_type);
        $this->assertSame($submission->id, $entry->auditable_id);
        $this->assertSame('other_member_state', $entry->changes['reason']);
        $this->assertSame(404, $entry->changes['status']);
        $this->assertSame('submissions.edit', $entry->changes['route']);
        $this->assertSame('/en/submissions/'.$submission->id, $entry->changes['path']);
    }

    public function test_a_rep_opening_another_countrys_folder_is_recorded(): void
    {
        $rep = User::factory()->memberStateRep($this->botswana)->create();
        $folder = Folder::create(['name' => 'Malawi returns', 'slug' => 'malawi-returns', 'country_id' => $this->malawi->id, 'clearance' => 'member']);

        $this->actingAs($rep)->get('/en/extranet/folders/'.$folder->id)->assertNotFound();

        $entry = $this->refusals()->sole();
        $this->assertSame(Folder::class, $entry->auditable_type);
        $this->assertSame($folder->id, $entry->auditable_id);
        $this->assertSame('other_member_state', $entry->changes['reason']);
        $this->assertSame('extranet.folder', $entry->changes['route']);
    }

    public function test_a_record_that_does_not_exist_is_not_a_refusal(): void
    {
        $rep = User::factory()->memberStateRep($this->botswana)->create();

        $this->actingAs($rep)->get('/en/submissions/999999')->assertNotFound();
        $this->actingAs($rep)->get('/en/no-such-page-here')->assertNotFound();

        $this->assertCount(0, $this->refusals());
    }

    public function test_the_same_refusal_is_written_once_a_minute(): void
    {
        $viewer = User::factory()->viewer()->create();
        $rep = User::factory()->memberStateRep($this->botswana)->create();
        $submission = $this->malawiSubmission();

        foreach (range(1, 5) as $i) {
            $this->actingAs($viewer)->get('/en/admin/users')->assertForbidden();
            $this->actingAs($rep)->get('/en/submissions/'.$submission->id)->assertNotFound();
        }
        $this->assertCount(2, $this->refusals());

        // Another target is another entry.
        $other = $this->malawiSubmission('2024');
        $this->actingAs($rep)->get('/en/submissions/'.$other->id)->assertNotFound();
        $this->assertCount(3, $this->refusals());

        // A minute later the same attempt is written again.
        $this->travel(61)->seconds();
        $this->actingAs($viewer)->get('/en/admin/users')->assertForbidden();
        $this->assertCount(4, $this->refusals());
    }

    public function test_guests_are_not_recorded(): void
    {
        $submission = $this->malawiSubmission();

        $this->get('/en/admin/users')->assertRedirect();
        $this->get('/en/submissions/'.$submission->id)->assertRedirect();
        $this->get('/en/admin/audit')->assertRedirect();

        $this->assertCount(0, $this->refusals());
    }

    public function test_nothing_sensitive_is_stored(): void
    {
        $viewer = User::factory()->viewer()->create();
        $rep = User::factory()->memberStateRep($this->botswana)->create();

        $this->actingAs($viewer)->post('/en/admin/users/invite?email=jane.banda%40example.org&token=abc123secret', [
            'name' => 'Jane Banda', 'email' => 'jane.banda@example.org', 'password' => 'Sup3rSecretPass!', '_token' => 'csrf-value',
        ])->assertForbidden();

        // A share link to a file above the reader's clearance: a 404, and
        // the link's token is not written down.
        $folder = Folder::withoutGlobalScopes()->create(['name' => 'Secretariat papers', 'slug' => 'secretariat-papers', 'clearance' => Clearance::Secretariat]);
        $document = Document::create([
            'documentable_type' => Folder::class, 'documentable_id' => $folder->id, 'folder_id' => $folder->id,
            'file_path' => 'extranet/'.$folder->id.'/note.pdf', 'original_filename' => 'note.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 13, 'clearance' => Clearance::Secretariat,
        ]);
        $token = $document->shareToken();
        $this->actingAs($rep)->get('/en/extranet/shared/'.$token)->assertNotFound();

        $entries = $this->refusals();
        $this->assertCount(2, $entries);

        $stored = $entries->map(fn (AuditLog $e) => json_encode($e->getAttributes()))->implode("\n");
        foreach (['jane', 'example.org', 'Sup3rSecretPass', 'csrf-value', 'abc123secret', $token, 'password', 'email='] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $stored, $needle);
        }

        $this->assertSame('/en/admin/users/invite', $entries[0]->changes['path']);
        $this->assertSame('POST', $entries[0]->changes['method']);
        $this->assertSame('/en/extranet/shared/{token}', $entries[1]->changes['path']);
        $this->assertSame('not_cleared', $entries[1]->changes['reason']);
        $this->assertSame(Document::class, $entries[1]->auditable_type);
        $this->assertSame($document->id, $entries[1]->auditable_id);
    }

    public function test_the_audit_screen_and_csv_show_the_refusal_in_plain_words(): void
    {
        $viewer = User::factory()->viewer()->create(['name' => 'Tendai Moyo']);
        $this->actingAs($viewer)->get('/en/admin/users')->assertForbidden();

        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/en/admin/audit')->assertOk()
            ->assertSee('<option value="access_refused"', false)
            ->assertSee('Access refused')
            ->assertSee('Attempted')
            ->assertSee('GET /en/admin/users')
            ->assertSee('Not allowed for this account (refused)');

        // Filterable by action like the others.
        $this->actingAs($admin)->get('/en/admin/audit?action=access_refused')->assertOk()
            ->assertSee('Tendai Moyo')
            ->assertSee('GET /en/admin/users');

        $csv = $this->actingAs($admin)->get('/en/admin/audit/export.csv?action=access_refused')->assertOk()->streamedContent();
        $this->assertStringContainsString('Access refused', $csv);
        $this->assertStringContainsString('GET /en/admin/users', $csv);
        $this->assertStringContainsString('Tendai Moyo', $csv);
        $this->assertStringContainsString('admin.users', $csv);

        foreach (['fr' => ['Accès refusé', 'Tentative', 'Non autorisé pour ce compte'], 'pt' => ['Acesso recusado', 'Tentativa', 'Não permitido para esta conta']] as $locale => $words) {
            $html = $this->actingAs($admin)->get("/{$locale}/admin/audit?action=access_refused")->assertOk()->getContent();
            foreach ($words as $word) {
                $this->assertStringContainsString(e($word), $html, $locale.': '.$word);
            }
        }
    }

    public function test_the_strings_exist_in_three_languages(): void
    {
        $keys = ['audit.action_access_refused', 'd3_auditdeny.field_attempted', 'd3_auditdeny.field_screen', 'd3_auditdeny.field_ability',
            'd3_auditdeny.field_reason', 'd3_auditdeny.field_status', 'd3_auditdeny.reason_forbidden',
            'd3_auditdeny.reason_other_member_state', 'd3_auditdeny.reason_not_cleared'];

        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach ($keys as $key) {
                $this->assertTrue(\Illuminate\Support\Facades\Lang::hasForLocale($key, $locale), "{$locale}: {$key}");
            }
        }
        $this->assertNotSame(__('audit.action_access_refused', [], 'en'), __('audit.action_access_refused', [], 'fr'));
        $this->assertNotSame(__('audit.action_access_refused', [], 'en'), __('audit.action_access_refused', [], 'pt'));
    }
}
