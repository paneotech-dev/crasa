<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Policies\DocumentPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way a document reaches a reader.
 *
 * Files live on the private disk, outside the web root, so there is no URL
 * that serves one without passing through here. Role, country scope and
 * clearance are checked before a byte is streamed, and every download of a
 * non-public document writes an audit row.
 */
class DocumentController extends Controller
{
    public function show(Request $request, Document $document): StreamedResponse
    {
        $this->authorizeDocument($request, $document);

        return $this->stream($request, $document, 'inline');
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $this->authorizeDocument($request, $document);

        return $this->stream($request, $document, 'attachment');
    }

    private function authorizeDocument(Request $request, Document $document): void
    {
        // A guest is refused rather than redirected to a login page: the
        // existence of a document at a given id is itself information.
        abort_unless(
            $request->user()?->can('view', $document)
                ?? app(DocumentPolicy::class)->view(null, $document),
            403
        );
    }

    private function stream(Request $request, Document $document, string $disposition): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        if (! $document->isPublic()) {
            AuditLog::record(
                AuditLog::DOCUMENT_DOWNLOADED,
                $document,
                [
                    'clearance' => $document->clearance->value,
                    'filename' => $document->original_filename,
                    'disposition' => $disposition,
                ],
                $request->user(),
            );
        }

        // A read is a read (19 October 2026). Incrementing through the model
        // fired its "updated" event, so every download, by anybody, wrote an
        // "updated Document" row to the append-only audit log and moved the
        // document's updated_at. The counter is bumped in the database
        // directly: no model event, no timestamp. A download of a restricted
        // document is still recorded above, as what it is.
        Document::withoutGlobalScopes()
            ->whereKey($document->getKey())
            ->toBase()
            ->increment('download_count');

        return Storage::disk('local')->response(
            $document->file_path,
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type,
                // Never let a browser or proxy cache a restricted document.
                'Cache-Control' => $document->isPublic()
                    ? 'public, max-age=3600'
                    : 'no-store, no-cache, must-revalidate, private',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $disposition
        );
    }
}
