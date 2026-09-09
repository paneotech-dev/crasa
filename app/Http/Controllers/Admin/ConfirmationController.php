<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Authority;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Sector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Confirming the member state records, as a working session.
 *
 * All fourteen authorities are flagged as unverified and no member state has
 * confirmed its own regulator's name, acronym, website, mandate or committee
 * seats. That has been the platform's largest content gap since the start,
 * and it was going to leave in a folder as a request.
 *
 * The fourteen delegates who can answer it will be in one room in Lilongwe
 * on 23 September. So this is built for that room: one screen per member
 * state, every field editable in place, and a confirm action that records
 * who vouched for it.
 *
 * WHY IT RECORDS A NAME AS WELL AS AN ACCOUNT. The signed-in account is the
 * Secretariat operator driving the session on a projector. The person
 * vouching for the record is the delegate standing next to them. Those are
 * rarely the same person, and a confirmation that names the operator rather
 * than the delegate is a confirmation of nothing.
 */
class ConfirmationController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Permission::ManageContent->value);

        $countries = Country::with(['authority.sectors', 'authority.confirmedBy', 'committees'])
            ->orderBy('name')
            ->get();

        return view('pages.admin.confirmations.index', [
            'countries' => $countries,
            'confirmed' => $countries->filter(fn (Country $c) => $c->authority?->isConfirmed())->count(),
        ]);
    }

    public function edit(string $iso): View
    {
        Gate::authorize(Permission::ManageContent->value);

        $country = Country::with(['authority.sectors', 'authority.confirmedBy', 'committees'])
            ->where('iso_code', strtolower($iso))
            ->firstOrFail();

        return view('pages.admin.confirmations.edit', [
            'country' => $country,
            'authority' => $country->authority,
            'sectors' => Sector::orderBy('id')->get(),
            'committees' => Committee::where('is_active', true)->orderBy('name')->get(),
            'seated' => $country->committees->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, string $iso): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $country = Country::where('iso_code', strtolower($iso))->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'acronym' => ['required', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'established_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'sectors' => ['array'],
            'sectors.*' => ['integer', 'exists:sectors,id'],
            'committees' => ['array'],
            'committees.*' => ['integer', 'exists:committees,id'],
            // Required only when confirming, below.
            'confirmed_by_name' => ['nullable', 'string', 'max:160'],
            'confirm' => ['nullable', 'boolean'],
        ]);

        $confirming = (bool) ($data['confirm'] ?? false);

        if ($confirming && blank($data['confirmed_by_name'] ?? null)) {
            return back()
                ->withInput()
                ->withErrors(['confirmed_by_name' => __('admin.confirm_name_required')]);
        }

        DB::transaction(function () use ($country, $data, $confirming, $request) {
            $authority = $country->authority ?? new Authority(['country_id' => $country->id]);

            $authority->fill([
                'name' => $data['name'],
                'acronym' => $data['acronym'],
                'website' => $data['website'] ?? null,
                'established_year' => $data['established_year'] ?? null,
            ]);

            if ($confirming) {
                // Confirmed by its own regulator, so it is no longer
                // demonstration data and the go-live purge must not take it.
                $authority->is_sample = false;
                $authority->confirmed_at = now();
                $authority->confirmed_by_id = $request->user()->id;
                $authority->confirmed_by_name = $data['confirmed_by_name'];
            }

            $authority->save();

            $authority->sectors()->sync($data['sectors'] ?? []);

            // Seats carry the pivot's own is_sample, and a confirmed seat is
            // not demonstration data either.
            $country->committees()->sync(
                collect($data['committees'] ?? [])
                    ->mapWithKeys(fn ($id) => [$id => ['role' => 'member', 'is_sample' => ! $confirming]])
                    ->all()
            );
        });

        return redirect()
            ->route('admin.confirmations', ['locale' => app()->getLocale()])
            ->with('status', $confirming
                ? __('admin.confirm_recorded', ['country' => $country->name])
                : __('admin.confirm_saved', ['country' => $country->name]));
    }
}
