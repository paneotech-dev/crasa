<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTrigger;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Meeting;
use App\Services\CalendarFeed;
use App\Services\NotifiesMembers;
use App\Support\CountryDashboard;
use App\Support\Documents\DocumentStore;
use App\Support\Documents\FolderAccess;
use App\Support\Documents\PdfText;
use App\Support\ExtranetNavigation;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

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

        $documents = $folder->documents()
            ->with(['uploader', 'validator'])
            ->latest()
            ->get()
            ->each(fn (Document $document) => $document->setRelation('folder', $folder))
            ->filter(fn (Document $document) => Gate::allows('view', $document))
            ->values();

        $effective = FolderAccess::effective($folder);

        return view('pages.extranet.folder', [
            'folder' => $folder,
            'children' => $folder->children->filter(fn (Folder $child) => Gate::allows('view', $child))->values(),
            'documents' => $documents,
            'canUpload' => Gate::allows('upload', $folder),
            'canValidate' => Gate::allows('validate', $folder),
            'canEdit' => Gate::allows('update', $folder),
            'canAddSubfolder' => Gate::allows('update', $folder) && $folder->depth() < Folder::MAX_DEPTH,
            'canDelete' => Gate::allows('delete', $folder),
            'ancestors' => $folder->ancestors(),
            'access' => FolderAccess::summary($folder),
            'inheritedFrom' => $effective['from'] !== null && $effective['from']->id !== $folder->id ? $effective['from'] : null,
            'activity' => $this->activity($folder),
        ]);
    }

    /**
     * The folder's activity, from the audit log: files added, replaced,
     * downloaded, moved, renamed, validated, binned and restored.
     */
    private function activity(Folder $folder)
    {
        $ids = Document::withTrashed()->where('folder_id', $folder->id)->pluck('id')->all();
        $folderActions = [DocumentStore::FOLDER_CREATED, DocumentStore::FOLDER_CHANGED];

        return AuditLog::query()
            ->with('user')
            ->where(function ($q) use ($folder, $ids, $folderActions): void {
                $q->where(fn ($f) => $f->where('auditable_type', $folder->getMorphClass())->where('auditable_id', $folder->id)->whereIn('action', $folderActions));
                if ($ids !== []) {
                    $q->orWhere(fn ($d) => $d->where('auditable_type', (new Document)->getMorphClass())->whereIn('auditable_id', $ids)->whereIn('action', [
                        DocumentStore::ADDED, DocumentStore::REPLACED, DocumentStore::RENAMED, DocumentStore::MOVED,
                        DocumentStore::BINNED, DocumentStore::RESTORED, DocumentStore::VALIDATED, AuditLog::DOCUMENT_DOWNLOADED,
                    ]));
                }
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            // A move is written for both folders; each shows its own.
            ->filter(fn (AuditLog $row) => ! isset($row->changes['folder_id']) || (int) $row->changes['folder_id'] === $folder->id)
            ->take(15)
            ->values();
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

        // Several files at once ("files[]"), or one ("file", as before).
        $request->validate([
            'files' => ['required_without:file', 'array', 'max:20'],
            'files.*' => DocumentStore::fileRules(),
            'file' => array_merge(['required_without:files'], DocumentStore::fileRules()),
        ], [], ['files.*' => __('d3_docs.file_attribute'), 'file' => __('d3_docs.file_attribute')]);

        $files = $request->hasFile('files') ? $request->file('files') : [$request->file('file')];
        $store = app(DocumentStore::class);
        $added = [];

        foreach ($files as $file) {
            $document = $store->add($folder, $file, $request->user());
            $added[] = $document->original_filename;

            /*
             * RP-05: a document added to my committee. Fired here rather than
             * on the model, because a document created by the legacy import or
             * by a seeder is not something to wake fourteen regulators about.
             */
            if ($folder->committee !== null) {
                app(NotifiesMembers::class)->fire(
                    NotificationTrigger::CommitteeDocument,
                    __('notifications.filed', [
                        'file' => $document->original_filename,
                        'committee' => $folder->committee->name,
                    ]),
                    route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $folder->id]),
                    committee: $folder->committee,
                    except: $request->user(),
                    payload: ['document_id' => $document->id],
                );
            }
        }

        return back()->with('status', count($added) === 1
            ? __('extranet.uploaded', ['name' => $added[0]])
            : __('d3_docs.uploaded_many', ['count' => count($added)]));
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
                ->orderBy('date_unknown')
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
    /**
     * The country dashboard, screen 04 and RP-12.
     *
     * WHAT CHANGED AND WHY. This page opened on a welcome and three panels
     * of recent things. A national regulator signing in wants one question
     * answered: what does my authority owe, and what has changed since I
     * was last here. Both are answerable from records the platform already
     * holds, and neither was being asked.
     *
     * A committee member gets the committee view instead, because a person
     * who holds a seat but represents no member state has no return to
     * file and no completeness figure of their own. Giving them the
     * country panels would show them either somebody else's numbers or
     * three empty boxes.
     */
    public function dashboard(Request $request): View
    {
        $user = $request->user();

        return view('pages.extranet.dashboard', CountryDashboard::for($user) + [
            'isCountryView' => $user->country_id !== null,
            'notifications' => $user->notifications()->limit(5)->get(),
            'clearance' => ExtranetNavigation::clearance($user),
        ]);
    }

    /**
     * The meetings calendar, screen 12.
     *
     * The screen draws a month grid with meetings on their dates, a
     * committee filter and an export. The build had an ordered table: all
     * the data was right and the view the screen draws was not built.
     *
     * The month is in the query string, so a particular month is a URL a
     * chair can send. An unparseable month falls back to this one rather
     * than erroring, because a mistyped link should still open a calendar.
     */
    /**
     * The member area's own search (INC-11, UX-02): meetings, folders and
     * files, each one checked against the same policy as the page that
     * shows it, so a result is never something the reader could not open.
     * Another member state's folders are already out of the query, by the
     * global scope.
     */
    public function search(Request $request): View
    {
        Gate::authorize('viewAny', Folder::class);

        $term = trim((string) $request->query('q', ''));
        $like = '%'.addcslashes($term, '%_\\').'%';
        $found = mb_strlen($term) >= 2;

        return view('pages.extranet.search', [
            'term' => $term,
            'meetings' => $found ? Meeting::where(fn ($q) => $q->where('title', 'like', $like)->orWhere('location', 'like', $like))
                ->orderByDesc('starts_on')->limit(25)->get()
                ->filter(fn (Meeting $m) => Gate::allows('view', $m))->values() : collect(),
            'folders' => $found ? Folder::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like))
                ->orderBy('name')->limit(25)->get()
                ->filter(fn (Folder $f) => Gate::allows('view', $f))->values() : collect(),
            // By name, and inside a PDF by its text. Every result is checked
            // against the same policy as the file itself, so a match in
            // another member state's folder is never returned.
            'documents' => $found ? Document::whereNotNull('folder_id')
                ->where(fn ($q) => $q->where('original_filename', 'like', $like)->orWhere('search_text', 'like', $like))
                ->latest()->limit(100)->get()
                ->filter(fn (Document $d) => Gate::allows('view', $d))->take(25)->values() : collect(),
            'textSearch' => PdfText::tool() !== null,
        ]);
    }

    public function calendar(Request $request): View
    {
        Gate::authorize('viewAny', Meeting::class);

        $asked = $this->monthFrom($request->query('month'));
        $committee = $request->string('committee')->value() ?: null;

        // The filter takes the public calendar's committee codes (PC, SFMC:
        // App\Support\EventGroup), so the two calendars share colours, codes
        // and links; a committee slug from an older link still works.
        $isCode = $committee !== null && isset(\App\Support\EventGroup::GROUPS[$committee]);

        $visible = fn ($query) => $query
            ->with('committee')
            ->when($committee && ! $isCode, fn ($q) => $q->whereHas('committee', fn ($c) => $c->where('slug', $committee)))
            ->get()
            ->filter(fn (Meeting $m) => Gate::allows('view', $m))
            ->when($isCode, fn ($c) => $c->filter(fn (Meeting $m) => $m->group()->code === $committee))
            ->values();

        $today = LocalTime::today();
        $month = $asked ?? LocalTime::now()->startOfMonth();
        $inMonth = $visible($this->inMonth($month));

        // Opened with no month asked for, on a month with nothing in it: open
        // on the next month that has a meeting instead (PE-T05, PE-T06).
        $movedFrom = null;
        if ($asked === null && $inMonth->isEmpty()) {
            $next = $visible(Meeting::dated()
                ->whereDate('starts_on', '>', $month->endOfMonth()->toDateString())
                ->orderBy('starts_on'))->first();

            if ($next) {
                $movedFrom = $month;
                $month = CarbonImmutable::parse($next->starts_on->toDateString())->startOfMonth();
                $inMonth = $visible($this->inMonth($month));
            }
        }

        return view('pages.extranet.calendar', [
            'month' => $month,
            'today' => $today,
            'movedFrom' => $movedFrom,
            'committeeFilter' => $committee,
            'committees' => Committee::where('is_active', true)->orderBy('name')->get(),
            'groups' => \App\Support\EventGroup::present(
                Meeting::query()->get()->filter(fn (Meeting $m) => Gate::allows('view', $m))
            ),
            'inMonth' => $inMonth,
            'upcoming' => $visible(Meeting::upcoming()),
            'past' => $visible(Meeting::past()->limit(20)),
        ]);
    }

    /**
     * Dated meetings that touch a month: those that start in it, and those
     * that started before it and are still running in it.
     */
    private function inMonth(CarbonImmutable $month)
    {
        $first = $month->startOfMonth()->toDateString();
        $last = $month->endOfMonth()->toDateString();

        return Meeting::dated()
            ->whereDate('starts_on', '<=', $last)
            ->whereRaw('COALESCE(ends_on, starts_on) >= ?', [$first])
            ->orderBy('starts_on');
    }

    /**
     * The calendar as a file a regulator can open in their own calendar.
     *
     * Screen 12: "The feed carries only the meetings your category can
     * see." So every meeting goes through the same Gate the page uses, and
     * the file is generated per request rather than cached, because a
     * cached calendar is a calendar that leaks the moment somebody's
     * clearance changes.
     */
    public function calendarExport(Request $request): Response
    {
        Gate::authorize('viewAny', Meeting::class);

        $month = $this->monthFrom($request->query('month')) ?? LocalTime::now()->startOfMonth();

        $meetings = $this->inMonth($month)
            ->get()
            ->filter(fn (Meeting $m) => Gate::allows('view', $m))
            ->values();

        return response(
            app(CalendarFeed::class)->ics($meetings),
            200,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="crasa-'.$month->format('Y-m').'.ics"',
            ]
        );
    }

    /**
     * The month a calendar URL asks for.
     *
     * Checked with a pattern before it is parsed, because Carbon rolls an
     * out-of-range month forward rather than refusing it: "2027-99" parses
     * happily to March 2035, and a reader who mistyped a link would land
     * nine years out with no indication anything had gone wrong. The
     * exception handler alone did not catch that, because nothing threw.
     *
     * Anything that is not a real year and a month between 01 and 12 is
     * treated as no month asked for, and the calendar opens as it would by
     * default. A mistyped link should still open a calendar.
     */
    private function monthFrom(?string $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value)) {
            return null;
        }

        // "!" resets the fields the format does not name. Without it the day
        // came from today, so on the 29th, 30th or 31st "2027-02" rolled
        // over into March.
        try {
            return CarbonImmutable::createFromFormat('!Y-m', $value, LocalTime::zone())->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }
}
