<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\Meeting;
use App\Support\PublishedCounts;

/**
 * One committee on the public site (29 October 2026): what it is for, the
 * documents it has published and its next meeting. Members-only material
 * stays in the member area; this reads only what the public library and
 * the events page already show.
 */
class CommitteePageController extends Controller
{
    public function show(Committee $committee)
    {
        abort_unless($committee->is_active, 404);

        $committee->load(['sector', 'chairAuthority.country', 'translations']);

        $publications = $committee->publications()
            ->where(PublishedCounts::listedScope())
            ->with(['translations', 'sector', 'documents'])
            ->orderByDesc('published_on')
            ->get();

        $next = Meeting::public()->where('committee_id', $committee->id)->upcoming()->first();
        $past = Meeting::public()->where('committee_id', $committee->id)->past()->limit(6)->get();

        $others = Committee::where('is_active', true)
            ->where('id', '!=', $committee->id)
            ->orderBy('name')
            ->get();

        return view('pages.committees.show', compact('committee', 'publications', 'next', 'past', 'others'));
    }
}
