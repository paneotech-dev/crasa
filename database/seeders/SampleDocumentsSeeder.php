<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Publication;
use App\Support\SampleDocumentPdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Replace the one-line placeholder PDFs of sample publications with
 * multi-page sample documents, and nothing else (14 October 2026).
 *
 * Safe to run on a live site: it touches only documents flagged is_sample
 * whose file is still a placeholder, rewrites the file, and clears the
 * thumbnail so `php artisan documents:thumbnails` draws it again.
 *
 *   php artisan db:seed --class=SampleDocumentsSeeder --force
 */
class SampleDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        $replaced = 0;

        foreach (Document::withoutGlobalScopes()->where('is_sample', true)->where('documentable_type', Publication::class)->get() as $document) {
            $disk = Storage::disk('local');
            $bytes = $disk->exists($document->file_path) ? $disk->get($document->file_path) : null;
            if (! SampleDocumentPdf::isPlaceholder($bytes)) {
                continue;
            }

            $publication = Publication::withoutGlobalScopes()->find($document->documentable_id);
            if ($publication === null) {
                continue;
            }

            $disk->put($document->file_path, SampleDocumentPdf::forPublication(
                (string) $publication->translated('title', $publication->slug, 'en'),
                $publication->publication_type->value,
                (int) $publication->published_on?->year ?: (int) now()->year,
            ));
            $document->forceFill(['size_bytes' => $disk->size($document->file_path), 'thumbnail_path' => null])->save();
            $replaced++;
        }

        $this->command?->info("Sample publication documents replaced: {$replaced}");
    }
}
