<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Putting an informational alert away for the session.
 *
 * Per session and not per account: these say what is true right now, and a
 * reader who dismissed the sample-data count in March should meet it again
 * in April rather than never again. The account-level dismissal that does
 * persist is the help hints, which are a different thing.
 *
 * THE MAIL WARNING IS NOT ON THIS LIST AND CANNOT BE. Mail that goes
 * nowhere produces no error anywhere, and an alert somebody can silence is
 * an alert somebody silences.
 */
class DismissController extends Controller
{
    private const DISMISSABLE = ['samples', 'review', 'welcome'];

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('enter-back-office');

        $data = $request->validate([
            'notice' => ['required', Rule::in(self::DISMISSABLE)],
        ]);

        $request->session()->put('dismissed.'.$data['notice'], true);

        return back();
    }
}
