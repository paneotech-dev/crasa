<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in reader's own dashboard (F4).
 *
 * A reader who has a back office or a member area is sent to it, where
 * the same role blocks open the page. A viewer has neither, and this page
 * is theirs: the latest published items and the comparator.
 */
class MyDashboardController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->can('enter-back-office')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->can('enter-member-area')) {
            return redirect()->route('extranet.dashboard');
        }

        return view('pages.my-dashboard');
    }
}
