<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Day 3: auditlock. Archives audit rows older than the online retention
 * (config crasa.audit_retention_months, 12 by default) to a dated file:
 * <dir>/audit-<from>-<to>.jsonl.gz, one row per line, with a .sha256 file
 * beside it. The archive is kept for crasa.audit_archive_years (five).
 *
 * Archived rows are NOT deleted. The triggers on audit_log refuse every
 * DELETE, so archived rows stay in the table until a retention decision
 * changes the triggers; this command only copies them out. Each run starts
 * the day after the last archive in the folder, so a row is archived once.
 *
 * Scheduled monthly in routes/console.php.
 */
class ArchiveAuditLog extends Command
{
    protected $signature = 'audit:archive';

    protected $description = 'Copy audit rows older than the retention period to a dated archive file (rows are not deleted)';

    public function handle(): int
    {
        $dir = rtrim((string) config('crasa.audit_archive_dir'), '/');
        $cutoff = now()->startOfDay()->subMonthsNoOverflow((int) config('crasa.audit_retention_months'));

        if (! is_dir($dir) && ! @mkdir($dir, 0750, true)) {
            $this->error('The archive folder '.$dir.' does not exist and cannot be created.');

            return self::FAILURE;
        }

        if (! is_writable($dir)) {
            $this->error('The archive folder '.$dir.' is not writable.');

            return self::FAILURE;
        }

        $from = $this->nextStart($dir) ?? $this->oldest();

        if ($from === null || $from->gte($cutoff)) {
            $this->info('Nothing older than '.$cutoff->toDateString().' left to archive.');

            return self::SUCCESS;
        }

        $to = $cutoff->copy()->subDay();
        $name = 'audit-'.$from->toDateString().'-'.$to->toDateString().'.jsonl.gz';
        $path = $dir.'/'.$name;
        $gz = @gzopen($path.'.part', 'wb9');

        if ($gz === false) {
            $this->error('Cannot open '.$path.'.part for writing; nothing was archived.');

            return self::FAILURE;
        }

        $count = 0;

        DB::table('audit_log')
            ->where('created_at', '>=', $from)->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($gz, &$count): void {
                foreach ($rows as $row) {
                    gzwrite($gz, json_encode((array) $row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
                    $count++;
                }
            });

        gzclose($gz);
        rename($path.'.part', $path);
        file_put_contents($path.'.sha256', hash_file('sha256', $path).'  '.$name."\n");

        AuditLog::record('audit_archived', null, [
            'file' => $name,
            'rows' => $count,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'sha256' => hash_file('sha256', $path),
        ]);

        $this->info('Archived '.$count.' rows to '.$path.'. The rows stay in audit_log: deletion is refused by the database.');

        $this->listExpired($dir);

        return self::SUCCESS;
    }

    /** The day after the latest archive already in the folder. */
    private function nextStart(string $dir): ?Carbon
    {
        $last = null;

        foreach (glob($dir.'/audit-*.jsonl.gz') ?: [] as $file) {
            if (preg_match('/audit-\d{4}-\d{2}-\d{2}-(\d{4}-\d{2}-\d{2})\.jsonl\.gz$/', $file, $m) && ($last === null || $m[1] > $last)) {
                $last = $m[1];
            }
        }

        return $last ? Carbon::parse($last)->addDay()->startOfDay() : null;
    }

    private function oldest(): ?Carbon
    {
        $oldest = DB::table('audit_log')->min('created_at');

        return $oldest ? Carbon::parse($oldest)->startOfDay() : null;
    }

    /**
     * Archives past the archive period are listed, not removed: removing
     * one is an operations decision, taken by hand.
     */
    private function listExpired(string $dir): void
    {
        $limit = now()->subYears((int) config('crasa.audit_archive_years'))->toDateString();

        foreach (glob($dir.'/audit-*.jsonl.gz') ?: [] as $file) {
            if (preg_match('/-(\d{4}-\d{2}-\d{2})\.jsonl\.gz$/', $file, $m) && $m[1] < $limit) {
                $this->warn('Past the '.config('crasa.audit_archive_years').'-year archive period: '.basename($file));
            }
        }
    }
}
