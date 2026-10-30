<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\AuditChain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Day 3: auditlock. Walks the audit log's hash chain in id order and
 * reports the first row where it breaks (App\Support\AuditChain).
 *
 * Exit 0 when the chain is intact, 1 with the first broken id otherwise.
 * The result is written to the Laravel log and as an audit entry
 * (audit_chain_verified), which itself joins the chain. Scheduled nightly
 * in routes/console.php.
 */
class VerifyAuditChain extends Command
{
    public const ACTION = 'audit_chain_verified';

    protected $signature = 'audit:verify-chain {--database= : Verify the audit_log of another configured connection, such as a copy}';

    protected $description = 'Verify the hash chain of the audit log';

    public function handle(): int
    {
        $connection = $this->option('database') ?: null;
        $result = AuditChain::verify($connection);
        $intact = $result['broken_id'] === null;

        $summary = [
            'intact' => $intact,
            'rows_checked' => $result['rows'],
            'first_broken_id' => $result['broken_id'],
            'reason' => $result['reason'],
            'connection' => $connection ?? config('database.default'),
        ];

        if ($intact) {
            $this->info('Audit chain intact: '.$result['rows'].' rows checked.');
            Log::info('Audit chain intact.', $summary);
        } else {
            $this->error('Audit chain broken at id '.$result['broken_id'].' ('.$result['reason'].'), after '.$result['rows'].' intact rows.');
            Log::error('Audit chain broken.', $summary);
        }

        // The entry goes to the application's own log, whichever copy was checked.
        AuditLog::record(self::ACTION, null, $summary);

        return $intact ? self::SUCCESS : self::FAILURE;
    }
}
