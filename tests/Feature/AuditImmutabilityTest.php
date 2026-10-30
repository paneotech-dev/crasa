<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditChain;
use Dotenv\Dotenv;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Day 3: auditlock. The audit log is immutable in the database: a hash
 * chain on every row, triggers that refuse UPDATE and DELETE (MySQL and
 * MariaDB), verification, archiving and the retention sentence.
 *
 * The suite runs on SQLite, which has no triggers. The trigger tests use
 * the preview MariaDB connection explicitly: AUDITLOCK_MARIADB_DATABASE,
 * _HOST, _USERNAME and _PASSWORD, or else the DB_* values of .env.preview
 * in this checkout. They are skipped, with a message, only when neither is
 * configured.
 */
class AuditImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_new_row_chains_to_the_one_before(): void
    {
        $before = AuditLog::query()->orderByDesc('id')->value('row_hash') ?? AuditChain::GENESIS;

        $a = AuditLog::record(AuditLog::LOGIN);
        $b = AuditLog::record('role_saved', null, ['key' => 'x', 'name' => 'Équipe']);
        $c = AuditLog::record(AuditLog::LOGOUT, null, null, null, '2001:db8::1');

        $rows = DB::table('audit_log')->whereIn('id', [$a->id, $b->id, $c->id])->orderBy('id')->get();

        $this->assertSame($before, $rows[0]->prev_hash);
        $this->assertSame($rows[0]->row_hash, $rows[1]->prev_hash);
        $this->assertSame($rows[1]->row_hash, $rows[2]->prev_hash);

        foreach ($rows as $row) {
            $this->assertSame(64, strlen($row->row_hash));
            $this->assertSame(AuditChain::hash($row, $row->prev_hash), $row->row_hash, 'The stored hash does not match the stored row.');
        }

        $this->assertNull(AuditChain::verify()['broken_id']);
    }

    public function test_the_verify_command_passes_on_an_intact_chain_and_records_the_result(): void
    {
        AuditLog::record(AuditLog::LOGIN);
        AuditLog::record(AuditLog::LOGOUT);

        $this->artisan('audit:verify-chain')->expectsOutputToContain('Audit chain intact')->assertExitCode(0);

        $entry = AuditLog::where('action', 'audit_chain_verified')->latest('id')->firstOrFail();
        $this->assertTrue($entry->changes['intact']);
        $this->assertGreaterThanOrEqual(2, $entry->changes['rows_checked']);
        $this->assertNull(AuditChain::verify()['broken_id'], 'The verification entry broke the chain.');
    }

    public function test_the_chain_check_finds_a_tampered_row_on_a_copy(): void
    {
        // The test database is a copy without triggers, so the row can be changed here.
        AuditLog::record(AuditLog::LOGIN);
        $tampered = AuditLog::record(AuditLog::ROLE_CHANGED, null, ['to' => 'editor']);
        AuditLog::record(AuditLog::LOGOUT);

        DB::table('audit_log')->where('id', $tampered->id)->update(['changes' => json_encode(['to' => 'administrator'])]);

        $this->artisan('audit:verify-chain')
            ->expectsOutputToContain('Audit chain broken at id '.$tampered->id)
            ->assertExitCode(1);

        $entry = AuditLog::where('action', 'audit_chain_verified')->latest('id')->firstOrFail();
        $this->assertFalse($entry->changes['intact']);
        $this->assertSame($tampered->id, $entry->changes['first_broken_id']);
    }

    public function test_the_chain_check_finds_a_removed_row(): void
    {
        AuditLog::record(AuditLog::LOGIN);
        $gone = AuditLog::record(AuditLog::LOGIN_FAILED);
        $next = AuditLog::record(AuditLog::LOGOUT);

        DB::table('audit_log')->where('id', $gone->id)->delete();

        $this->assertSame($next->id, AuditChain::verify()['broken_id']);
    }

    public function test_archive_copies_old_rows_to_a_dated_file_and_deletes_nothing(): void
    {
        $dir = sys_get_temp_dir().'/auditlock-'.uniqid();
        config(['crasa.audit_archive_dir' => $dir, 'crasa.audit_retention_months' => 12]);

        $old = AuditLog::forceCreate(['action' => AuditLog::LOGIN, 'created_at' => now()->subMonths(14)]);
        $recent = AuditLog::record(AuditLog::LOGOUT);
        $total = AuditLog::count();

        try {
            $this->artisan('audit:archive')->expectsOutputToContain('Archived')->assertExitCode(0);

            $files = glob($dir.'/audit-*.jsonl.gz');
            $this->assertCount(1, $files);
            $this->assertMatchesRegularExpression('/audit-\d{4}-\d{2}-\d{2}-\d{4}-\d{2}-\d{2}\.jsonl\.gz$/', $files[0]);
            $this->assertStringStartsWith(hash_file('sha256', $files[0]), file_get_contents($files[0].'.sha256'));

            $ids = array_map(fn ($l) => json_decode($l, true)['id'], array_filter(explode("\n", gzdecode(file_get_contents($files[0])))));
            $this->assertContains($old->id, $ids);
            $this->assertNotContains($recent->id, $ids);

            // Nothing deleted: the archived row is still in the table.
            $this->assertDatabaseHas('audit_log', ['id' => $old->id]);
            $this->assertSame($total + 1, AuditLog::count(), 'Only the audit_archived entry is new.');

            $this->artisan('audit:archive')->expectsOutputToContain('Nothing older than')->assertExitCode(0);
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir);
        }
    }

    public function test_the_audit_log_page_states_the_retention_in_three_languages(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get('/en/admin/audit')->assertOk()
            ->assertSee('Audit entries are kept online for 12 months, then archived for five years. They cannot be changed or deleted.');
        $this->actingAs($admin)->get('/fr/admin/audit')->assertOk()
            ->assertSee('pendant 12 mois, puis sont archivées pendant cinq ans', false);
        $this->actingAs($admin)->get('/pt/admin/audit')->assertOk()
            ->assertSee('durante 12 meses e depois arquivadas durante cinco anos', false);

        config(['crasa.audit_retention_months' => 24, 'crasa.audit_archive_years' => 7]);
        $this->actingAs($admin)->get('/en/admin/audit')->assertSee('kept online for 24 months, then archived for seven years.');
    }

    public function test_update_and_delete_fail_on_mariadb_even_for_the_application_connection(): void
    {
        $db = $this->mariadb();

        $db->beginTransaction();

        try {
            $id = $db->table('audit_log')->insertGetId(['action' => 'auditlock_probe', 'created_at' => now()]);

            try {
                $db->table('audit_log')->where('id', $id)->update(['action' => 'tampered']);
                $this->fail('An UPDATE on audit_log succeeded.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('45000', $e->getMessage());
                $this->assertStringContainsString('cannot be updated', $e->getMessage());
            }

            try {
                $db->table('audit_log')->where('id', $id)->delete();
                $this->fail('A DELETE on audit_log succeeded.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('45000', $e->getMessage());
                $this->assertStringContainsString('cannot be deleted', $e->getMessage());
            }

            $this->assertSame('auditlock_probe', $db->table('audit_log')->where('id', $id)->value('action'));
        } finally {
            $db->rollBack();
        }
    }

    public function test_deleting_a_user_on_mariadb_still_nulls_user_id_despite_the_triggers(): void
    {
        $db = $this->mariadb();

        $db->beginTransaction();

        try {
            $userId = $db->table('users')->insertGetId([
                'name' => 'Auditlock probe', 'email' => 'auditlock-probe-'.uniqid().'@example.invalid', 'password' => 'x',
            ]);
            $id = $db->table('audit_log')->insertGetId(['user_id' => $userId, 'action' => 'auditlock_probe', 'created_at' => now()]);

            $db->table('users')->where('id', $userId)->delete();

            $this->assertTrue($db->table('audit_log')->where('id', $id)->exists());
            $this->assertNull($db->table('audit_log')->where('id', $id)->value('user_id'), 'The foreign key did not null user_id.');
        } finally {
            $db->rollBack();
        }
    }

    /** The preview MariaDB connection, or a skip that says why. */
    private function mariadb(): \Illuminate\Database\Connection
    {
        $env = [
            'DB_HOST' => getenv('AUDITLOCK_MARIADB_HOST') ?: null,
            'DB_DATABASE' => getenv('AUDITLOCK_MARIADB_DATABASE') ?: null,
            'DB_USERNAME' => getenv('AUDITLOCK_MARIADB_USERNAME') ?: null,
            'DB_PASSWORD' => getenv('AUDITLOCK_MARIADB_PASSWORD') ?: null,
        ];

        if (! $env['DB_DATABASE'] && is_readable(base_path('.env.preview'))) {
            $env = array_merge($env, Dotenv::parse(file_get_contents(base_path('.env.preview'))));
        }

        if (empty($env['DB_DATABASE']) || empty($env['DB_USERNAME'])) {
            $this->markTestSkipped('No MariaDB connection configured (set AUDITLOCK_MARIADB_* or provide .env.preview); the trigger test needs MariaDB.');
        }

        config(['database.connections.auditlock_mariadb' => array_merge(config('database.connections.mysql'), [
            'host' => $env['DB_HOST'] ?? 'localhost',
            'port' => $env['DB_PORT'] ?? 3306,
            'database' => $env['DB_DATABASE'],
            'username' => $env['DB_USERNAME'],
            'password' => $env['DB_PASSWORD'] ?? '',
            'url' => null,
        ])]);

        $db = DB::connection('auditlock_mariadb');

        if (count($db->select("SHOW TRIGGERS WHERE `Table` = 'audit_log'")) < 2) {
            $this->fail('The audit_log triggers are missing on '.$env['DB_DATABASE'].': run the migrations there.');
        }

        return $db;
    }
}
