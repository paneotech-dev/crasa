<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Empties the recycle bin of files deleted more than thirty days ago.
 *
 * Only files that were in a member area folder: a publication's attachment
 * is not in this bin. Each purged file's versions go with it, and a stored
 * file is removed only when no other row still points at it. Every purge
 * writes an audit row naming the file and its folder.
 *
 * Registered in routes/console.php to run daily. --dry-run lists what would
 * go and changes nothing.
 */
class PurgeDocumentBin extends Command
{
    protected $signature = 'documents:purge-bin {--dry-run : List what would be purged and change nothing}';

    protected $description = 'Permanently remove files that have been in the recycle bin for more than thirty days';

    public function handle(): int
    {
        $cutoff = now()->subDays(Document::BIN_DAYS);

        $due = Document::onlyTrashed()
            ->whereNotNull('folder_id')
            ->where('deleted_at', '<', $cutoff)
            ->with('versions')
            ->get();

        if ($due->isEmpty()) {
            $this->info('Nothing in the recycle bin is older than '.Document::BIN_DAYS.' days.');

            return self::SUCCESS;
        }

        foreach ($due as $document) {
            $paths = $document->versions->pluck('file_path')->push($document->file_path)->unique()->values();

            $this->line(($this->option('dry-run') ? 'Would purge ' : 'Purging ').$document->id.': '.$document->original_filename);

            if ($this->option('dry-run')) {
                continue;
            }

            AuditLog::record('document_purged', $document, [
                'folder_id' => $document->folder_id,
                'filename' => $document->original_filename,
                'deleted_at' => $document->deleted_at?->toIso8601String(),
            ]);

            $document->versions()->delete();
            $document->forceDeleteQuietly();

            foreach ($paths as $path) {
                $stillUsed = Document::withTrashed()->where('file_path', $path)->exists()
                    || DocumentVersion::where('file_path', $path)->exists();

                if (! $stillUsed) {
                    Storage::disk('local')->delete($path);
                }
            }
        }

        $this->info(($this->option('dry-run') ? 'Would purge ' : 'Purged ').$due->count().' file(s).');

        return self::SUCCESS;
    }
}
