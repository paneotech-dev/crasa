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
 * Safe to run again: columns and the head table are added only when
 * missing, the backfill continues from the last hash already written, and
 * each trigger is dropped before it is created. The triggers are created
 * only once every row has a row_hash.
 *
 * The grants (SELECT and INSERT only for the application user) are an
 * operations script, not this migration: see prod-grants.sh and the
 * staging proposal.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['prev_hash', 'row_hash'] as $column) {
            if (! Schema::hasColumn('audit_log', $column)) {
                Schema::table('audit_log', fn (Blueprint $table) => $table->char($column, 64)->nullable());
            }
        }

        // Continue from the last row already chained (none on a first run).
        $prev = DB::table('audit_log')->whereNotNull('row_hash')->orderByDesc('id')->value('row_hash') ?? AuditChain::GENESIS;

        // One transaction: faster, and a half-backfilled chain never stays.
        DB::transaction(function () use (&$prev): void {
            DB::table('audit_log')->whereNull('row_hash')->orderBy('id')
                ->select(array_merge(['id'], AuditChain::COLUMNS))
                ->chunkById(1000, function ($rows) use (&$prev): void {
                    foreach ($rows as $row) {
                        $hash = AuditChain::hash($row, $prev);
                        DB::table('audit_log')->where('id', $row->id)->update(['prev_hash' => $prev, 'row_hash' => $hash]);
                        $prev = $hash;
                    }
                });
        });

        // The chain head: one row with the latest row_hash, which each insert
        // locks by primary key (no gap lock). No triggers on this table.
        if (! Schema::hasTable(AuditChain::HEAD)) {
            Schema::create(AuditChain::HEAD, function (Blueprint $table) {
                $table->unsignedTinyInteger('id')->primary();
                $table->char('hash', 64);
            });
        }
        $last = DB::table('audit_log')->orderByDesc('id')->value('row_hash') ?? AuditChain::GENESIS;
        DB::table(AuditChain::HEAD)->updateOrInsert(['id' => 1], ['hash' => $last]);

        $unchained = DB::table('audit_log')->whereNull('row_hash')->count();
        if ($unchained > 0) {
            throw new \RuntimeException($unchained.' audit rows have no row_hash; the triggers were not created.');
        }

        if (AuditChain::hasTriggers()) {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_log_no_update');
            DB::unprepared("CREATE TRIGGER audit_log_no_update BEFORE UPDATE ON audit_log FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only: an audit row cannot be updated'");
            DB::unprepared('DROP TRIGGER IF EXISTS audit_log_no_delete');
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

        Schema::dropIfExists(AuditChain::HEAD);

        foreach (['prev_hash', 'row_hash'] as $column) {
            if (Schema::hasColumn('audit_log', $column)) {
                Schema::table('audit_log', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
