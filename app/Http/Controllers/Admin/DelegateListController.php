<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Secretariat's delegate list, WS-07.
 *
 * A plain sortable list and a CSV export. Deliberately plain: this is the
 * collecting half, and the Event Management Platform is where the list
 * becomes a schedule. The export exists precisely so the list can leave
 * this platform for the one that does that work.
 *
 * There is no grouping by arrival window, no allocation, no reminder and no
 * status workflow, because each of those is the managing half.
 */
class DelegateListController extends Controller
{
    private const SORTS = ['delegate_name', 'country_id', 'arrival_on', 'departure_on'];

    public function index(Request $request, Meeting $meeting): View
    {
        Gate::authorize(Permission::ManageMeetings->value);

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'arrival_on';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        return view('pages.admin.delegates.index', [
            'meeting' => $meeting,
            'registrations' => DelegateRegistration::query()
                ->where('meeting_id', $meeting->id)
                ->with(['country', 'user'])
                ->orderBy($sort, $direction)
                ->get(),
            'sort' => $sort,
            'direction' => $direction,
            'sorts' => self::SORTS,
        ]);
    }

    /**
     * The export. This is the handover point to the Event Management
     * Platform: the website has collected the information and this is how
     * it leaves.
     */
    public function csv(Meeting $meeting): StreamedResponse
    {
        Gate::authorize(Permission::ManageMeetings->value);

        $registrations = DelegateRegistration::query()
            ->where('meeting_id', $meeting->id)
            ->with('country')
            ->orderBy('arrival_on')
            ->get();

        return response()->streamDownload(function () use ($registrations) {
            $handle = fopen('php://output', 'w');

            // The byte order mark, so Excel reads the accented delegate
            // names correctly rather than as mojibake.
            fwrite($handle, "\u{FEFF}");

            fputcsv($handle, [
                __('delegates.col_name'), __('delegates.col_email'), __('delegates.col_role'),
                __('admin.field_country'),
                __('delegates.col_arrival_point'), __('delegates.col_arrival_on'), __('delegates.col_arrival_at'),
                __('delegates.col_arrival_reference'),
                __('delegates.col_departure_on'), __('delegates.col_departure_at'),
                __('delegates.col_departure_reference'),
                __('delegates.col_notes'),
            ]);

            foreach ($registrations as $row) {
                fputcsv($handle, [
                    $row->delegate_name,
                    $row->delegate_email,
                    $row->delegate_role,
                    $row->country?->name,
                    $row->arrival_point,
                    $row->arrival_on?->toDateString(),
                    $row->arrival_at,
                    $row->arrival_reference,
                    $row->departure_on?->toDateString(),
                    $row->departure_at,
                    $row->departure_reference,
                    $row->notes,
                ]);
            }

            fclose($handle);
        }, 'crasa-delegates-'.$meeting->slug.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
