<?php

namespace App\Http\Controllers;

use App\Exceptions\RecordHidden;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Support\AccessRefusals;
use App\Support\Documents\DocumentStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One file in a folder: its page, its versions, its share link, and the
 * actions on it (rename, move, replace, validate, the recycle bin).
 *
 * A file is reached by whoever may open its folder and by nobody else, and
 * that is decided by DocumentPolicy on every request: by id, by share link
 * and by version. A file another member state owns answers 404 here, so the
 * page never confirms that it exists. Changing a file needs the upload
 * right on its folder; validating needs the validate right.
 */
class FolderDocumentController extends Controller
{
    public function show(Request $request, int $document): View
    {
        $document = $this->find($request, $document);
        $folder = Folder::unscoped()->findOrFail($document->folder_id);

        $canManage = Gate::allows('upload', $folder);

        return view('pages.extranet.document', [
            'document' => $document->load(['uploader', 'validator', 'versions.uploader']),
            'folder' => $folder,
            'ancestors' => $folder->ancestors(),
            'canManage' => $canManage,
            'canValidate' => Gate::allows('validate', $folder),
            'shareUrl' => route('extranet.shared', ['locale' => app()->getLocale(), 'token' => $document->shareToken()]),
            'destinations' => $canManage ? $this->destinations($folder) : collect(),
        ]);
    }

    /** The share link: stable, and it checks access every time it is opened. */
    public function shared(Request $request, string $token): RedirectResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{20,40}$/', $token) === 1, 404);

        $document = Document::query()->whereNotNull('folder_id')->where('share_token', $token)->first();

        abort_if($document === null, 404);
        $this->hideUnlessAllowed('view', $document);

