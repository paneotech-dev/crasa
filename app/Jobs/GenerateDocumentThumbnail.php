<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

/**
 * Renders page 1 of a PDF to a thumbnail.
 *
 * Queued, not synchronous: Ghostscript on a 200-page PDF is slow enough to
 * time out an upload request, and an editor should not wait for it.
 *
 * Imagick and Ghostscript are both present on this host and the page 1 round
 * trip was verified before this was written. If generation fails anyway the
 * document keeps a null thumbnail_path and the document row falls back to
 * the file-type icon, which is a normal state rather than an error.
 */
class GenerateDocumentThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /** The document row is 3:2. Rendered at 2x for high-density screens. */
    private const WIDTH = 640;

    private const RESOLUTION = 150;

    public function __construct(public int $documentId) {}

    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if ($document === null || ! $document->isPdf()) {
            return;
        }

        if (! Storage::disk('local')->exists($document->file_path)) {
            Log::warning('Thumbnail skipped: the source file is missing.', [
                'document' => $document->id,
                'path' => $document->file_path,
            ]);

            return;
        }

        if (! class_exists(Imagick::class)) {
            Log::warning('Thumbnail skipped: Imagick is not available on this host.', [
                'document' => $document->id,
            ]);

            return;
        }

        $source = Storage::disk('local')->path($document->file_path);
        $relative = 'thumbnails/'.$document->id.'.jpg';

        try {
            $image = new Imagick;
            $image->setResolution(self::RESOLUTION, self::RESOLUTION);
            $image->readImage($source.'[0]');
            // A PDF page can carry transparency; flatten onto white so the
            // thumbnail does not render as a black rectangle.
            $image->setImageBackgroundColor('white');
            $image = $image->flattenImages();
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(82);
            $image->thumbnailImage(self::WIDTH, 0);

            Storage::disk('public')->put($relative, $image->getImageBlob());

            $image->clear();

            $document->forceFill(['thumbnail_path' => $relative])->saveQuietly();
        } catch (Throwable $e) {
            // A failed thumbnail is not a failed upload. The row falls back
            // to the file-type icon.
            Log::warning('Thumbnail generation failed. The document keeps its file-type icon.', [
                'document' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
