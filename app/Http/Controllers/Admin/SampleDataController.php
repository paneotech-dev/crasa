<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Support\SampleData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * What demonstration data is still in the database.
 *
 * The dashboard carries the total. This is the breakdown, and it exists
 * because "196 rows" is a number the Secretariat cannot act on: which
 * tables, and how many in each, is what tells them whether the strip is
 * safe to run yet.
 *
 * THERE IS NO PURGE BUTTON, AND THAT IS DELIBERATE. The strip deletes
 * two hundred rows across fourteen tables in one transaction and cannot be
 * undone. It is a console command run by somebody at a terminal who can
 * read what it says before confirming, not a button in a browser that a
 * mis-click reaches. The page states the command.
 */
class SampleDataController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Permission::ManageSettings->value);

        return view('pages.admin.samples.index', [
            'counts' => SampleData::counts(),
            'total' => SampleData::total(),
        ]);
    }
}
