<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Meeting;
use App\Support\ExtranetNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The extranet: secure folders, the committee workspace and the meetings
 * calendar. IN-02, WS-09, screens 04, 11 and 12.
 *
 * ZERO TRUST IS ONE MECHANISM, NOT THREE CHECKS. Screen 12 claims that
 * another member state's folders "are not listed above, are not searchable,
 * and are not reachable by guessing a URL". A global scope on the model
 * makes all three true together: the query that would have found the folder
 * returns nothing, so the listing omits it, the search misses it, and route
 * binding 404s. A controller check would have satisfied only the third.
 *
 * The policy runs on top of that for clearance and for writes, because a
 * scope protects reads and says nothing about a POST.
 */
class ExtranetController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Folder::class);

        $user = $request->user();

        return view('pages.extranet.index', [
            'roots' => Folder::roots()
                ->with(['children', 'country', 'committee'])
                ->orderBy('name')
                ->get()
                ->filter(fn (Folder $folder) => Gate::allows('view', $folder))
                ->values(),
            'committees' => $user->committees()->with('sector')->get(),
            'upcoming' => Meeting::upcoming()
                ->with('committee')
                ->limit(6)
                ->get()
                ->filter(fn (Meeting $meeting) => Gate::allows('view', $meeting))
                ->values(),
            'country' => $user->country,
        ]);
    }

    public function folder(Request $request, Folder $folder): View
    {
        // The scope has already made another member state's folder
        // unfindable, so this is a 404 rather than a 403 and the request
        // never confirms that the folder exists.
        Gate::authorize('view', $folder);

        $folder->load(['children', 'country', 'committee', 'parent']);

        return view('pages.extranet.folder', [
            'folder' => $folder,
            'children' => $folder->children->filter(fn (Folder $child) => Gate::allows('view', $child))->values(),
            'documents' => $folder->documents()
                ->with('uploader')
                ->latest()
                ->get()
                ->filter(fn (Document $document) => Gate::allows('view', $document))
                ->values(),
            'canUpload' => Gate::allows('upload', $folder),
            'ancestors' => $folder->ancestors(),
        ]);
    }

    /**
     * Uploading into a folder.
     *
     * Archives are refused, as screen 12 draws: "Archives are not accepted."
     * A zip is a way to put anything at all past a MIME check, and a folder
     * whose contents nobody can enumerate is not a document store.
     */
    public function upload(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('upload', $folder);

        $request->validate([
            'file' => [
                'required', 'file', 'max:25600',
                'mimetypes:application/pdf,application/msword,'
                .'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                .'application/vnd.ms-excel,'
                .'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                .'text/csv,text/plain',
            ],
        ]);

        $file = $request->file('file');
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $path = 'extranet/'.$folder->id.'/'.$name.'-'.Str::random(8).'.'.$file->getClientOriginalExtension();

        /*
         * The private disk. Nothing in the extranet is ever served from the
         * filesystem: every byte goes through the document controller,
         * which checks clearance, country and committee before streaming.
         */
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        $document = Document::create([
            'documentable_type' => Folder::class,
            'documentable_id' => $folder->id,
            'folder_id' => $folder->id,
            'country_id' => $folder->country_id,
            'committee_id' => $folder->committee_id,
            'uploaded_by' => $request->user()->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'clearance' => $folder->clearance,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => AuditLog::CREATED,
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
            'changes' => ['folder' => $folder->name],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return back()->with('status', __('extranet.uploaded', ['name' => $document->original_filename]));
    }

    /** The committee workspace, screen 04. */
    public function committee(Request $request, Committee $committee): View
    {
        $user = $request->user();

        // Membership of the committee, not merely being signed in. A
        // committee workspace that any account can open is a shared drive.
        abort_unless($user->sitsOn($committee) || $user->seesAllCountries(), 403);

        return view('pages.extranet.committee', [
            'committee' => $committee->load('sector'),
            'folders' => Folder::where('committee_id', $committee->id)
                ->with('children')
                ->get()
                ->filter(fn (Folder $folder) => Gate::allows('view', $folder))
                ->values(),
            'meetings' => Meeting::where('committee_id', $committee->id)
                ->orderByDesc('starts_on')
                ->get()
                ->filter(fn (Meeting $meeting) => Gate::allows('view', $meeting))
                ->values(),
            'members' => $committee->countries()->orderBy('name')->get(),
        ]);
    }

    /**
     * The extranet landing page, screen 11.
     *
     * Until 9 September a member signed in and was redirected straight to
     * the folder list, so the first thing the extranet said to a national
     * regulator was "here is a file browser". Screen 11 draws a dashboard:
     * next meetings, recently filed in your committees, and notifications.
     *
     * Everything here is filtered by the same Gate the pages behind it use.
     * A dashboard is the classic place a permission layer leaks, because it
     * assembles records from four sources and it is tempting to trust the
     * query instead of the policy.
     */
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $committees = $user->committees()->pluck('committees.id');

        return view('pages.extranet.dashboard', [
            // The reader's own committees, or all of them for an account
            // that sees the whole region.
            'meetings' => Meeting::upcoming()
                ->with('committee')
                ->when(
                    ! $user->seesAllCountries(),
                    fn ($q) => $q->whereIn('committee_id', $committees)->orWhereNull('committee_id')
                )
                ->limit(5)
                ->get()
                ->filter(fn (Meeting $m) => Gate::allows('view', $m))
                ->values(),

            'documents' => Document::query()
                ->with(['documentable', 'folder'])
                ->latest()
                ->limit(30)
                ->get()
                ->filter(fn (Document $d) => Gate::allows('view', $d))
                ->take(5)
                ->values(),

            'notifications' => $user->notifications()->limit(5)->get(),
            'clearance' => ExtranetNavigation::clearance($user),
        ]);
    }

    /** The meetings calendar, screen 12. */
    public function calendar(Request $request): View
    {
        Gate::authorize('viewAny', Meeting::class);

        return view('pages.extranet.calendar', [
            'upcoming' => Meeting::upcoming()->with('committee')->get()
                ->filter(fn (Meeting $m) => Gate::allows('view', $m))->values(),
            'past' => Meeting::past()->with('committee')->limit(20)->get()
                ->filter(fn (Meeting $m) => Gate::allows('view', $m))->values(),
        ]);
    }
}
