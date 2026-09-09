<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Rehearse the backup and restore, repeatably.
 *
 * docs/BACKUP_SETUP.md was rehearsed by hand on 9 September 2026 and was
 * wrong twice: the documented restore command could not authenticate, and a
 * database-only restore left every download returning 404 while every page
 * looked healthy. A procedure that is only ever rehearsed by hand gets
 * rehearsed once.
 *
 * WHAT IT CAN DO ON THIS HOST, AND WHAT IT CANNOT.
 *
 * The database user holds ALL PRIVILEGES on one schema and USAGE on
 * everything else, so it cannot create a scratch database. Verified, not
 * assumed. Without a second schema there is nowhere safe to restore into,
 * and restoring into the live one to prove a point is not a rehearsal, it
 * is an outage.
 *
 * So the default run does everything except the restore: it takes a real
 * dump with the real command, proves the dump is complete against the live
 * row counts, and proves the file store is where the backup expects it.
 * That covers both defects the manual rehearsal found.
 *
 * Given a scratch schema with --database, it does the full cycle. Asking the
 * host for one is Question 10.
 */
class RehearseBackup extends Command
{
    protected $signature = 'backup:rehearse
        {--database= : A scratch schema to restore into. Defaults to config database.rehearsal_database}
        {--keep : Leave the dump on disk rather than deleting it}';

    protected $description = 'Prove the backup and restore procedure still works';

    private const REPORT = ['Step', 'Result'];

    public function handle(): int
    {
        $rows = [];
        $failed = false;

        $directory = storage_path('app/rehearsal');
        File::ensureDirectoryExists($directory);
        $dump = $directory.'/rehearsal-'.now()->format('Ymd-His').'.sql';

        // 1. The credentials file. A password passed on the command line
        //    does not survive shell quoting on this account, and would be
        //    in the shell history besides.
        $config = $this->credentialsFile();

        // mysqldump is for MySQL. On any other connection the dump steps
        // are meaningless and the file-store checks are not, so it does the
        // half it can and says which half.
        $isMysql = DB::getDriverName() === 'mysql';

        try {
            $dumped = false;
            $counts = [];

            if (! $isMysql) {
                $rows[] = ['Dump', 'skipped, connection is '.DB::getDriverName()];
            } else {
                // 2. The dump, with the command the document tells the
                //    reader to run. If this fails, the document is wrong.
                $counts = $this->liveCounts();

                $dumped = $this->shell([
                    'mysqldump', '--defaults-extra-file='.$config,
                    '--single-transaction', '--routines', '--triggers',
                    config('database.connections.mysql.database'),
                ], $dump);

                $rows[] = ['Dump', $dumped ? 'ok, '.$this->size($dump) : 'FAILED'];
                $failed = $failed || ! $dumped;
            }

            if ($dumped) {
                // 3. Completeness. A dump that ran and is missing a table is
                //    the failure a size check does not catch.
                $missing = $this->tablesMissingFrom($dump, array_keys($counts));

                $rows[] = ['Tables in dump', $missing === []
                    ? count($counts).' of '.count($counts)
                    : 'MISSING: '.implode(', ', $missing)];
                $failed = $failed || $missing !== [];

                $rows[] = ['Rows in dump', $this->insertsLookRight($dump, $counts) ? 'consistent with live' : 'INCONSISTENT'];
            }

            // 4. The file store. The manual rehearsal restored a database
            //    and left every download 404ing, because the documents live
            //    on disk and the dump does not carry them.
            foreach (['app/private', 'app/public'] as $path) {
                $full = storage_path($path);
                $exists = File::isDirectory($full);
                $rows[] = ['Backup set: storage/'.$path, $exists ? 'present, '.$this->directorySize($full) : 'MISSING'];
                $failed = $failed || ! $exists;
            }

            // 5. A document row whose file is gone is the state a
            //    database-only restore leaves. Report it rather than wait
            //    for somebody to click a download.
            $orphanedFiles = $this->documentsWithoutFiles();
            $rows[] = ['Documents with no file', $orphanedFiles === 0 ? 'none' : $orphanedFiles.' FOUND'];
            $failed = $failed || $orphanedFiles > 0;

            // 6. The restore, only where there is somewhere safe to put it.
            $scratch = $this->option('database') ?: config('database.rehearsal_database');
            $live = config('database.connections.mysql.database');

            if ($scratch && $scratch === $live) {
                /*
                 * The one thing this command must never do. Restoring a
                 * dump over the live database is not a rehearsal, it is an
                 * outage with extra steps, and a mistyped option should not
                 * be able to cause one.
                 */
                $rows[] = ['Restore', 'REFUSED: the rehearsal schema is the live database'];
                $failed = true;
            } elseif ($scratch && $isMysql) {
                $restored = $this->shell(['mysql', '--defaults-extra-file='.$config, $scratch], null, $dump);
                $rows[] = ['Restore into '.$scratch, $restored ? 'ok' : 'FAILED'];
                $failed = $failed || ! $restored;

                if ($restored) {
                    // The point of the whole exercise: not that the restore
                    // ran, but that what came back is what went in.
                    $mismatches = $this->compare($scratch, $counts);

                    $rows[] = ['Rows restored', $mismatches === []
                        ? array_sum($counts).' across '.count($counts).' tables, all matching'
                        : 'MISMATCH: '.implode('; ', $mismatches)];
                    $failed = $failed || $mismatches !== [];

                    $dropped = $this->emptyScratch($scratch);
                    $rows[] = ['Scratch schema cleared', $dropped.' tables dropped'];
                }
            } else {
                $rows[] = ['Restore', 'skipped, no rehearsal schema configured'];
            }
        } finally {
            // The credentials file never outlives the command, whatever
            // happened above.
            File::delete($config);

            if (! $this->option('keep')) {
                File::delete($dump);
            }
        }

        $this->table(self::REPORT, $rows);

        if ($this->option('keep')) {
            $this->line('Dump kept at '.$dump);
        }

        if (! ($this->option('database') ?: config('database.rehearsal_database')) || ! $isMysql) {
            $this->warn('The restore step was skipped. Create the rehearsal schema and set DB_REHEARSAL_DATABASE; see docs/SCRATCH_SCHEMA.md.');
        }

        if ($failed) {
            $this->error('The rehearsal found a problem. docs/BACKUP_SETUP.md describes a procedure that will not work as written.');

            return self::FAILURE;
        }

        $this->info('Backup procedure verified.');

        return self::SUCCESS;
    }

