<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Support\Locales;
use App\Support\TextDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * An instrument's history, and what changed between two versions.
 *
 * RP-07 gives the data: every edit snapshots the full set of translatable
 * fields in every language into a version row, and has since the schema was
 * written. Nothing read it. An editor could see that an instrument had nine
 * versions and could not see what any of them said, which makes the history
 * a count rather than a record.
 *
 * TWO VERSIONS, SIDE BY SIDE, WORD BY WORD. A line diff on regulatory prose
 * marks a whole paragraph changed because one date moved in it. "What
 * exactly changed in clause 4" is the question a regulator asks, and a
 * paragraph highlighted end to end does not answer it.
 *
 * The comparison defaults to a version against the one before it, which is
 * what somebody opening a history wants nine times out of ten, and any two
 * can be chosen.
 */
class InstrumentVersionController extends Controller
{
    public function index(Instrument $instrument): View
    {
        Gate::authorize('view', $instrument);

        return view('pages.admin.instruments.versions', [
            'instrument' => $instrument->load('translations'),
            'versions' => $instrument->versions()->with('author:id,name')->get(),
        ]);
    }

    public function diff(Request $request, Instrument $instrument): View
    {
        Gate::authorize('view', $instrument);

        $versions = $instrument->versions()->with('author:id,name')->get();

        abort_if($versions->count() < 2, 404, __('admin.diff_needs_two'));

        /*
         * Newest against the one before it by default. Both are read from
         * this instrument's own versions rather than by id alone, so a
         * version number from another instrument cannot be compared here.
         */
        $after = $this->pick($versions, $request->integer('to')) ?? $versions->first();
        $before = $this->pick($versions, $request->integer('from'))
            ?? $versions->firstWhere('version_number', '<', $after->version_number)
            ?? $versions->last();

        $locale = Locales::isSupported($request->query('lang')) ? $request->query('lang') : Locales::FALLBACK;

        $fields = [];

        foreach ($instrument->translatableFields() as $field) {
            $fields[$field] = TextDiff::between(
                $before->valueFor($field, $locale),
                $after->valueFor($field, $locale),
            );
        }

        return view('pages.admin.instruments.diff', [
            'instrument' => $instrument->load('translations'),
            'versions' => $versions,
            'before' => $before,
            'after' => $after,
            'fields' => $fields,
            'locale' => $locale,
            'locales' => Locales::SUPPORTED,
        ]);
    }

    /** @param \Illuminate\Support\Collection<int, InstrumentVersion> $versions */
    private function pick($versions, int $id): ?InstrumentVersion
    {
        return $id > 0 ? $versions->firstWhere('id', $id) : null;
    }
}
