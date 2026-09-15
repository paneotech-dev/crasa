<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Announcements as a member state reads them. Extranet item 10, RP-05.
 *
 * MEMBERS ONLY, AND THE SCOPE SAYS SO RATHER THAN THE ROUTE. `visibleTo`
 * is the only way this controller reads the model, so an announcement
 * narrowed to the postal committee is not listed, not linked and not
 * reachable by guessing a slug for anyone without that seat. A route
 * middleware would have covered only the first.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.extranet.announcements', [
            'announcements' => Announcement::live()
                ->visibleTo($request->user())
                ->with(['translations', 'sector', 'committee', 'author'])
                ->orderByDesc('published_at')
                ->paginate(20),
        ]);
    }

    public function show(Request $request, string $announcement): View
    {
        $record = Announcement::live()
            ->visibleTo($request->user())
            ->with(['translations', 'sector', 'committee', 'author'])
            ->where('slug', $announcement)
            ->firstOrFail();

        return view('pages.extranet.announcement', ['announcement' => $record]);
    }
}
