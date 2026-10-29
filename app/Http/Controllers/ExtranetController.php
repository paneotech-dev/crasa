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
use App\Support\ExtranetNavigation;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        /*
         * RP-05: a document added to my committee. Fired here rather than
         * on the model, because a document created by the legacy import or
         * by a seeder is not something to wake fourteen regulators about.
         *
         * The audience is the seats on that committee, worked out at send
         * time from the pivot rather than from a stored subscription list.
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
            'folders' => $found ? Folder::where('name', 'like', $like)->orderBy('name')->limit(25)->get()
                ->filter(fn (Folder $f) => Gate::allows('view', $f))->values() : collect(),
            'documents' => $found ? Document::whereNotNull('folder_id')->where('original_filename', 'like', $like)
                ->latest()->limit(25)->get()
                ->filter(fn (Document $d) => Gate::allows('view', $d))->values() : collect(),
        ]);
    }

    public function calendar(Request $request): View
    {
        Gate::authorize('viewAny', Meeting::class);

        $asked = $this->monthFrom($request->query('month'));
        $committee = $request->string('committee')->value() ?: null;

        $visible = fn ($query) => $query
            ->with('committee')
            ->when($committee, fn ($q, $v) => $q->whereHas('committee', fn ($c) => $c->where('slug', $v)))
            ->get()
            ->filter(fn (Meeting $m) => Gate::allows('view', $m))
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
