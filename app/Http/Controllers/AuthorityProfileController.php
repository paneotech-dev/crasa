<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\AuthorityChange;
use App\Models\Committee;
use App\Models\Sector;
use App\Services\AuthorityProfile;
use App\Support\SafeImage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        // The member area and a country, like the sidebar row; a committee
        // member reads the record and does not propose.
        abort_unless($request->user()->can('read-own-country-record'), 403);

        $country = $request->user()->country;

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
            'mayPropose' => $request->user()->can(Permission::SubmitCountryReturn->value),
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

        // Proposing a change is the representative's: the member area, a
        // country and the permission to file for it.
        abort_unless($user->can('read-own-country-record') && $user->can(Permission::SubmitCountryReturn->value), 403);

        $country = $user->country;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'acronym' => ['required', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'established_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'sectors' => ['array'],
            'sectors.*' => ['integer', 'exists:sectors,id'],
            'committees' => ['array'],
            'committees.*' => ['integer', 'exists:committees,id'],
            // The type is judged from the content in logo(), never the name:
            // see SafeImage.
            'logo' => ['nullable', 'file', 'max:2048'],
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
     * Public by definition, so it is on the public disk; but its name and
     * extension are chosen here, from the file's content, never from what
     * the uploader typed (19 October 2026: a JPEG named logo.html used to be
     * stored and served as bw.html). A random part in the name means a
     * proposal never overwrites the logo readers see before the Secretariat
     * approves it. nginx serves /storage with a strict content security
     * policy and image types only.
     */
    private function logo(Request $request, string $iso): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        $extension = SafeImage::extensionFor($request->file('logo'));

        if ($extension === null) {
            throw ValidationException::withMessages(['logo' => __('authority.logo_refused')]);
        }

        $path = 'images/authorities/'.Str::lower($iso).'-'.Str::lower(Str::random(12)).'.'.$extension;

        Storage::disk('public')->put($path, file_get_contents($request->file('logo')->getRealPath()));

        return $path;
    }
}
