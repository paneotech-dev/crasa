<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDocumentThumbnail;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Queues thumbnail generation for every PDF that has none.
 *
 * Reuses the stage 2 pipeline rather than duplicating it. Run after a bulk
 * import, after a seed, or after a restore: a PDF whose recorded thumbnail
 * file is no longer on the public disk counts as having none, so a partial
 * restore or a replaced source file does not leave a broken image behind.
 */
class GenerateMissingThumbnails extends Command
{
    protected $signature = 'documents:thumbnails {--sync : Run now instead of queueing}
        {--force : Rebuild every PDF thumbnail, not only the missing ones}';

    protected $description = 'Generate page-1 thumbnails for PDFs that have none';

    public function handle(): int
    {
        $public = Storage::disk('public');

        $documents = Document::where('mime_type', 'application/pdf')
            ->get()
            ->filter(fn (Document $document) => $this->option('force')
                || $document->thumbnail_path === null
                || ! $public->exists($document->thumbnail_path))
            ->values();

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
