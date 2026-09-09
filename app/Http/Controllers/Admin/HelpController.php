<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Onboarding;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Onboarding.
 *
 * NO APPROVED SCREEN COVERS THIS, so it is built in the register the
 * screens establish rather than in the register the phrase "first-run tour"
 * usually implies. Logged in docs/OPEN_ITEMS.md.
 *
 * What that means concretely: it is not a modal overlay that dims the page
 * and walks a spotlight around it. That pattern traps focus, is nearly
 * impossible to operate with a screen reader, breaks the moment the layout
 * reflows, and cannot be re-read once dismissed. WCAG 2.1 AA is a
 * contractual acceptance criterion here.
 *
 * Instead: a panel at the top of the dashboard that says what the back
 * office is and where each section starts, dismissible per account and
 * permanently re-readable at this page; and inline help on the controls
 * that need it, drawn as a disclosure rather than a hover tooltip, because
 * a tooltip does not exist for anyone on a touch screen or a keyboard.
 */
class HelpController extends Controller
{
    public function index(): View
    {
        Gate::authorize('enter-back-office');

        return view('pages.admin.help', [
            'steps' => Onboarding::steps(),
        ]);
    }

    /**
     * Dismiss a piece of guidance for this account.
     *
     * Per account, not per browser: a Secretariat officer who signs in from
     * the office and from a laptop is one person who has read it once.
     */
    public function dismiss(Request $request): RedirectResponse
    {
        Gate::authorize('enter-back-office');

        $data = $request->validate([
            'hint' => ['required', Rule::in(Onboarding::keys())],
        ]);

        $request->user()->dismiss($data['hint']);

        return back()->with('status', __('admin.hint_dismissed'));
    }

    /** Bring every dismissed hint back. */
    public function reset(Request $request): RedirectResponse
    {
        Gate::authorize('enter-back-office');

        $request->user()->update(['dismissed_hints' => []]);

        return back()->with('status', __('admin.hints_restored'));
    }
}