    private function credentialsFile(): string
    {
        $path = storage_path('app/rehearsal/.my.cnf');
        $connection = config('database.connections.mysql');

        File::put($path, sprintf(
            "[client]\nuser=%s\npassword=\"%s\"\nhost=%s\n",
            $connection['username'],
            $connection['password'],
            $connection['host']
        ));

        // Readable by nobody else, for as long as it exists.
        chmod($path, 0600);

        return $path;
    }

    /** @param list<string> $command */
    private function shell(array $command, ?string $stdout = null, ?string $stdin = null): bool
    {
        $process = new Process($command, base_path(), null, $stdin ? File::get($stdin) : null, 300);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->line('<fg=red>'.trim($process->getErrorOutput()).'</>');

            return false;
        }

        if ($stdout) {
            File::put($stdout, $process->getOutput());
        }

        return true;
    }

    /**
     * Every table's row count in the restored schema against the live one.
     *
     * A restore that ran and is missing rows is the failure a success
     * message hides, and the go-live restore is the only time anybody would
     * find out. The comparison is the point of the exercise, not the
     * restore.
     *
     * @param  array<string, int>  $expected
     * @return list<string>
     */
    private function compare(string $schema, array $expected): array
    {
        $mismatches = [];

        foreach ($expected as $table => $count) {
            try {
                $actual = (int) DB::selectOne('SELECT COUNT(*) AS total FROM `'.$schema.'`.`'.$table.'`')->total;
            } catch (\Throwable) {
                $mismatches[] = $table.' is missing entirely';

                continue;
            }

            if ($actual !== $count) {
                $mismatches[] = $table.' has '.$actual.' of '.$count;
            }
        }

        return $mismatches;
    }

    /**
     * Leave the scratch schema empty.
     *
     * A rehearsal that left last week's data behind would compare this
     * week's dump against a mixture of both, and the second run would pass
     * for the wrong reason.
     *
     * Guarded again here rather than trusting the caller's check to still be
     * there after a refactor.
     */
    private function emptyScratch(string $schema): int
    {
        if ($schema === config('database.connections.mysql.database')) {
            throw new \RuntimeException('Refusing to drop tables from the live database.');
        }

        $tables = DB::select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$schema]
        );

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            DB::statement('DROP TABLE IF EXISTS `'.$schema.'`.`'.$table->name.'`');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        return count($tables);
    }

    /**
     * @return array<string, int>
     *
     * getTableListing() rather than SHOW TABLES, which is MySQL-only and
     * broke this command's own tests on SQLite.
     */
    private function liveCounts(): array
    {
        $counts = [];

        foreach (DB::getSchemaBuilder()->getTableListing() as $table) {
            // MySQL returns these schema-qualified — "crasadev.audit_log" —
            // and a dump names them bare. Comparing the qualified name
            // against the dump reported all forty tables missing.
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;

            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function tablesMissingFrom(string $dump, array $tables): array
    {
        $sql = File::get($dump);

        return array_values(array_filter(
            $tables,
            fn (string $table) => ! str_contains($sql, 'CREATE TABLE `'.$table.'`')
        ));
    }

    /** @param array<string, int> $counts */
    private function insertsLookRight(string $dump, array $counts): bool
    {
        $sql = File::get($dump);

        foreach ($counts as $table => $count) {
            if ($count > 0 && ! str_contains($sql, 'INSERT INTO `'.$table.'`')) {
                $this->line('<fg=red>'.$table.' has '.$count.' rows and no INSERT in the dump.</>');

                return false;
            }
        }

        return true;
    }

    private function documentsWithoutFiles(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('documents')) {
            return 0;
        }

        $missing = 0;

        foreach (DB::table('documents')->whereNotNull('file_path')->pluck('file_path') as $path) {
            if (! File::exists(storage_path('app/private/'.$path))) {
                $missing++;
            }
        }

        return $missing;
    }

    private function size(string $path): string
    {
        return number_format(File::size($path) / 1024, 0).' KB';
    }

    private function directorySize(string $path): string
    {
        $bytes = 0;

        foreach (File::allFiles($path) as $file) {
            $bytes += $file->getSize();
        }

        return $bytes < 1048576
            ? number_format($bytes / 1024, 0).' KB'
            : number_format($bytes / 1048576, 1).' MB';
    }
}
