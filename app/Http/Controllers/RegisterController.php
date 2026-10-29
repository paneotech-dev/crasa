<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\Sector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The harmonised register, as a signed-in member reads it. RP-03.
 *
 * WHY THIS IS NOT COUNTRY-SCOPED. The register exists so that fourteen
 * regulators can read each other's instruments; a scope here would defeat
 * the comparator and the whole harmonisation mandate. Contributing and
 * editing are scoped, and reading is not. That distinction is the model's
 * own comment and it is the one thing about this page that must not drift.
 *
 * WHAT IT SHOWS. Validated contributions and the instruments the
 * Secretariat entered directly. A contribution still in draft or sitting
 * with the Secretariat is not in the register, which is what `published()`
 * now means.
 */
class RegisterController extends Controller
{
    public function index(Request $request): View
    {
        $sector = $request->query('sector');
        $country = $request->query('country');
        // In force by default; repealed instruments on request (UX-13).
        $includeRepealed = $request->boolean('repealed');

        return view('pages.extranet.register.index', [
            'includeRepealed' => $includeRepealed,
            'instruments' => Instrument::published()
                ->with(['translations', 'country', 'sector'])
                ->unless($includeRepealed, fn ($q) => $q->where('status', '!=', \App\Enums\InstrumentStatus::Repealed->value))
                ->when($sector, fn ($q, $slug) => $q->whereHas('sector', fn ($s) => $s->where('slug', $slug)))
                ->when($country, fn ($q, $iso) => $q->whereHas('country', fn ($c) => $c->where('iso_code', $iso)))
                ->orderBy('country_id')
                ->orderByDesc('enacted_on')
                ->paginate(25)
                ->withQueryString(),
            'sectors' => Sector::orderBy('name')->get(),
            'sector' => $sector,
        ]);
    }

    public function show(Request $request, Instrument $instrument): View
    {
        // A contribution that has not been validated is not in the
        // register, so it 404s here even though its own contributor can
        // still open it through the contributions screen.
        abort_unless(
            Instrument::published()->whereKey($instrument->getKey())->exists(),
            404
        );

        return view('pages.extranet.register.show', [
            'instrument' => $instrument->load(['translations', 'country', 'sector', 'documents']),
            'documents' => $instrument->documents->filter(fn ($d) => Gate::allows('view', $d))->values(),
        ]);
    }
}
