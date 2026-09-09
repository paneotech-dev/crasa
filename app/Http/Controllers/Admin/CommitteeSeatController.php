<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommitteeRole;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Which member states hold seats on which committee, and in what role.
 *
 * THIS IS WHAT THE CONFIRMATION SESSION IN LILONGWE WRITES INTO. The
 * committees have been in the platform since the schema was written and the
 * seeder has never filled this in, deliberately: inventing which member
 * state sits on which committee is the one thing that seeder must not do.
 * So the committees page has said "seats not yet recorded" to every reader,
 * and there has been no way to record them.
 *
 * ONE CHAIR PER COMMITTEE. A committee with two chairs is a data error
 * somebody has to notice later, so promoting a second one moves the first
 * to member rather than refusing: the intent of the click is unambiguous
 * and refusing it would make the operator do it in two steps.
 *
 * The counts on the committee cards and the member state profiles read this
 * pivot, so they follow with no further work.
 */
class CommitteeSeatController extends Controller
{
    public function index(Committee $committee): View
    {
        Gate::authorize(Permission::ManageContent->value);

        $committee->load(['sector', 'translations']);

        $seated = $committee->countries()->orderBy('name')->get();

        return view('pages.admin.committees.seats', [
            'committee' => $committee,
            'seated' => $seated,
            // Only the states that do not already hold a seat. Offering one
            // that does turns "add" into a silent change of role.
            'available' => Country::whereNotIn('id', $seated->pluck('id'))->orderBy('name')->get(),
            'roles' => CommitteeRole::cases(),
        ]);
    }

    public function store(Request $request, Committee $committee): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $data = $request->validate([
            'country_id' => [
                'required',
                'integer',
                Rule::exists('countries', 'id'),
                Rule::unique('committee_country', 'country_id')
                    ->where('committee_id', $committee->id),
            ],
            'role' => ['required', Rule::enum(CommitteeRole::class)],
        ]);

        $role = CommitteeRole::from($data['role']);

        $this->standDownExistingChair($committee, $role, (int) $data['country_id']);

        $committee->countries()->attach($data['country_id'], [
            'role' => $role->value,
            // Recorded by the Secretariat, so it is not demonstration data
            // and the go-live strip leaves it alone.
            'is_sample' => false,
        ]);

        $country = Country::find($data['country_id']);

        AuditLog::record(AuditLog::UPDATED, $committee, [
            'seat_added' => $country?->name,
            'role' => $role->value,
        ], $request->user());

        return back()->with('status', __('admin.seat_added', [
            'country' => $country?->name,
            'role' => $role->label(),
        ]));
    }

    public function update(Request $request, Committee $committee, Country $country): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        abort_unless($committee->countries()->whereKey($country->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::enum(CommitteeRole::class)],
        ]);

        $role = CommitteeRole::from($data['role']);
        $before = $committee->countries()->whereKey($country->id)->first()?->pivot->role;

        $this->standDownExistingChair($committee, $role, $country->id);

        $committee->countries()->updateExistingPivot($country->id, ['role' => $role->value]);

        AuditLog::record(AuditLog::UPDATED, $committee, [
            'seat_changed' => $country->name,
            'from' => $before,
            'to' => $role->value,
        ], $request->user());

        return back()->with('status', __('admin.seat_changed', [
            'country' => $country->name,
            'role' => $role->label(),
        ]));
    }

    public function destroy(Request $request, Committee $committee, Country $country): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        abort_unless($committee->countries()->whereKey($country->id)->exists(), 404);

        $committee->countries()->detach($country->id);

        AuditLog::record(AuditLog::UPDATED, $committee, [
            'seat_removed' => $country->name,
        ], $request->user());

        return back()->with('status', __('admin.seat_removed', ['country' => $country->name]));
    }

    /**
     * A committee has one chair.
     *
     * Promoting a second moves the first to member rather than refusing.
     * The intent of the click is unambiguous, and refusing it would make
     * the operator demote the old chair first, which is two steps for one
     * decision and leaves the committee chairless in between.
     */
    private function standDownExistingChair(Committee $committee, CommitteeRole $role, int $incoming): void
    {
        if ($role !== CommitteeRole::Chair) {
            return;
        }

        $committee->countries()
            ->wherePivot('role', CommitteeRole::Chair->value)
            ->get()
            ->reject(fn (Country $country) => $country->id === $incoming)
            ->each(fn (Country $country) => $committee->countries()->updateExistingPivot(
                $country->id,
                ['role' => CommitteeRole::Member->value]
            ));
    }
}
