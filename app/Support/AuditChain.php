<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Day 3: auditlock. The hash chain over audit_log.
 *
 * Each row stores the hash of the row before it (prev_hash) and its own
 * hash (row_hash): SHA-256 over a canonical JSON of its content columns
 * plus prev_hash. Changing, removing or inserting a row anywhere in the
 * middle breaks the chain from that row on, and audit:verify-chain finds it.
 *
 * Two columns are left out of the hash, on purpose:
 * - id, because it is only known after the insert, and a row can never be
 *   updated afterwards. Order is still covered: each row names its
 *   predecessor's hash, and rows are chained in id order under a lock.
 * - user_id, because the database itself changes it: the foreign key is
 *   ON DELETE SET NULL, and a foreign-key action does not fire triggers.
 *   Deleting an account nulls user_id on its audit rows, and that must not
 *   read as tampering.
 */
class AuditChain
{
    /** The prev_hash of the first row. */
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /** The one-row table holding the latest row_hash, locked by each insert. */
    public const HEAD = 'audit_chain_head';

    /** The columns covered by the hash, in a fixed order. */
    public const COLUMNS = ['action', 'auditable_type', 'auditable_id', 'changes', 'ip_address', 'created_at'];

    /**
     * The hash of one row. $row holds the raw column values as stored
     * (changes as its JSON text, created_at as 'Y-m-d H:i:s').
     *
     * @param  array<string, mixed>|object  $row
     */
    public static function hash(array|object $row, string $prevHash): string
    {
        $row = (array) $row;
        $content = [];

        foreach (self::COLUMNS as $column) {
            $value = $row[$column] ?? null;
            $content[$column] = $value === null ? null : (string) $value;
        }

        $content['prev_hash'] = $prevHash;

        return hash('sha256', json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Walk the chain in id order.
     *
     * Runs in one transaction so that the rows and the head are read from
     * the same snapshot. Finally the head must equal the last row's hash.
     *
     * @return array{rows: int, broken_id: int|null, reason: string|null, last_hash: string}
     */
    public static function verify(?string $connection = null): array
    {
        return DB::connection($connection)->transaction(fn () => self::walk($connection));
    }

    /** @return array{rows: int, broken_id: int|null, reason: string|null, last_hash: string} */
    private static function walk(?string $connection): array
    {
        $prev = self::GENESIS;
        $rows = 0;
        $broken = null;
        $reason = null;
        $lastId = null;

        DB::connection($connection)->table('audit_log')
            ->orderBy('id')
            ->select(array_merge(['id', 'prev_hash', 'row_hash'], self::COLUMNS))
            ->chunkById(1000, function ($chunk) use (&$prev, &$rows, &$broken, &$reason): bool {
                foreach ($chunk as $row) {
                    if ($row->prev_hash !== $prev) {
                        [$broken, $reason] = [(int) $row->id, 'prev_hash does not match the previous row'];

                        return false;
                    }

                    if ($row->row_hash !== self::hash($row, $prev)) {
                        [$broken, $reason] = [(int) $row->id, 'row_hash does not match the row content'];

                        return false;
                    }

                    $prev = $row->row_hash;
                    $lastId = (int) $row->id;
                    $rows++;
                }

                return true;
            });

        if ($broken === null) {
            $head = DB::connection($connection)->table(self::HEAD)->where('id', 1)->value('hash');

            if ($head !== null && $head !== $prev) {
                [$broken, $reason] = [$lastId ?? 0, 'audit_chain_head does not match the last row'];
            }
        }

        return ['rows' => $rows, 'broken_id' => $broken, 'reason' => $reason, 'last_hash' => $prev];
    }

    /** Whether the database enforces immutability with triggers (MySQL and MariaDB only). */
    public static function hasTriggers(?string $connection = null): bool
    {
        return in_array(DB::connection($connection)->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
