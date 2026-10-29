<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Support\MemberHome;
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
        $user = $request->user();
        $announcements = Announcement::live()
            ->visibleTo($user)
            ->with(['translations', 'sector', 'committee', 'author', 'documents'])
            ->orderByDesc('published_at')
            ->paginate(20);
        $read = MemberHome::readIds($user);

        // Grouped by the month of publication, newest first (Day 3, 8c).
        $months = collect($announcements->items())
            ->map(fn (Announcement $a) => MemberHome::notice($a, $read, $user))
            ->groupBy(fn (array $n) => $n['model']->published_at?->displayed()->format('Y-m') ?? '');

        return view('pages.extranet.announcements', [
            'announcements' => $announcements,
            'months' => $months,
            'unread' => collect($announcements->items())->reject(fn (Announcement $a) => $read->contains($a->id))->count(),
        ]);
    }

    public function show(Request $request, string $announcement): View
    {
        $record = Announcement::live()
            ->visibleTo($request->user())
            ->with(['translations', 'sector', 'committee', 'author'])
            ->where('slug', $announcement)
            ->firstOrFail();

        MemberHome::markRead($request->user(), $record);

        return view('pages.extranet.announcement', ['announcement' => $record]);
    }
}
