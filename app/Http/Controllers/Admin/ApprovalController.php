<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Support\Approvals;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Awaiting approval, F2. The inbox for moderators and administrators, the
 * decision screen for one item, the author's "Send for approval", and the
 * rules page that says per content type whether an approval is needed.
 *
 * Every action re-checks on the server. The four-eyes rule lives in
 * Approvals and is enforced there, not by hiding the buttons.
 */
class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('enter-back-office');
        $user = $request->user();

        abort_unless($user->hasAnyPermission([Permission::ApproveContent, Permission::ManageContent, Permission::ManageMeetings]), 403);

        return view('pages.admin.approvals.index', [
            'awaiting' => Approvals::awaitingFor($user),
            'mine' => Approval::with('approvable', 'decider')->where('submitted_by', $user->id)
                ->latest('id')->limit(20)->get(),
            'decided' => $user->hasPermission(Permission::ApproveContent)
                ? Approval::with('approvable', 'submitter', 'decider')->whereIn('state', [Approval::APPROVED, Approval::RETURNED])
                    ->latest('decided_at')->limit(10)->get()
                : collect(),
            'mayApprove' => $user->hasPermission(Permission::ApproveContent),
        ]);
    }

    public function show(Request $request, Approval $approval): View
    {
        Gate::authorize('enter-back-office');
        $user = $request->user();

        Gate::authorize('view', $approval);

        $approval->load('approvable', 'submitter', 'decider');

        return view('pages.admin.approvals.show', [
            'approval' => $approval,
            'item' => $approval->approvable,
            'changes' => Approvals::changes($approval),
            'mayDecide' => Approvals::mayDecide($user, $approval),
            'mayWithdraw' => Approvals::mayWithdraw($user, $approval),
            'ownItem' => $approval->submitted_by === $user->id,
            'history' => Approval::with('submitter', 'decider')
                ->where('approvable_type', $approval->approvable_type)
                ->where('approvable_id', $approval->approvable_id)
                ->latest('id')->get(),
        ]);
    }

    public function submit(Request $request, string $type, int $id): RedirectResponse
    {
        Gate::authorize('enter-back-office');
        abort_unless(isset(Approvals::TYPES[$type]), 404);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $model = Approvals::TYPES[$type]::query()->withoutGlobalScopes()->findOrFail($id);

        abort_unless(Approvals::maySubmit($request->user(), $model), 403);

        try {
            Approvals::submit($model, $request->user(), $data['note'] ?? null);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('fa.approval_sent'));
    }

    public function approve(Request $request, Approval $approval): RedirectResponse
    {
        // The approve permission for this item's own type (evening pass).
        Gate::authorize('decide', $approval);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);

        try {
            Approvals::approve($approval, $request->user(), $data['comment'] ?? null);
        } catch (DomainException|ValidationException $e) {
            $message = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage();

            throw ValidationException::withMessages(['approval' => $message]);
        }

        return redirect()->route('admin.approvals', ['locale' => app()->getLocale()])
            ->with('status', __('fa.approval_approved_flash', ['title' => Approvals::title($approval->approvable)]));
    }

    public function returnItem(Request $request, Approval $approval): RedirectResponse
    {
        // The approve permission for this item's own type (evening pass).
        Gate::authorize('decide', $approval);
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']], [
            'comment.required' => __('fa.approval_comment_required'),
        ]);

        try {
            Approvals::return($approval, $request->user(), $data['comment']);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['approval' => $e->getMessage()]);
        }

        return redirect()->route('admin.approvals', ['locale' => app()->getLocale()])
            ->with('status', __('fa.approval_returned_flash', ['title' => Approvals::title($approval->approvable)]));
    }

    /**
     * An administrator takes an item out of review, back to Draft, so it
     * can be edited again. Audited; the author is told.
     */
    public function withdraw(Request $request, Approval $approval): RedirectResponse
    {
        Gate::authorize('withdraw', $approval);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);

        try {
            Approvals::withdraw($approval, $request->user(), $data['comment'] ?? null);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['approval' => $e->getMessage()]);
        }

        return back()->with('status', __('evening.withdrawn_flash', ['title' => Approvals::title($approval->approvable)]));
    }

        public function rules(Request $request): View
    {
        abort_unless($request->user()->hasPermission(Permission::ManageSettings), 403);

        return view('pages.admin.approvals.rules', [
            'rules' => DB::table('approval_rules')->pluck('mode', 'content_type')->all(),
            'types' => array_keys(Approvals::TYPES),
        ]);
    }

    public function updateRules(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission(Permission::ManageSettings), 403);

        $rules = [];
        foreach (array_keys(Approvals::TYPES) as $type) {
            $rules['mode.'.$type] = ['required', 'in:none,one'];
        }
        $data = $request->validate($rules);

        foreach ($data['mode'] as $type => $mode) {
            $before = DB::table('approval_rules')->where('content_type', $type)->value('mode');
            DB::table('approval_rules')->updateOrInsert(
                ['content_type' => $type],
                ['mode' => $mode, 'updated_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()],
            );

            if ($before !== $mode) {
                \App\Models\AuditLog::record('approval_rule_changed', null, ['type' => $type, 'from' => $before, 'to' => $mode], $request->user());
            }
        }

        return back()->with('status', __('fa.rules_saved'));
    }
}
