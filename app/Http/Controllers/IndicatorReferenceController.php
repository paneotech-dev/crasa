<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The indicator taxonomy, with every definition and its source.
 * RP-11, extranet item 11.
 *
 * WHY THIS PAGE EXISTS. DATA_MODEL says an indicator's definition must be
 * "precise enough that fourteen regulators report the same thing". The
 * definition was already stored and the only place it appeared was as a
 * hint under a form field, truncated by the layout, where a regulator part
 * way through a return cannot read it without losing their place. If
 * fourteen regulators are to report the same thing they need somewhere to
 * read what the thing is.
 *
 * Every e-Form field links here, to its own indicator, so the answer is one
 * click from the question.
 */
class IndicatorReferenceController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->query('category');

        $indicators = Indicator::active()
            ->when($category, fn ($q, $value) => $q->where('category', $value))
            ->with('sector')
            ->orderBy('category')
            ->orderBy('display_order')
            ->get();

        return view('pages.extranet.indicators', [
            'indicators' => $indicators->groupBy('category'),
            'categories' => Indicator::active()->distinct()->orderBy('category')->pluck('category'),
            'category' => $category,
        ]);
    }
}
