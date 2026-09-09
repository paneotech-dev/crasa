<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Page;
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
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Page::class);

        $pages = Page::query()
            ->with('translations')
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

        $page->load(['translations', 'editableBlocks']);

        return view('pages.admin.pages.edit', [
            'page' => $page,
            'locales' => Locales::SUPPORTED,
            'transitions' => $this->allowedTransitions($page),
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

        foreach (Locales::SUPPORTED as $locale) {
            $page->setTranslations($locale, array_filter(
                $data[$locale] ?? [],
                fn ($value) => $value !== null && trim($value) !== ''
            ));
        }

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

        if ($target->requiresPublishPermission()) {
            Gate::authorize('publish', $page);
        }

        $page->update([
            'status' => $target,
            'published_at' => $target === PageStatus::Published ? ($page->published_at ?? now()) : $page->published_at,
        ]);

        return back()->with('status', __($target->labelKey().'_confirmation'));
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
            fn (PageStatus $status) => ! $status->requiresPublishPermission()
                || Gate::allows('publish', $page)
        ));
    }
}
