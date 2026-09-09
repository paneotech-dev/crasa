<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\IndicatorValue;
use App\Models\Publication;
use Illuminate\Contracts\View\View;

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
