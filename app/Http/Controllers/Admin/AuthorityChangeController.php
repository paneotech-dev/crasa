<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuthorityChange;
use App\Models\Scopes\CountryScope;
use App\Services\AuthorityProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The Secretariat approving a member state's proposed record.
 * Extranet item 3.
 *
 * THE SAME ACT AS THE LILONGWE CONFIRMATION, and it runs the same apply
 * step: the authority's own regulator has stood behind these details, so
 * the record stops being demonstration data and the confirmation is
 * recorded against the delegate who vouched for it.
 */
class AuthorityChangeController extends Controller
{
    public function __construct(private readonly AuthorityProfile $profile) {}

    public function index(): View
    {
        Gate::authorize(Permission::ManageContent->value);

        return view('pages.admin.authority-changes.index', [
            'open' => AuthorityChange::query()
                ->withoutGlobalScope(CountryScope::class)
                ->where('status', 'submitted')
                ->with(['country', 'authority', 'proposer'])
                ->orderBy('submitted_at')
                ->get(),
            'decided' => AuthorityChange::query()
                ->withoutGlobalScope(CountryScope::class)
                ->whereIn('status', ['approved', 'returned'])
                ->with(['country', 'reviewer'])
                ->orderByDesc('reviewed_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function show(int $change): View
    {
        Gate::authorize(Permission::ManageContent->value);

        $record = $this->find($change);

        return view('pages.admin.authority-changes.show', [
            'change' => $record,
            'current' => $this->profile->current($record->country),
            'sectors' => \App\Models\Sector::orderBy('id')->get()->keyBy('id'),
            'committees' => \App\Models\Committee::orderBy('name')->get()->keyBy('id'),
        ]);
    }

    public function approve(Request $request, int $change): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $record = $this->find($change);

        // The delegate's own name, not the operator's account. Same rule as
        // the confirmation session, and for the same reason.
        $data = $request->validate([
            'confirmed_by_name' => ['required', 'string', 'max:160'],
        ]);

        $this->profile->apply($record->country, $record->payload, $request->user(), $data['confirmed_by_name']);

        $record->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('admin.authority-changes', ['locale' => app()->getLocale()])
            ->with('status', __('authority.approved_notice', ['country' => $record->country->name]));
    }

    public function returnChange(Request $request, int $change): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $record = $this->find($change);

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:2000'],
        ]);

        $record->update([
            'status' => 'returned',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        return redirect()
            ->route('admin.authority-changes', ['locale' => app()->getLocale()])
            ->with('status', __('authority.returned_notice', ['country' => $record->country->name]));
    }

    private function find(int $change): AuthorityChange
    {
        return AuthorityChange::query()
            ->withoutGlobalScope(CountryScope::class)
            ->with(['country.authority.sectors', 'country.committees', 'proposer'])
            ->findOrFail($change);
    }
}
