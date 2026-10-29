<?php

namespace App\Support\Documents;

use App\Events\DocumentAdded;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\Scopes\CountryScope;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Putting a file into a folder, and a new version over a file.
 *
 * Both write the file to the private disk (never under the web root: every
 * byte goes back out through DocumentController, which checks access first),
 * keep a version row with who and when, read the text of a PDF for search,
 * write the folder's activity to the audit log and dispatch DocumentAdded.
 */
final class DocumentStore
{
    /** The folder activity a folder page lists. */
    public const ADDED = 'document_added';

    public const REPLACED = 'document_replaced';

    public const RENAMED = 'document_renamed';

    public const MOVED = 'document_moved';

    public const BINNED = 'document_binned';

    public const RESTORED = 'document_restored';

    public const VALIDATED = 'document_validated';

    public const PURGED = 'document_purged';

    public const FOLDER_CREATED = 'folder_created';

    public const FOLDER_CHANGED = 'folder_changed';

    public const FOLDER_DELETED = 'folder_deleted';

    /**
     * The upload rules, one file. Archives are refused: a zip is a way past
     * a file-type check, and a folder whose contents nobody can list is not
     * a document store.
     *
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return [
            'file', 'max:25600',
            'mimetypes:application/pdf,application/msword,'
            .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
            .'application/vnd.ms-excel,'
            .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
            .'text/csv,text/plain',
            'extensions:pdf,doc,docx,xls,xlsx,csv,txt',
        ];
    }

    public function add(Folder $folder, UploadedFile $file, User $user): Document
    {
        [$path, $text] = $this->put($folder, $file);

        $document = DB::transaction(function () use ($folder, $file, $user, $path, $text): Document {
            $document = Document::withoutEvents(fn () => Document::create([
                'documentable_type' => Folder::class,
                'documentable_id' => $folder->id,
                'folder_id' => $folder->id,
                'country_id' => $folder->country_id,
                'committee_id' => $folder->committee_id,
                'uploaded_by' => $user->id,
                'file_path' => $path,
                'original_filename' => $this->cleanName($file->getClientOriginalName()),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'clearance' => $folder->clearance,
                'version' => 1,
                'search_text' => $text,
                'share_token' => Str::random(32),
            ]));

            $this->version($document, $user);

            return $document;
        });

        self::record(self::ADDED, $document, $folder, ['filename' => $document->original_filename], $user);

        event(new DocumentAdded($document, $folder));

        return $document;
    }

    /** A new version over a file. The earlier versions are kept. */
    public function replace(Document $document, UploadedFile $file, User $user): Document
    {
        $folder = Folder::unscoped()->findOrFail($document->folder_id);
        [$path, $text] = $this->put($folder, $file);

        DB::transaction(function () use ($document, $file, $user, $path, $text): void {
            $document->forceFill([
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'version' => $document->version + 1,
                'search_text' => $text,
                'uploaded_by' => $user->id,
                // A new version has not been validated yet.
                'validated_at' => null,
                'validated_by' => null,
            ])->saveQuietly();

            $this->version($document, $user, $this->cleanName($file->getClientOriginalName()));
        });

        self::record(self::REPLACED, $document, $folder, ['filename' => $document->original_filename, 'version' => $document->version], $user);

        event(new DocumentAdded($document, $folder));

        return $document;
    }

    /**
     * A row in the audit log that the folder's activity list reads.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function record(string $action, Document|Folder $subject, Folder $folder, array $extra = [], ?User $user = null): void
    {
        AuditLog::record($action, $subject, ['folder_id' => $folder->id, 'folder' => $folder->name] + $extra, $user);
    }

    /**
     * The recycle bin as the signed-in reader may see it: folder files
     * deleted and not yet purged, in a folder the reader may upload into
     * (which is who may restore). The bin page lists these and the folders
     * screen counts them, so the count never includes a file the reader
     * would not find in the bin.
     *
     * @return Collection<int, Document>
     */
    public static function binned(): Collection
    {
        return Document::onlyTrashed()
            ->whereNotNull('folder_id')
            ->with(['deleter', 'folder' => fn ($q) => $q->withoutGlobalScope(CountryScope::class)])
            ->orderByDesc('deleted_at')
            ->limit(500)
            ->get()
            ->filter(fn (Document $d) => $d->folder !== null && Gate::allows('upload', $d->folder))
            ->values();
    }

    /** @return array{0: string, 1: ?string} the stored path and the PDF text */
    private function put(Folder $folder, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $path = trim((string) config('crasa.documents_dir', 'extranet'), '/').'/'.$folder->id.'/'.Str::limit($name, 80, '').'-'.Str::random(8).'.'.$extension;

        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

        $text = ($file->getMimeType() === 'application/pdf' || $extension === 'pdf')
            ? PdfText::extract(Storage::disk('local')->path($path))
            : null;

        return [$path, $text];
    }

    private function version(Document $document, User $user, ?string $name = null): void
    {
        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => $document->version,
            'file_path' => $document->file_path,
            'original_filename' => $name ?? $document->original_filename,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'uploaded_by' => $user->id,
            'created_at' => now(),
        ]);
    }

    /** A file name as given, without any path a browser may have sent. */
    public function cleanName(string $name): string
    {
        $name = trim(str_replace(['/', '\\', "\0"], '-', basename($name)));

        return Str::limit($name === '' ? 'file' : $name, 200, '');
    }
}
