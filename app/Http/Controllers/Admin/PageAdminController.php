<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Page;
use App\Services\PageVersioning;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Page and block editing, CM-01.
 *
 * Screen 14 draws the content editor as a list of rows -- field, value,
 * where it appears, which languages it exists in, and an edit link -- inside
 * the same panel the rest of the back office uses. It does not draw a
 * drag-and-drop canvas, so none is built: reordering is two buttons on a
 * row, which is also the only version of reordering that works from a
 * keyboard without a bespoke widget.
 *
 * The screen is silent on the block list itself, on the workflow controls
 * and on preview. Those are case 2 and are logged in docs/OPEN_ITEMS.md.
 */
class PageAdminController extends Controller
{
    public function __construct(private readonly PageVersioning $versioning) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Page::class);

        $pages = Page::query()
            // blocks.translations, not just a count: the review column reads
            // the outstanding items out of the text rather than keeping a
            // second list of them that would go stale.
            ->with(['translations', 'blocks.translations'])
            ->withCount('blocks')
            ->when($request->string('q')->toString() !== '', function ($query) use ($request) {
                $term = $request->string('q')->toString();

                $query->where('slug', 'like', '%'.$term.'%')
                    ->orWhereHas('translations', fn ($t) => $t->where('field', 'title')->where('value', 'like', '%'.$term.'%'));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('slug')
            ->paginate(20)
            ->withQueryString();

        return view('pages.admin.pages.index', [
            'pages' => $pages,
            'statuses' => PageStatus::cases(),
        ]);
    }

    public function edit(Page $page): View
    {
        Gate::authorize('update', $page);

        $page->load(['translations', 'editableBlocks', 'blocks.translations', 'clientReviewer']);

        return view('pages.admin.pages.edit', [
            'page' => $page,
            'locales' => Locales::SUPPORTED,
            'transitions' => $this->allowedTransitions($page),
            'versions' => $page->versions()->with('author')->limit(20)->get(),
            'outstanding' => $page->outstandingClientItems(),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('update', $page);

        $rules = [];

        foreach (Locales::SUPPORTED as $locale) {
            $rules[$locale.'.title'] = ['nullable', 'string', 'max:255'];
            $rules[$locale.'.meta_title'] = ['nullable', 'string', 'max:255'];
            $rules[$locale.'.meta_description'] = ['nullable', 'string', 'max:320'];
        }

        $data = $request->validate($rules);

        $this->versioning->recordInitial($page);

        foreach (Locales::SUPPORTED as $locale) {
            $page->setTranslations($locale, array_filter(
                $data[$locale] ?? [],
                fn ($value) => $value !== null && trim($value) !== ''
            ));
        }

        $this->versioning->record($page, __('admin.page_version_details'));

        return redirect()
            ->route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug])
            ->with('status', __('admin.page_saved'));
    }

    /**
     * The workflow, enforced on the server.
     *
     * Two separate checks, deliberately. The first is whether this state
     * follows from the current one at all, which stops a page jumping from
     * archived to published without passing an author. The second is
     * whether this person may make that particular move, which is where
     * publish separates from edit. Hiding the button does neither.
     */
    public function updateStatus(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('update', $page);

        $data = $request->validate([
            'status' => ['required', Rule::enum(PageStatus::class)],
        ]);

        $target = PageStatus::from($data['status']);

        abort_unless(in_array($target, $page->status->transitions(), true), 422, __('admin.page_transition_invalid'));

        /*
         * F2. "Send for review" is a request to a moderator, not a state an
         * editor sets: it opens an approval. Publishing directly needs the
         * publish ability, which the approval rules decide.
         */
        if ($target === PageStatus::Review) {
            try {
                \App\Support\Approvals::submit($page, $request->user());
            } catch (\DomainException $e) {
                return back()->withErrors(['approval' => $e->getMessage()]);
            }

            return back()->with('status', __('fa.approval_sent'));
        }

        if ($target->requiresPublishPermission()) {
            Gate::authorize('publish', $page);
        }

        // Back to draft from review withdraws the open request.
        if ($target === PageStatus::Draft && ($open = \App\Support\Approvals::pending($page)) !== null) {
            $open->update(['state' => \App\Models\Approval::WITHDRAWN, 'decided_by' => $request->user()->id, 'decided_at' => now()]);
        }

        $this->versioning->recordInitial($page);

        $page->update([
            'status' => $target,
            'published_at' => $target === PageStatus::Published ? ($page->published_at ?? now()) : $page->published_at,
        ]);

        $this->versioning->record($page, __('admin.page_version_status', ['status' => __($target->labelKey())]));

        if ($target === PageStatus::Published) {
            \App\Support\Approvals::recordDirectPublish($page, $request->user());
        }

        return back()->with('status', __($target->labelKey().'_confirmation'));
    }

    /**
     * Mark a page reviewed by the client, or send it back for review.
     *
     * THIS IS AN ADMINISTRATION STATE AND NOTHING ELSE. No public template
     * reads needs_client_review, and the square-bracketed blanks the flag
     * counts are the page's own text, which stays as drafted. What moved off
     * the public page on 9 September 2026 is the announcement that the text
     * was a draft, which is a fact about the project rather than a fact
     * about privacy or about terms of use.
     *
     * Clearing it stamps who and when, so the sign-off is itself a record
     * and "who approved the privacy notice" has an answer.
     */
    public function clientReview(Request $request, Page $page): RedirectResponse
    {
        Gate::authorize('clientReview', $page);

        $reviewed = $request->boolean('reviewed');

        $this->versioning->recordInitial($page);

        $page->update($reviewed
            ? ['client_reviewed_at' => now(), 'client_reviewed_by' => auth()->id(), 'needs_client_review' => false]
            : ['client_reviewed_at' => null, 'client_reviewed_by' => null, 'needs_client_review' => true]);

        return back()->with('status', __($reviewed ? 'admin.page_review_cleared' : 'admin.page_review_reopened'));
    }

    /**
     * Preview renders the real page template with the real records, so what
     * a reviewer approves is what ships. It differs from the public route in
     * one respect only: it does not require the page to be published, which
     * is the whole point of previewing it.
     */
    public function preview(Page $page): View
    {
        Gate::authorize('view', $page);

        $page->load(['translations', 'visibleBlocks']);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => AuditLog::UPDATED,
            'auditable_type' => Page::class,
            'auditable_id' => $page->id,
            'changes' => ['previewed' => true],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return view('pages.composed', ['page' => $page, 'isPreview' => true]);
    }

    /**
     * @return list<PageStatus>
     */
    private function allowedTransitions(Page $page): array
    {
        return array_values(array_filter(
            $page->status->transitions(),
            fn (PageStatus $status) => $status !== PageStatus::Review
                && (! $status->requiresPublishPermission() || Gate::allows('publish', $page))
        ));
    }
}