        return redirect()->route('extranet.document', ['locale' => app()->getLocale(), 'document' => $document->id]);
    }

    public function rename(Request $request, int $document): RedirectResponse
    {
        $document = $this->find($request, $document);
        $folder = $this->managed($document);

        $data = $request->validate(['name' => ['required', 'string', 'max:200']]);

        $before = $document->original_filename;
        $name = app(DocumentStore::class)->cleanName($data['name']);

        // The format stays what the file is: a renamed PDF is still a PDF.
        $extension = pathinfo($before, PATHINFO_EXTENSION);
        if ($extension !== '' && strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== strtolower($extension)) {
            $name .= '.'.$extension;
        }

        $document->forceFill(['original_filename' => $name])->saveQuietly();
        DocumentStore::record(DocumentStore::RENAMED, $document, $folder, ['from' => $before, 'to' => $name, 'filename' => $name], $request->user());

        return back()->with('status', __('d3_docs.renamed', ['name' => $name]));
    }

    public function move(Request $request, int $document): RedirectResponse
    {
        $document = $this->find($request, $document);
        $from = $this->managed($document);

        $data = $request->validate(['folder_id' => ['required', 'integer']]);
        $to = Folder::find((int) $data['folder_id']);

        // The destination goes through the scope and the policy too: a
        // file cannot be moved into a folder the mover could not upload to.
        if ($to === null || Gate::denies('upload', $to)) {
            throw ValidationException::withMessages(['folder_id' => __('d3_docs.move_not_allowed')]);
        }

        if ($to->id !== $from->id) {
            $document->forceFill([
                'folder_id' => $to->id,
                'documentable_id' => $to->id,
                'country_id' => $to->country_id,
                'committee_id' => $to->committee_id,
                'clearance' => $document->clearance->rank() < $to->clearance->rank() ? $to->clearance : $document->clearance,
            ])->saveQuietly();

            DocumentStore::record(DocumentStore::MOVED, $document, $from, ['to_folder_id' => $to->id, 'to' => $to->name, 'filename' => $document->original_filename], $request->user());
            DocumentStore::record(DocumentStore::MOVED, $document, $to, ['from_folder_id' => $from->id, 'from' => $from->name, 'filename' => $document->original_filename], $request->user());
        }

        return redirect()
            ->route('extranet.document', ['locale' => app()->getLocale(), 'document' => $document->id])
            ->with('status', __('d3_docs.moved', ['name' => $document->original_filename, 'folder' => $to->name]));
    }

    public function replace(Request $request, int $document): RedirectResponse
    {
        $document = $this->find($request, $document);
        $this->managed($document);

        $request->validate(['file' => array_merge(['required'], DocumentStore::fileRules())]);

        app(DocumentStore::class)->replace($document, $request->file('file'), $request->user());

        return back()->with('status', __('d3_docs.replaced', ['name' => $document->original_filename, 'version' => $document->version]));
    }

    public function validateFile(Request $request, int $document): RedirectResponse
    {
        $document = $this->find($request, $document);
        $folder = Folder::unscoped()->findOrFail($document->folder_id);

        abort_unless(Gate::allows('validate', $folder), 403);

        $document->forceFill(['validated_at' => now(), 'validated_by' => $request->user()->id])->saveQuietly();
        DocumentStore::record(DocumentStore::VALIDATED, $document, $folder, ['filename' => $document->original_filename, 'version' => $document->version], $request->user());

        return back()->with('status', __('d3_docs.validated', ['name' => $document->original_filename]));
    }

    /** To the recycle bin, restorable for thirty days. */
    public function destroy(Request $request, int $document): RedirectResponse
    {
        $document = $this->find($request, $document);
        $folder = $this->managed($document);

        $document->forceFill(['deleted_by' => $request->user()->id])->saveQuietly();
        $document->deleteQuietly();
        DocumentStore::record(DocumentStore::BINNED, $document, $folder, ['filename' => $document->original_filename], $request->user());

        return redirect()
            ->route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $folder->id])
            ->with('status', __('d3_docs.binned', ['name' => $document->original_filename, 'days' => Document::BIN_DAYS]));
    }

    /** The recycle bin: files the reader may manage, deleted in the last thirty days. */
    public function bin(Request $request): View
    {
        $admin = $request->routeIs('admin.*');
        $this->authoriseBackOfficeBin($request);

        $documents = DocumentStore::binned();

        return view('pages.documents.bin', [
            'layout' => $admin ? 'layouts.admin' : 'layouts.extranet',
            'surface' => $admin ? 'admin' : 'extranet',
            'documents' => $documents,
        ]);
    }

    public function restore(Request $request, int $document): RedirectResponse
    {
        $this->authoriseBackOfficeBin($request);

        $document = Document::onlyTrashed()->whereNotNull('folder_id')->find($document);
        abort_if($document === null, 404);

        $folder = Folder::unscoped()->find($document->folder_id);
        abort_if($folder === null || Gate::denies('upload', $folder), 404);

        $document->restoreQuietly();
        $document->forceFill(['deleted_by' => null])->saveQuietly();
        DocumentStore::record(DocumentStore::RESTORED, $document, $folder, ['filename' => $document->original_filename], $request->user());

        return back()->with('status', __('d3_docs.restored', ['name' => $document->original_filename, 'folder' => $folder->name]));
    }

    /** An earlier version of a file, checked against the file's own access. */
    public function version(Request $request, int $version): StreamedResponse
    {
        $version = DocumentVersion::find($version);
        $document = $version ? Document::query()->whereNotNull('folder_id')->find($version->document_id) : null;

        abort_if($document === null, 404);
        $this->hideUnlessAllowed('view', $document);
        abort_unless(Storage::disk('local')->exists($version->file_path), 404);

        $folder = Folder::unscoped()->find($document->folder_id);
        if ($folder !== null) {
            DocumentStore::record(AuditLog::DOCUMENT_DOWNLOADED, $document, $folder, [
                'clearance' => $document->clearance->value, 'filename' => $version->original_filename, 'version' => $version->version, 'disposition' => 'attachment',
            ], $request->user());
        }

        return Storage::disk('local')->response($version->file_path, $version->original_filename, [
            'Content-Type' => $version->mime_type,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Type-Options' => 'nosniff',
        ], 'attachment');
    }

    /*
     * The back office's bin is part of the folders screen, and asks what
     * that screen asks (FolderPolicy::create, manage content). The door to
     * the back office alone let a moderator or an auditor open it.
     */
    private function authoriseBackOfficeBin(Request $request): void
    {
        if ($request->routeIs('admin.*')) {
            Gate::authorize('create', Folder::class);
        }
    }

    /* ------------------------------------------------------------------ */

    /** A live file in a folder that the reader may open, or a 404. */
    private function find(Request $request, int $id): Document
    {
        $document = Document::query()->whereNotNull('folder_id')->find($id);

        abort_if($document === null, 404);
        $this->hideUnlessAllowed('view', $document);

        return $document;
    }

    /** Day 3: auditdeny. A file the reader may not open is a 404, and the attempt is audited. */
    private function hideUnlessAllowed(string $ability, Document $document): void
    {
        if (Gate::denies($ability, $document)) {
            throw new RecordHidden($document, AccessRefusals::NOT_CLEARED, $ability);
        }
    }

    /** The file's folder, when the reader may manage files in it. */
    private function managed(Document $document): Folder
    {
        $folder = Folder::unscoped()->findOrFail($document->folder_id);

        abort_unless(Gate::allows('upload', $folder), 403);

        return $folder;
    }

    /** Folders a file may be moved to: the ones the reader may upload into. */
    private function destinations(Folder $current)
    {
        return Folder::query()->orderBy('name')->get()
            ->filter(fn (Folder $f) => $f->id !== $current->id && Gate::allows('upload', $f))
            ->values();
    }
}
