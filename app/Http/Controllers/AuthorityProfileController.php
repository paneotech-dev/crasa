<?php

namespace App\Http\Controllers;

use App\Models\AuthorityChange;
use App\Models\Committee;
use App\Models\Sector;
use App\Services\AuthorityProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A member state maintaining its own authority record. Extranet item 3.
 *
 * WHY THIS DOES NOT WRITE THE RECORD. The authority record is what the
 * public members page shows and what a ministry cites. A representative
 * editing it directly would mean the name of a national regulator on a
 * treaty body's site could change without anybody at the Secretariat seeing
 * it. So the representative writes a proposal and the Secretariat applies
 * it, which is the same workflow as the Lilongwe confirmation session.
 *
 * ONE OPEN PROPOSAL PER MEMBER STATE. A second would mean the Secretariat
 * reviewing two versions of the same record with no way to tell which the
 * state meant, so a further edit revises the one that is open.
 */
class AuthorityProfileController extends Controller
{
    public function __construct(private readonly AuthorityProfile $profile) {}

    public function show(Request $request): View
    {
        $country = $request->user()->country;

        abort_if($country === null, 403);

        $country->load(['authority.sectors', 'committees']);

        $open = AuthorityChange::open()->with('proposer')->latest()->first();

        return view('pages.extranet.authority', [
            'country' => $country,
            'authority' => $country->authority,
            'current' => $this->profile->current($country),
            // What the form shows: the open proposal if there is one, so a
            // representative coming back sees what they sent rather than
            // what the platform still holds.
            'values' => $open?->payload ?? $this->profile->current($country),
            'open' => $open,
            'sectors' => Sector::orderBy('id')->get(),
            'committees' => Committee::where('is_active', true)->orderBy('name')->get(),
            'lastDecision' => AuthorityChange::whereIn('status', ['approved', 'returned'])
                ->with('reviewer')
                ->latest('reviewed_at')
                ->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $country = $user->country;

        abort_if($country === null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'acronym' => ['required', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'established_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'sectors' => ['array'],
            'sectors.*' => ['integer', 'exists:sectors,id'],
            'committees' => ['array'],
            'committees.*' => ['integer', 'exists:committees,id'],
            'logo' => ['nullable', 'image', 'max:2048', 'mimes:png,jpg,svg'],
        ]);

        $payload = [
            'name' => $data['name'],
            'acronym' => $data['acronym'],
            'website' => $data['website'] ?? null,
            'established_year' => $data['established_year'] ?? null,
            'sectors' => $data['sectors'] ?? [],
            'committees' => $data['committees'] ?? [],
            'logo_path' => $this->logo($request, $country->iso_code) ?? $country->authority?->logo_path,
        ];

        $this->profile->propose($country->fresh(), $user, $payload);

        return back()->with('status', __('authority.proposed'));
    }

    /**
     * The regulator's own mark.
     *
     * Stored under the member state's ISO code so a second upload replaces
     * rather than accumulates, and on the public disk because a logo on the
     * members page is public by definition.
     */
    private function logo(Request $request, string $iso): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        $file = $request->file('logo');
        $path = 'images/authorities/'.Str::lower($iso).'.'.Str::lower($file->getClientOriginalExtension());

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }
}
