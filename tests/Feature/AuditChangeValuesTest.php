<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RoleDefinition;
use App\Models\User;
use App\Support\AuditChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The audit change tables show values a reader recognises: roles by name,
 * related records by name, dates in one format and one zone.
 */
class AuditChangeValuesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->admin = User::factory()->administrator()->create(['name' => 'Audit Reader']);
    }

    private function observerRole(): RoleDefinition
    {
        return RoleDefinition::create(['key' => 'workshop-observer', 'name_en' => 'Workshop observer', 'name_fr' => 'Observateur d\'atelier', 'name_pt' => 'Observador do seminário', 'base_role' => 'viewer', 'is_built_in' => false]);
    }

    /** @return array<string, array{before: ?string, after: ?string}> */
    private function rowsOf(AuditLog $entry): array
    {
        return collect(AuditChanges::rows($entry))->mapWithKeys(fn ($r) => [$r['field'] => ['before' => $r['before'], 'after' => $r['after']]])->all();
    }

    public function test_a_role_change_shows_role_names_not_keys(): void
    {
        $this->observerRole();
        $subject = User::factory()->viewer()->create();
        $entry = AuditLog::record(AuditLog::ROLE_CHANGED, $subject, ['from' => 'viewer', 'to' => 'workshop-observer'], $this->admin);

        $row = AuditChanges::rows($entry)[0];
        $this->assertSame('Viewer', $row['before']);
        $this->assertSame('Workshop observer', $row['after']);

        $gone = AuditLog::record(AuditLog::ROLE_CHANGED, $subject, ['from' => 'workshop-observer', 'to' => 'retired-role'], $this->admin);
        $this->assertSame('retired-role', AuditChanges::rows($gone)[0]['after']);

        $this->actingAs($this->admin)->get('/fr/admin/audit')->assertOk()
            ->assertSee('Observateur d&#039;atelier', false)
            ->assertSee('Lecteur')
            ->assertDontSee('>workshop-observer<', false);
        $this->actingAs($this->admin)->get('/pt/admin/audit')->assertOk()->assertSee('Observador do seminário');
    }
}
