<?php

use App\Support\AuditChain;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Day 3: auditlock. The audit log becomes immutable in the database, not
 * only in the model.
 *
 * 1. prev_hash and row_hash (CHAR(64)).
 * 2. The chain is backfilled for the existing rows in id order. This has to
 *    happen before step 3: once the triggers exist, no row can be updated,
 *    not even by this migration.
 * 3. On MySQL and MariaDB, BEFORE UPDATE and BEFORE DELETE triggers that
 *    raise SQLSTATE 45000. SQLite (the test suite) gets no triggers; the
 *    trigger test runs against the preview MariaDB connection.
 *
 * A foreign-key action does not fire triggers, so deleting a user still
 * sets user_id to NULL on that user's audit rows; user_id is outside the
 * hash for that reason (App\Support\AuditChain).
 *
 * The grants (SELECT and INSERT only for the application user) are an
 * operations script, not this migration: see prod-grants.sh and the
 * staging proposal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->char('prev_hash', 64)->nullable();
            $table->char('row_hash', 64)->nullable();
        });

        $prev = AuditChain::GENESIS;

        // One transaction: faster, and a half-backfilled chain never stays.
        DB::transaction(fn () => DB::table('audit_log')->orderBy('id')
            ->select(array_merge(['id'], AuditChain::COLUMNS))
            ->chunkById(1000, function ($rows) use (&$prev): void {
                foreach ($rows as $row) {
                    $hash = AuditChain::hash($row, $prev);
                    DB::table('audit_log')->where('id', $row->id)->update(['prev_hash' => $prev, 'row_hash' => $hash]);
                    $prev = $hash;
                }
            }));

        if (AuditChain::hasTriggers()) {
            DB::unprepared("CREATE TRIGGER audit_log_no_update BEFORE UPDATE ON audit_log FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only: an audit row cannot be updated'");
            DB::unprepared("CREATE TRIGGER audit_log_no_delete BEFORE DELETE ON audit_log FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only: an audit row cannot be deleted'");
        }
    }

    public function down(): void
    {
        if (AuditChain::hasTriggers()) {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_log_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_log_no_delete');
        }

        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropColumn(['prev_hash', 'row_hash']);
        });
    }
};
