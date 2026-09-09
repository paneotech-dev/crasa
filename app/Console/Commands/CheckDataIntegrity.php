<?php

namespace App\Console\Commands;

use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records that point at records which are no longer there.
 *
 * Written after a purge rehearsal on 9 September 2026 left 337 orphaned
 * translation rows, and after four reseeds in one afternoon made another
 * 525. Both were invisible: nothing errored, no page broke, and the rows
 * simply accumulated.
 *
 * WHY IT IS NOT MERELY UNTIDY. A translation is addressed by
 * translatable_type and translatable_id, and ids are reused. An orphan for
 * publication 5 becomes the French title of the next publication created as
 * id 5 — a demonstration string appearing under a real document, on a
 * platform whose whole claim is that its content is sourced.
 *
 * Reports by default and fixes on request, because a command that deletes
 * rows the moment it is run is a command nobody wants on a schedule.
 */
class CheckDataIntegrity extends Command
{
    protected $signature = 'data:check
        {--fix : Remove what it finds rather than only reporting it}';

    protected $description = 'Report records whose parent no longer exists';

    public function handle(): int
    {
        $findings = $this->orphanedTranslations();
        $total = array_sum($findings);

        if ($total === 0) {
            $this->info('No orphaned records.');

            return self::SUCCESS;
        }

        $this->table(
            ['Type', 'Orphaned translations'],
            collect($findings)->filter()->map(fn ($n, $type) => [class_basename($type), $n])->values()->all()
        );

        if (! $this->option('fix')) {
            /*
             * Logged at warning level as well as printed, because this
             * command's real home is a nightly schedule where nobody is
             * watching the output. A silent accumulation that is also
             * silent in the log is one nobody finds.
             */
            Log::warning($total.' orphaned translation rows. Run `php artisan data:check --fix` to clear them.', [
                'by_type' => array_filter($findings),
            ]);

            $this->line('');
            $this->warn($total.' orphaned rows. Nothing has been changed. Run with --fix to clear them.');

            // Non-zero, so a scheduler or a CI step notices.
            return self::FAILURE;
        }

        $removed = $this->clear();

        $this->info($removed.' orphaned rows removed.');

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function orphanedTranslations(): array
    {
        $findings = [];

        foreach ($this->types() as $type) {
            $findings[$type] = $this->query($type)->count();
        }

        return $findings;
    }

    private function clear(): int
    {
        $removed = 0;

        foreach ($this->types() as $type) {
            $removed += $this->query($type)->delete();
        }

        return $removed;
    }

    /** @return Collection<int, string> */
    private function types()
    {
        return DB::table('translations')->select('translatable_type')->distinct()->pluck('translatable_type');
    }

    private function query(string $type): Builder
    {
        $query = DB::table((new Translation)->getTable())->where('translatable_type', $type);

        if (class_exists($type)) {
            $table = (new $type)->getTable();
            $query->whereNotIn('translatable_id', fn ($q) => $q->select('id')->from($table));
        }
        // A type whose class the codebase has dropped is orphaned in whole.

        return $query;
    }
}
