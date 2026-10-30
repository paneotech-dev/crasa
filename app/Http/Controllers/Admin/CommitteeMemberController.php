<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommitteeRole;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The people who sit on a committee, and which of them chairs it.
 *
 * Day 3. The member state seats beside it say which regulators are
 * represented; this says who, through committee_user. Drawn on the same
 * screen, held to the same rules: one chair, promoting a second stands the
 * first down to member, and every change writes an audit row on the
 * committee as the member state seats do.
 */
class CommitteeMemberController extends Controller
{
    public function store(Request $request, Committee $committee): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('committee_user', 'user_id')->where('committee_id', $committee->id),
            ],
            'role' => ['required', Rule::enum(CommitteeRole::class)],
        ]);

        $role = CommitteeRole::from($data['role']);
        $user = User::findOrFail($data['user_id']);

        $this->standDownExistingChair($committee, $role, $user->id);

        $committee->users()->attach($user->id, [
            'role' => $role->value,
            // Recorded by the Secretariat, so not demonstration data.
            'is_sample' => false,
        ]);

        AuditLog::record(AuditLog::UPDATED, $committee, [
            'member_added' => $user->name,
            'role' => $role->value,
        ], $request->user());

        return back()->with('status', __('d3_crud.member_added', ['name' => $user->name, 'role' => $this->roleLabel($role)]));
    }

    public function update(Request $request, Committee $committee, User $user): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $seat = $committee->users()->whereKey($user->id)->first();
        abort_if($seat === null, 404);

        $data = $request->validate([
            'role' => ['required', Rule::enum(CommitteeRole::class)],
        ]);

        $role = CommitteeRole::from($data['role']);
        $before = $seat->pivot->role;

        $this->standDownExistingChair($committee, $role, $user->id);

        $committee->users()->updateExistingPivot($user->id, ['role' => $role->value]);

        AuditLog::record(AuditLog::UPDATED, $committee, [
            'member_changed' => $user->name,
            'from' => $before,
            'to' => $role->value,
        ], $request->user());

        return back()->with('status', __('d3_crud.member_changed', ['name' => $user->name, 'role' => $this->roleLabel($role)]));
    }

    public function destroy(Request $request, Committee $committee, User $user): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        abort_unless($committee->users()->whereKey($user->id)->exists(), 404);

        $committee->users()->detach($user->id);

        AuditLog::record(AuditLog::UPDATED, $committee, [
            'member_removed' => $user->name,
        ], $request->user());

        return back()->with('status', __('d3_crud.member_removed', ['name' => $user->name]));
    }

    /** One chair: naming a second moves the first to member. */
    private function standDownExistingChair(Committee $committee, CommitteeRole $role, int $incoming): void
    {
        if ($role !== CommitteeRole::Chair) {
            return;
        }

        $committee->users()
            ->wherePivot('role', CommitteeRole::Chair->value)
            ->get()
            ->reject(fn (User $user) => $user->id === $incoming)
            ->each(fn (User $user) => $committee->users()->updateExistingPivot(
                $user->id,
                ['role' => CommitteeRole::Member->value]
            ));
    }

    private function roleLabel(CommitteeRole $role): string
    {
        return __('d3_crud.role_'.$role->value);
    }
}
