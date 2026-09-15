<?php

namespace App\Http\Controllers;

use App\Enums\ContributionState;
use App\Enums\InstrumentStatus;
use App\Enums\InstrumentType;
use App\Models\Document;
use App\Models\Instrument;
use App\Models\Sector;
use App\Services\ContributionWorkflow;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A member state contributing a national instrument. RP-03 and RP-06,
 * extranet item 2.
 *
 * THIS IS HOW THE REGISTER FILLS. From fourteen members, not from a
 * Secretariat of five people typing in fourteen countries' legislation.
 * That is the point of the feature and it is why the form is in the
 * extranet rather than the back office.
 *
 * COUNTRY SCOPE IS ENFORCED ON THE WRITE, NOT ON THE READ. The regulatory
 * library is deliberately not country-scoped: the comparator exists to read
 * across all fourteen. What a representative may not do is contribute for,
 * or edit, another member state's instrument, and every write here fixes
 * country_id to the contributor's own rather than reading it from the form.
 * A form field the server ignores cannot be tampered with.
 */
class ContributionController extends Controller
{
    public function __construct(private readonly ContributionWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless($user->country_id !== null, 403);

        return view('pages.extranet.contributions.index', [
            'contributions' => Instrument::withContributor()
                ->where('country_id', $user->country_id)
                ->with(['translations', 'sector', 'contributor', 'reviewer'])
                ->orderByDesc('updated_at')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->country_id !== null, 403);

        return view('pages.extranet.contributions.form', [
            'instrument' => new Instrument,
            'sectors' => Sector::orderBy('name')->get(),
            'types' => InstrumentType::cases(),
            'statuses' => InstrumentStatus::cases(),
            'related' => collect(),
        ]);
    }

    public function edit(Request $request, Instrument $instrument): View
    {
        $this->authoriseContributor($request, $instrument);

        return view('pages.extranet.contributions.form', [
            'instrument' => $instrument->load(['translations', 'documents', 'sector']),
            'sectors' => Sector::orderBy('name')->get(),
            'types' => InstrumentType::cases(),
            'statuses' => InstrumentStatus::cases(),
            'related' => Instrument::published()
                ->where('country_id', $instrument->country_id)
                ->whereKeyNot($instrument->getKey())
                ->with('translations')
                ->orderBy('reference_number')
                ->limit(200)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->country_id !== null, 403);

        $data = $this->validated($request);

        $instrument = Instrument::create([
            // The contributor's own country, never the form's. A field the
            // server ignores cannot be tampered with.
            'country_id' => $user->country_id,
            'sector_id' => $data['sector_id'],
            'instrument_type' => $data['instrument_type'],
            'reference_number' => $data['reference_number'],
            'enacted_on' => $data['enacted_on'] ?? null,
            'effective_from' => $data['effective_from'] ?? null,
            'status' => $data['status'],
            'superseded_by_id' => $data['superseded_by_id'] ?? null,
            'slug' => $this->slug($data['title_en']),
            'contribution_state' => ContributionState::Draft->value,
            'contributed_by' => $user->id,
            'is_sample' => false,
        ]);

        $this->writeText($instrument, $data);
        $this->attach($request, $instrument);

        // From the first save, not from whichever edit somebody thought was
        // worth keeping. DATA_MODEL: never update the text in place.
        $this->workflow->recordVersion($instrument, $user, __('contributions.version_created'));

        return redirect()
            ->route('contributions.edit', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug])
            ->with('status', __('contributions.saved'));
    }

    public function update(Request $request, Instrument $instrument): RedirectResponse
    {
        $this->authoriseContributor($request, $instrument);

        abort_unless($instrument->isEditableByContributor(), 403);

        $data = $this->validated($request);

        $instrument->update([
            'sector_id' => $data['sector_id'],
            'instrument_type' => $data['instrument_type'],
            'reference_number' => $data['reference_number'],
            'enacted_on' => $data['enacted_on'] ?? null,
            'effective_from' => $data['effective_from'] ?? null,
            'status' => $data['status'],
            'superseded_by_id' => $data['superseded_by_id'] ?? null,
        ]);

        $this->writeText($instrument, $data);
        $this->attach($request, $instrument);
        $this->workflow->recordVersion($instrument, $request->user(), __('contributions.version_edited'));

        return back()->with('status', __('contributions.saved'));
    }

    /** Send it to the Secretariat. */
    public function submit(Request $request, Instrument $instrument): RedirectResponse
    {
        $this->authoriseContributor($request, $instrument);

        $this->workflow->transition($instrument, ContributionState::Submitted, $request->user());

        return back()->with('status', __('contributions.submitted'));
    }

    /**
     * Whether this person may work on this contribution.
     *
     * A 404 rather than a 403 for another member state's, because the
     * request should not confirm that a draft exists in Mozambique.
     */
    private function authoriseContributor(Request $request, Instrument $instrument): void
    {
        $user = $request->user();

        abort_if($instrument->contribution_state === null, 404);
        abort_unless($user->country_id !== null && $instrument->country_id === $user->country_id, 404);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $rules = [
            'sector_id' => ['required', 'exists:sectors,id'],
            'instrument_type' => ['required', 'string', 'in:'.implode(',', array_column(InstrumentType::cases(), 'value'))],
            'status' => ['required', 'string', 'in:'.implode(',', array_column(InstrumentStatus::cases(), 'value'))],
            'reference_number' => ['required', 'string', 'max:120'],
            'enacted_on' => ['nullable', 'date'],
            'effective_from' => ['nullable', 'date'],
            'superseded_by_id' => ['nullable', 'exists:instruments,id'],
            'title_en' => ['required', 'string', 'max:255'],
            'summary_en' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:25600', 'mimetypes:application/pdf'],
        ];

        foreach (Locales::SUPPORTED as $locale) {
            if ($locale === 'en') {
                continue;
            }

            // English only is allowed. CLAUDE.md: requiring all three
            // blocks all publishing.
            $rules['title_'.$locale] = ['nullable', 'string', 'max:255'];
            $rules['summary_'.$locale] = ['nullable', 'string', 'max:2000'];
        }

        return $request->validate($rules);
    }

    /** @param array<string, mixed> $data */
    private function writeText(Instrument $instrument, array $data): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $instrument->setTranslation('title', $locale, $data['title_'.$locale] ?? null);
            $instrument->setTranslation('summary', $locale, $data['summary_'.$locale] ?? null);
        }
    }

    /**
     * The PDF.
     *
     * On the private disk, like every other document on this platform.
     * DATA_MODEL section 3: never serve a document by filesystem URL.
     */
    private function attach(Request $request, Instrument $instrument): void
    {
        if (! $request->hasFile('file')) {
            return;
        }

        $file = $request->file('file');
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $path = 'contributions/'.$instrument->id.'/'.$name.'-'.Str::random(8).'.pdf';

        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        Document::create([
            'documentable_type' => $instrument->getMorphClass(),
            'documentable_id' => $instrument->id,
            'country_id' => $instrument->country_id,
            'uploaded_by' => $request->user()->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => 'application/pdf',
            'size_bytes' => $file->getSize(),
            'locale' => app()->getLocale(),
            // Not public until the Secretariat validates the contribution.
            'clearance' => 'member',
            'is_sample' => false,
        ]);
    }

    private function slug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 180, '')) ?: 'instrument';
        $slug = $base;
        $suffix = 2;

        while (Instrument::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
