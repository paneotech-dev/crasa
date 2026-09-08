<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDocumentThumbnail;
use App\Models\Document;
use Illuminate\Console\Command;

/**
 * Queues thumbnail generation for every PDF that has none.
 *
 * Reuses the stage 2 pipeline rather than duplicating it. Run after a bulk
 * import, or after a seed.
 */
class GenerateMissingThumbnails extends Command
{
    protected $signature = 'documents:thumbnails {--sync : Run now instead of queueing}';

    protected $description = 'Generate page-1 thumbnails for PDFs that have none';

    public function handle(): int
    {
        $documents = Document::whereNull('thumbnail_path')
            ->where('mime_type', 'application/pdf')
            ->get();

        if ($documents->isEmpty()) {
            $this->info('Every PDF already has a thumbnail.');

            return self::SUCCESS;
        }

        foreach ($documents as $document) {
            if ($this->option('sync')) {
                (new GenerateDocumentThumbnail($document->id))->handle();
            } else {
                GenerateDocumentThumbnail::dispatch($document->id);
            }
        }

        $verb = $this->option('sync') ? 'Generated' : 'Queued';
        $this->info($verb.' '.$documents->count().' thumbnail(s).');

        return self::SUCCESS;
    }
}
