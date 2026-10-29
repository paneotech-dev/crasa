<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use App\Models\User;
use App\Support\SpreadsheetCell;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The delegate list leaving the platform. Names, roles and notes are typed
 * by whoever fills the public form, so a cell that a spreadsheet would read
 * as a formula is written as text; and every export is in the audit log
 * with who took it, for which event and how many rows.
 */
class RegistrationExportSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->admin = User::factory()->administrator()->create();
        $this->meeting = Meeting::factory()->upcoming()->create(['clearance' => 'public']);
    }

    private function filing(string $name, array $attributes = []): DelegateRegistration
    {
        return DelegateRegistration::create($attributes + [
            'meeting_id' => $this->meeting->id,
            'delegate_name' => $name,
            'delegate_email' => strtolower(preg_replace('/[^a-z]/i', '', $name) ?: 'x').'@example.org',
            'arrival_on' => $this->meeting->starts_on->copy()->subDay()->toDateString(),
            'departure_on' => $this->meeting->starts_on->toDateString(),
            'status' => 'filed',
        ]);
    }

    public function test_the_helper_neutralises_every_formula_trigger_and_nothing_else(): void
    {
        foreach (['=1+1', '+27 11 000', '-2', '@SUM(A1)', "\tx", "\rx"] as $value) {
            $this->assertSame("'".$value, SpreadsheetCell::safe($value));
        }

        foreach (['Thandiwe Phiri', 'SA 172', '2026-11-03', '', null, 42] as $value) {
            $this->assertSame($value, SpreadsheetCell::safe($value));
        }
    }

    public function test_both_csv_exports_write_formulas_as_text(): void
    {
        $this->filing('=HYPERLINK("http://x.test","click")', ['notes' => '@SUM(1,1)', 'delegate_role' => '+Director']);
        $this->filing('Thandiwe Phiri', ['notes' => 'Arriving with a colleague']);

        $all = $this->actingAs($this->admin)->get('/en/admin/registrations/export.csv')->assertOk()->streamedContent();
        $one = $this->actingAs($this->admin)->get("/en/admin/meetings/{$this->meeting->slug}/delegates.csv")->assertOk()->streamedContent();

        foreach ([$all, $one] as $csv) {
            $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
            $this->assertStringContainsString("'@SUM(1,1)", $csv);
            $this->assertStringContainsString("'+Director", $csv);
            $this->assertStringContainsString('Thandiwe Phiri', $csv);
            $this->assertStringNotContainsString("'Thandiwe Phiri", $csv);
            $this->assertDoesNotMatchRegularExpression('/(^|,)"?=HYPERLINK/m', $csv);
        }
    }

    public function test_every_export_is_audited_with_who_which_event_and_how_many_rows(): void
    {
        $this->filing('Thandiwe Phiri');
        $this->filing('Kagiso Molefe');

        $this->actingAs($this->admin)->get('/en/admin/registrations/export.csv?meeting='.$this->meeting->slug)->assertOk()->streamedContent();
        $this->actingAs($this->admin)->get('/en/admin/registrations/export.xlsx')->assertOk();
        $this->actingAs($this->admin)->get("/en/admin/meetings/{$this->meeting->slug}/delegates.csv")->assertOk()->streamedContent();

        $rows = AuditLog::where('action', 'registrations_exported')->orderBy('id')->get();
        $this->assertCount(3, $rows);

        foreach ($rows as $row) {
            $this->assertSame($this->admin->id, $row->user_id);
            $this->assertSame(2, $row->changes['rows']);
        }

        $this->assertSame(['csv', 'xlsx', 'csv'], $rows->map(fn ($r) => $r->changes['format'])->all());
        $this->assertSame($this->meeting->slug, $rows[0]->changes['meeting']);
        $this->assertSame($this->meeting->id, $rows[0]->auditable_id);
        $this->assertNull($rows[1]->changes['meeting']);
        $this->assertSame($this->meeting->id, $rows[2]->auditable_id);

        foreach (['en', 'fr', 'pt'] as $locale) {
            $this->assertNotSame('audit.action_registrations_exported', __('audit.action_registrations_exported', [], $locale));
        }
    }

    public function test_a_refused_export_writes_no_audit_row(): void
    {
        $this->filing('Thandiwe Phiri');
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin/registrations/export.csv')->assertForbidden();
        $this->actingAs($viewer)->get("/en/admin/meetings/{$this->meeting->slug}/delegates.csv")->assertForbidden();

        $this->assertSame(0, AuditLog::where('action', 'registrations_exported')->count());
    }
}
