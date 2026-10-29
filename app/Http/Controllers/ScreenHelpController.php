<?php

namespace App\Http\Controllers;

use App\Support\ScreenHelp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * The help page for one screen (client brief, item 7). The help icon in the
 * top bar of the back office and the member area links here with the
 * current route name. Signed in only; the help for a back-office screen is
 * for the accounts that may enter the back office.
 */
class ScreenHelpController extends Controller
{
    public function show(Request $request, string $screen): View
    {
        $route = Route::getRoutes()->getByName($screen);

        abort_if($route === null || ! in_array('GET', $route->methods(), true), 404);

        if (ScreenHelp::section($screen) === ScreenHelp::BACK_OFFICE) {
            Gate::authorize('enter-back-office');
        }

        return view('pages.help.screen', [
            'screen' => $screen,
            'help' => ScreenHelp::for($screen),
            'back' => $this->back($request, $screen, $route),
            'guide' => $this->guide($request),
        ]);
    }

    /** Where "Back to the screen" goes: the page the reader came from, on this host. */
    private function back(Request $request, string $screen, \Illuminate\Routing\Route $route): ?string
    {
        $from = $request->query('from');

        if (is_string($from) && preg_match('~^/[^/\\\\]~', $from)) {
            return url($from);
        }

        $required = array_diff($route->parameterNames(), ['locale']);

        return $required === [] ? route($screen, ['locale' => app()->getLocale()]) : null;
    }

    private function guide(Request $request): ?string
    {
        $url = config('crasa.role_guide_url');

        if (! $url) {
            return null;
        }

        $anchor = [
            'administrator' => 'admin', 'editor' => 'editor', 'member_state_rep' => 'member',
            'committee_member' => 'committee', 'viewer' => 'everyone',
        ][$request->user()->role->value] ?? 'everyone';

        return $url.'#'.$anchor;
    }
}
