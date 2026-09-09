<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\IndicatorValue;
use App\Models\Publication;
use App\Services\MapExport;
use App\Support\MapView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * One member state.
 *
 * The map is a navigation surface, and navigation has to arrive somewhere.
 * Before this page a member state's shape linked to an anchor on the same
 * page, which is a link that does not go anywhere: the reader clicks a
 * country and the page scrolls a little. That is the difference between an
 * illustration and an interface.
 *
 * Everything on this page is a fact the platform already holds. Nothing is
 * written for the page, because a member state entry that carries invented
 * prose about a national regulator is the one thing on this platform a
 * regulator would notice first.
 */
class MemberController extends Controller
{
    /**
     * The dropdown beside the map. A GET form cannot put its value into a
     * path segment, so it arrives here as ?iso= and leaves as a redirect to
     * the canonical URL.
     *
     * An unknown code goes back to the membership page rather than to a 404:
     * the reader chose from a list we rendered, so a value we do not
     * recognise is our problem and not theirs.
     */
    public function redirectToState(Request $request): RedirectResponse
    {
        $iso = strtolower((string) $request->query('iso'));
        $locale = app()->getLocale();

        return Country::where('iso_code', $iso)->exists()
            ? redirect()->route('members.show', ['locale' => $locale, 'iso' => $iso])
            : redirect()->route('members', ['locale' => $locale]);
    }

    /**
     * The shaded map as a document. Same pipeline as the comparator's
     * exports: a branded SVG through Imagick, no headless browser.
     */
    public function export(Request $request, MapExport $export, string $format): Response
    {
        abort_unless(in_array($format, ['png', 'pdf'], true), 404);

        $view = MapView::resolve(
            Country::with(['authority.sectors'])->get(),
            $request->query('indicator')
        );

        $permalink = route('members', array_filter([
            'locale' => app()->getLocale(),
            'indicator' => $view->selection ?: null,
        ]));

        $slug = $view->selection ?: 'membership';

        return response(
            $format === 'png' ? $export->png($view, $permalink) : $export->pdf($view, $permalink),
            200,
            [
                'Content-Type' => $format === 'png' ? 'image/png' : 'application/pdf',
                'Content-Disposition' => 'attachment; filename="crasa-map-'.$slug.'.'.$format.'"',
            ]
        );
    }

    public function show(string $iso): View
    {
        $country = Country::query()
            ->with(['authority.sectors', 'authorities', 'committees'])
            ->where('iso_code', strtolower($iso))
            ->firstOrFail();

        // Figures this state has filed, most recent period first. Only what
        // has a value: an empty row here would read as a gap in the register
        // rather than a gap in the filing.
        $figures = IndicatorValue::query()
            ->with('indicator')
            ->where('country_id', $country->id)
            ->whereHas('indicator', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('period')
            ->get()
            ->filter(fn (IndicatorValue $v) => $v->hasValue())
            ->take(12);

        return view('pages.members.show', [
            'country' => $country,
            'authority' => $country->authority,
            'figures' => $figures,
            'committees' => $country->committees->sortBy('name'),
            'publications' => Publication::query()
                ->listed()
                ->current()
                ->where('country_id', $country->id)
                ->orderByDesc('published_on')
                ->take(5)
                ->get(),
        ]);
    }
}
