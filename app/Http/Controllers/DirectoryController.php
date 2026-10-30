<?php

namespace App\Http\Controllers;

use App\Exceptions\RecordHidden;
use App\Models\Authority;
use App\Models\AuthorityContact;
use App\Models\Sector;
use App\Support\AccessRefusals;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The members directory. Extranet item 4.
 *
 * WHY THIS IS NOT COUNTRY-SCOPED FOR READING. The point of a regional
 * directory is that a Zambian regulator can find the right person in
 * Malawi. What a representative may not do is add, edit or delete somebody
 * at another member state's authority, and every write here checks that.
 *
 * CONSENT GATES THE LISTING. `listed()` is the only scope the directory
 * reads through, and it requires a consent timestamp. A row whose person
 * has not said their details may appear exists for their own
 * representative to manage and is invisible to everybody else. That is the
 * correct default for a person whose details somebody else typed in, and
 * these are named individuals' work numbers across fourteen jurisdictions
 * with fourteen data protection regimes.
 *
 * MEMBERS ONLY, NEVER EXPORTED, NEVER PUBLIC. There is no public route
 * here, no CSV and no report that reads this table. Held by test.
 */
class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'authority' => $request->query('authority'),
            'sector' => $request->query('sector'),
            'q' => trim((string) $request->query('q')),
        ];

        $contacts = AuthorityContact::listed()
            ->with(['authority', 'country', 'sectors'])
            ->when($filters['authority'], fn ($q, $id) => $q->where('authority_id', $id))
            ->when($filters['sector'], fn ($q, $slug) => $q->whereHas('sectors', fn ($s) => $s->where('slug', $slug)))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                // Escaped, and with an ESCAPE clause: a role containing an
                // underscore is a role a wildcard would silently widen.
                $term = '%'.addcslashes($filters['q'], '%_\\').'%';

                $q->where(fn ($w) => $w->whereRaw('name LIKE ? ESCAPE ?', [$term, '\\'])
                    ->orWhereRaw('role LIKE ? ESCAPE ?', [$term, '\\']));
            })
            ->orderBy('country_id')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('pages.extranet.directory.index', [
            'contacts' => $contacts,
            'authorities' => Authority::with('country')->orderBy('name')->get(),
            'sectors' => Sector::orderBy('name')->get(),
            'filters' => $filters,
            'anyListed' => AuthorityContact::listed()->exists(),
            'mine' => $request->user()->country_id !== null
                ? AuthorityContact::where('country_id', $request->user()->country_id)->with('sectors')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->country_id === null, 403);

        $data = $this->validated($request);
        $authority = $user->country->authority;

        abort_if($authority === null, 404);

        $contact = AuthorityContact::create([
            // The representative's own authority, never the form's.
            'authority_id' => $authority->id,
            'country_id' => $user->country_id,
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'listed_at' => ($data['consented'] ?? false) ? now() : null,
            'listed_by' => ($data['consented'] ?? false) ? $user->id : null,
            'is_sample' => false,
        ]);

        $contact->sectors()->sync($data['sectors'] ?? []);

        return back()->with('status', __('directory.added'));
    }

    public function update(Request $request, AuthorityContact $contact): RedirectResponse
    {
        $this->authoriseOwn($request, $contact);

        $data = $this->validated($request);
        $consented = (bool) ($data['consented'] ?? false);

        $contact->update([
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            // Withdrawing consent takes the row out of the directory
            // immediately and keeps it for its own representative.
            'listed_at' => $consented ? ($contact->listed_at ?? now()) : null,
            'listed_by' => $consented ? ($contact->listed_by ?? $request->user()->id) : null,
        ]);

        $contact->sectors()->sync($data['sectors'] ?? []);

        return back()->with('status', __('directory.saved'));
    }

    public function destroy(Request $request, AuthorityContact $contact): RedirectResponse
    {
        $this->authoriseOwn($request, $contact);

        $contact->delete();

        return back()->with('status', __('directory.removed'));
    }

    /**
     * A 404 rather than a 403 for somebody else's authority, so the request
     * does not confirm that a particular person is on the list.
     */
    private function authoriseOwn(Request $request, AuthorityContact $contact): void
    {
        $user = $request->user();

        if ($user->country_id === null || $contact->country_id !== $user->country_id) {
            throw new RecordHidden($contact, AccessRefusals::OTHER_MEMBER_STATE);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'role' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'sectors' => ['array'],
            'sectors.*' => ['integer', 'exists:sectors,id'],
            'consented' => ['nullable', 'boolean'],
        ]);
    }
}
