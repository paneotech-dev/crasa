<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Projects. WS-12's no-content-loss clause.
 *
 * The legacy site divides these into current and past behind a top-level
 * menu item, and WS-03's information architecture lists neither. They are
 * one collection in two states here, so a project that finishes keeps its
 * URL, its documents and its photograph. That is the difference between
 * archiving a project and losing it.
 */
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $state = in_array($request->query('state'), ['current', 'past'], true)
            ? $request->query('state')
            : null;

        $projects = Project::published()
            ->with(['translations', 'sector'])
            ->when($state !== null, fn ($query) => $query->where('state', $state))
            ->orderByRaw("CASE WHEN state = 'current' THEN 0 ELSE 1 END")
            ->orderByDesc('published_at')
            ->get();

        return view('pages.projects.index', [
            'projects' => $projects,
            'state' => $state,
            'currentCount' => Project::published()->where('state', 'current')->count(),
            'pastCount' => Project::published()->where('state', 'past')->count(),
        ]);
    }

    public function show(Project $project)
    {
        abort_unless(
            $project->status === 'published' && $project->published_at !== null && $project->published_at->isPast(),
            404
        );

        return view('pages.projects.show', [
            'project' => $project->load(['translations', 'sector', 'documents']),
            'related' => Project::published()
                ->whereKeyNot($project->getKey())
                ->with('translations')
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
        ]);
    }
}
