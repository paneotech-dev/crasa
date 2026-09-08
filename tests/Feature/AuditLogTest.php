<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Authority;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    /* Immutability --------------------------------------------------------- */

    public function test_an_audit_row_cannot_be_updated(): void
    {
        $entry = AuditLog::record(AuditLog::LOGIN);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit rows cannot be updated.');

        $entry->update(['action' => 'something_else']);
    }

    public function test_an_audit_row_cannot_be_deleted(): void
    {
        $entry = AuditLog::record(AuditLog::LOGIN);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Audit rows cannot be deleted.');

        $entry->delete();
    }

    public function test_a_saved_audit_row_cannot_be_re_saved_with_changes(): void
    {
        $entry = AuditLog::record(AuditLog::LOGIN);
        $entry->action = 'tampered';

        $this->expectException(RuntimeException::class);

        $entry->save();
    }

    public function test_the_row_survives_the_attempt(): void
    {
        $entry = AuditLog::record(AuditLog::LOGIN);

        try {
            $entry->delete();
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('audit_log', ['id' => $entry->id, 'action' => AuditLog::LOGIN]);
    }

    /* Model events --------------------------------------------------------- */

    public function test_creating_a_model_writes_one_row_with_its_attributes(): void
    {
        $authority = Authority::factory()->create(['acronym' => 'CRAN']);

        $entry = AuditLog::where('auditable_type', $authority->getMorphClass())
            ->where('auditable_id', $authority->id)
            ->where('action', AuditLog::CREATED)
            ->sole();

        $this->assertSame('CRAN', $entry->changes['attributes']['acronym']);
    }

    /**
     * The requirement that matters for table size and for readability: an
     * update stores what changed, not the whole model.
     */
    public function test_an_update_writes_only_the_changed_attributes_with_before_and_after(): void
    {
        $authority = Authority::factory()->create([
            'acronym' => 'OLD',
            'name' => 'Old Authority',
            'website' => 'https://example.test',
        ]);

        $authority->update(['acronym' => 'NEW']);

        $entry = AuditLog::where('auditable_type', $authority->getMorphClass())
            ->where('auditable_id', $authority->id)
            ->where('action', AuditLog::UPDATED)
            ->sole();

        $this->assertSame(['acronym'], array_keys($entry->changes));
        $this->assertSame('OLD', $entry->changes['acronym']['from']);
        $this->assertSame('NEW', $entry->changes['acronym']['to']);

        $this->assertArrayNotHasKey('name', $entry->changes);
        $this->assertArrayNotHasKey('website', $entry->changes);
    }

    public function test_a_save_that_changes_nothing_writes_no_row(): void
    {
        $authority = Authority::factory()->create();
        $before = AuditLog::where('action', AuditLog::UPDATED)->count();

        $authority->save();
        $authority->update(['acronym' => $authority->acronym]);

        $this->assertSame($before, AuditLog::where('action', AuditLog::UPDATED)->count());
    }

    public function test_deleting_a_model_writes_a_row(): void
    {
        $authority = Authority::factory()->create(['acronym' => 'GONE']);
        $id = $authority->id;
        $authority->delete();

        $entry = AuditLog::where('auditable_type', $authority->getMorphClass())
            ->where('auditable_id', $id)
            ->where('action', AuditLog::DELETED)
            ->sole();

        $this->assertSame('GONE', $entry->changes['attributes']['acronym']);
    }

    /* Secrets -------------------------------------------------------------- */

    public function test_secrets_are_never_written_to_the_trail(): void
    {
        $user = User::factory()->administrator()->create();

        $user->two_factor_secret = 'JBSWY3DPEHPK3PXP';
        $user->password = 'a-new-password';
        $user->save();

        $rows = AuditLog::where('auditable_type', $user->getMorphClass())->get();
        $json = $rows->pluck('changes')->toJson();

        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $json);
        $this->assertStringNotContainsString('a-new-password', $json);
        $this->assertStringContainsString('[redacted]', $json);
    }

    /* Actions with no model event ------------------------------------------ */

    public function test_a_failed_login_writes_a_row_with_a_null_user(): void
    {
        AuditLog::record(AuditLog::LOGIN_FAILED, null, ['email' => 'nobody@crasa.org']);

        $entry = AuditLog::where('action', AuditLog::LOGIN_FAILED)->sole();

        $this->assertNull($entry->user_id);
        $this->assertSame('nobody@crasa.org', $entry->changes['email']);
    }

    public function test_the_named_non_model_actions_are_all_recordable(): void
    {
        $user = User::factory()->administrator()->create();

        foreach ([
            AuditLog::LOGIN,
            AuditLog::LOGOUT,
            AuditLog::ROLE_CHANGED,
            AuditLog::DOCUMENT_DOWNLOADED,
            AuditLog::SUBMISSION_TRANSITIONED,
        ] as $action) {
            AuditLog::record($action, null, null, $user);
        }

        $this->assertSame(5, AuditLog::whereIn('action', [
            AuditLog::LOGIN, AuditLog::LOGOUT, AuditLog::ROLE_CHANGED,
            AuditLog::DOCUMENT_DOWNLOADED, AuditLog::SUBMISSION_TRANSITIONED,
        ])->where('user_id', $user->id)->count());
    }

    public function test_a_role_change_records_the_old_and_the_new_role(): void
    {
        $country = Country::factory()->create();
        $subject = User::factory()->memberStateRep($country)->create();

        $subject->update(['role' => Role::Editor]);

        $entry = AuditLog::where('auditable_type', $subject->getMorphClass())
            ->where('auditable_id', $subject->id)
            ->where('action', AuditLog::UPDATED)
            ->latest('id')
            ->first();

        $this->assertSame('member_state_rep', $entry->changes['role']['from']);
        $this->assertSame('editor', $entry->changes['role']['to']);
    }

    public function test_the_acting_user_is_recorded_automatically(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $authority = Authority::factory()->create();

        $entry = AuditLog::where('auditable_type', $authority->getMorphClass())
            ->where('auditable_id', $authority->id)
            ->where('action', AuditLog::CREATED)
            ->sole();

        $this->assertSame($admin->id, $entry->user_id);
    }

    /* Schema --------------------------------------------------------------- */

    public function test_the_ip_column_holds_a_full_ipv6_address(): void
    {
        $address = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';

        AuditLog::record(AuditLog::LOGIN, null, null, null, $address);

        $this->assertSame($address, AuditLog::latest('id')->first()->ip_address);
        $this->assertSame(39, strlen($address));
    }

    public function test_the_table_has_no_updated_at_because_rows_are_never_updated(): void
    {
        $columns = array_map(
            fn ($c) => is_object($c) ? $c->name : $c,
            DB::getSchemaBuilder()->getColumnListing('audit_log')
        );

        $this->assertContains('created_at', $columns);
        $this->assertNotContains('updated_at', $columns);
    }
}
