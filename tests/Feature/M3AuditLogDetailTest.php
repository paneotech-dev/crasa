<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 1g: every audit entry shows field, before, after, actor, time and the
 * object; the list filters by user, object and date; the filtered list
 * exports to CSV, readable by ReadAuditLog only and audited itself.
 */
class M3AuditLogDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(PublicPagesSeeder::class);

        $this->admin = User::factory()->administrator()->create(['name' => 'Audit Reader']);
    }

    private function committee(): Committee
    {
        return Committee::where('slug', 'spectrum-and-frequency-management')->sole();
    }

    public function test_a_generic_resource_update_stores_before_and_after_and_the_screen_shows_them(): void
    {
        $committee = $this->committee();
        $oldName = $committee->name;

        $this->actingAs($this->admin)->put("/en/admin/data/committees/{$committee->id}", [
            'name' => 'Spectrum Committee renamed',
            'sector_id' => $committee->sector_id,
            'en' => ['mandate' => 'A new mandate in English.'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entries = AuditLog::where('auditable_type', Committee::class)->where('auditable_id', $committee->id)
            ->where('action', AuditLog::UPDATED)->get();
        $columns = $entries->first(fn ($e) => isset($e->changes['name']));
        $this->assertSame(['from' => $oldName, 'to' => 'Spectrum Committee renamed'], $columns->changes['name']);
        // The translation moved in the same save and has its own row now.
        $this->assertTrue($entries->contains(fn ($e) => ($e->changes['mandate.en']['to'] ?? null) === 'A new mandate in English.'));

        $html = $this->actingAs($this->admin)->get('/en/admin/audit')->assertOk()->getContent();
        foreach (['class="a-auditdiff"', '>Before<', '>After<', $oldName, 'Spectrum Committee renamed', 'Audit Reader', 'Mandate (English)',
            'href="'.route('admin.resources.edit', ['locale' => 'en', 'resource' => 'committees', 'id' => $committee->id]).'"'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
    }

    public function test_the_user_edit_stores_before_and_after(): void
    {
        $target = User::factory()->editor()->create(['name' => 'Old Name', 'email' => 'old@example.org']);

        $this->actingAs($this->admin)->put('/en/admin/users/'.$target->id, [
            'name' => 'New Name', 'email' => 'new@example.org',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entry = AuditLog::where('auditable_type', User::class)->where('auditable_id', $target->id)
            ->where('action', AuditLog::UPDATED)->latest('id')->firstOrFail();
        $this->assertSame(['from' => 'Old Name', 'to' => 'New Name'], $entry->changes['name']);
        $this->assertSame(['from' => 'old@example.org', 'to' => 'new@example.org'], $entry->changes['email']);

        $this->actingAs($this->admin)->get('/en/admin/audit?type=User&object='.$target->id)->assertOk()
            ->assertSee('old@example.org')->assertSee('new@example.org')
            ->assertSee('href="'.route('admin.users.show', ['locale' => 'en', 'user' => $target->id]).'"', false);
    }

    public function test_secrets_are_masked_long_values_shortened_and_a_deleted_object_says_so(): void
    {
        $target = User::factory()->editor()->create();
        AuditLog::record(AuditLog::UPDATED, $target, [
            'password' => ['from' => 'old-plain-secret', 'to' => 'new-plain-secret'],
            'two_factor_secret' => ['from' => null, 'to' => 'TOTPSECRETVALUE'],
            'invitation_token' => ['from' => 'tok-abc-123', 'to' => null],
            'bio' => ['from' => null, 'to' => str_repeat('Long paragraph text. ', 20).'END-OF-TEXT'],
        ], $this->admin);

        $gone = new Country(['name' => 'Gone']);
        $gone->id = 999999;
        AuditLog::record(AuditLog::DELETED, $gone, ['attributes' => ['name' => 'Gone']], $this->admin);

        $html = $this->actingAs($this->admin)->get('/en/admin/audit')->assertOk()->getContent();
        foreach (['old-plain-secret', 'new-plain-secret', 'TOTPSECRETVALUE', 'tok-abc-123'] as $secret) {
            $this->assertStringNotContainsString($secret, $html, $secret);
        }
        $this->assertStringContainsString('Hidden', $html);
        $this->assertStringContainsString('<details class="a-auditdiff__long">', $html);
        $this->assertStringContainsString('Show in full', $html);
        $this->assertStringContainsString('END-OF-TEXT', $html);
        $this->assertStringContainsString('deleted', $html);
    }

    public function test_the_filters_narrow_by_user_object_and_date(): void
    {
        $other = User::factory()->administrator()->create(['name' => 'Someone Else']);
        $committee = $this->committee();
        AuditLog::record(AuditLog::UPDATED, $committee, ['name' => ['from' => 'A-unique-before', 'to' => 'B']], $this->admin);
        AuditLog::record(AuditLog::UPDATED, $other, ['name' => ['from' => 'C-unique-before', 'to' => 'D']], $other);
        (new AuditLog)->forceFill(['user_id' => $other->id, 'action' => AuditLog::UPDATED, 'auditable_type' => Committee::class,
            'auditable_id' => $committee->id, 'changes' => ['name' => ['from' => 'Old-dated', 'to' => 'E']], 'created_at' => now()->subYear()])->save();

        $this->actingAs($this->admin)->get('/en/admin/audit?user='.$this->admin->id)->assertOk()
            ->assertSee('A-unique-before')->assertDontSee('C-unique-before');

        $this->actingAs($this->admin)->get('/en/admin/audit?type=Committee&object='.urlencode('Spectrum'))->assertOk()
            ->assertSee('A-unique-before')->assertDontSee('C-unique-before');

        $this->actingAs($this->admin)->get('/en/admin/audit?object=Someone')->assertOk()
            ->assertSee('C-unique-before')->assertDontSee('A-unique-before');

        $this->actingAs($this->admin)->get('/en/admin/audit?from='.now()->subDay()->format('Y-m-d').'&to='.now()->format('Y-m-d'))->assertOk()
            ->assertSee('A-unique-before')->assertDontSee('Old-dated');

        $this->actingAs($this->admin)->get('/en/admin/audit?to='.now()->subMonths(6)->format('Y-m-d'))->assertOk()
            ->assertSee('Old-dated')->assertDontSee('A-unique-before');

        // A typo in a date is ignored, not a 500.
        $this->actingAs($this->admin)->get('/en/admin/audit?from=31-31-2026&to=nonsense')->assertOk();
    }

    public function test_the_export_is_the_filtered_list_with_safe_cells_and_is_audited(): void
    {
        $committee = $this->committee();
        AuditLog::record(AuditLog::UPDATED, $committee, ['name' => ['from' => '=HYPERLINK("x")', 'to' => 'Plain after']], $this->admin);
        AuditLog::record(AuditLog::UPDATED, $this->admin, ['password' => ['from' => 'p1-secret', 'to' => 'p2-secret']], $this->admin);

        $response = $this->actingAs($this->admin)->get('/en/admin/audit/export.csv?type=Committee');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Time,User,Action,"Object type","Object id",Object,Field,Before,After,Address', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Plain after', $csv);
        $this->assertStringNotContainsString('p1-secret', $csv);

        $exported = AuditLog::where('action', 'audit_exported')->sole();
        $this->assertSame($this->admin->id, $exported->user_id);
        $this->assertSame(['type' => 'Committee'], $exported->changes['filters']);

        $this->actingAs($this->admin)->get('/en/admin/audit')->assertOk()->assertSee('Exported the audit log');
    }

    public function test_only_readers_of_the_audit_log_may_open_or_export_it(): void
    {
        $auditor = User::factory()->auditor()->create();
        $this->actingAs($auditor)->get('/en/admin/audit')->assertOk()->assertSee('Export to CSV');
        $this->actingAs($auditor)->get('/en/admin/audit/export.csv')->assertOk();

        foreach ([User::factory()->editor()->create(), User::factory()->moderator()->create()] as $user) {
            $this->actingAs($user)->get('/en/admin/audit')->assertForbidden();
            $this->actingAs($user)->get('/en/admin/audit/export.csv')->assertForbidden();
        }

        $viewer = User::factory()->viewer()->create();
        $status = $this->actingAs($viewer)->get('/en/admin/audit/export.csv')->getStatusCode();
        $this->assertContains($status, [403, 404]);

        auth()->logout();
        $this->get('/en/admin/audit/export.csv')->assertRedirect();
    }

    public function test_the_labels_exist_in_french_and_portuguese(): void
    {
        AuditLog::record(AuditLog::UPDATED, $this->committee(), ['name' => ['from' => 'x', 'to' => 'y']], $this->admin);

        $this->actingAs($this->admin)->get('/fr/admin/audit')->assertOk()->assertSee('Avant')->assertSee('Après')->assertSee('Exporter en CSV');
        $this->actingAs($this->admin)->get('/pt/admin/audit')->assertOk()->assertSee('Antes')->assertSee('Depois')->assertSee('Exportar para CSV');
    }
}
