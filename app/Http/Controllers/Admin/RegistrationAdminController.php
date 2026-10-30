<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Events\RegistrationConfirmed;
use App\Http\Controllers\Controller;
use App\Mail\DelegateRegistrationFiled;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\DelegateRegistration;
use App\Models\Meeting;
use App\Support\MailStatus;
use App\Support\SimpleXlsx;
use App\Support\SpreadsheetCell;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Delegate registrations, every event on one screen.
 *
 * The chain this shows: a delegate files on the event's public form, the
 * row arrives here as filed, the Secretariat confirms or declines it, and
 * the list leaves through the export. The per-event roster stays where it
 * was; this is the way in that does not need the event's slug.
 *
 * Collecting only, as with the roster: nothing here books, allocates or
 * reminds.
 */
class RegistrationAdminController extends Controller
{
    public const STATUSES = ['filed', 'confirmed', 'declined'];

    public function index(Request $request): View
    {
        Gate::authorize(Permission::ManageMeetings->value);

        $filter = $this->filters($request);
        $registrations = $this->query($filter)->get()->groupBy('meeting_id');

        /*
         * Every event with a registration, and every event whose form is
         * open now even with none yet: the form link is what the
         * Secretariat sends to delegates, and an event with no filings is
         * exactly the one that needs it.
         */
        $meetings = Meeting::query()
            ->with('committee')
            ->when($filter['meeting'], fn ($q, $m) => $q->whereKey($m->id))
            ->where(fn ($q) => $q->whereIn('id', $registrations->keys())
                ->when(! $filter['status'] && ! $filter['country'], fn ($q) => $q->orWhere(fn ($open) => $open
                    ->where('date_unknown', false)->whereNotNull('starts_on')
                    // Still to start, or under way with its form open until
                    // an explicit closing date (the Lilongwe workshop).
                    ->where(fn ($when) => $when->where('starts_on', '>', \App\Support\LocalTime::today())
                        ->orWhereDate('registration_closes_on', '>=', \App\Support\LocalTime::today()))
                    ->where('status', '!=', 'cancelled'))))
            ->get()
            ->sortBy([
                fn ($a, $b) => (int) $a->hasEnded() <=> (int) $b->hasEnded(),
                fn ($a, $b) => $a->hasEnded()
                    ? strcmp((string) $b->starts_on?->toDateString(), (string) $a->starts_on?->toDateString())
                    : strcmp((string) $a->starts_on?->toDateString(), (string) $b->starts_on?->toDateString()),
            ])
            ->values();

        return view('pages.admin.registrations.index', [
            'meetings' => $meetings,
            'registrations' => $registrations,
            'filter' => $filter,
            'statuses' => self::STATUSES,
            'allMeetings' => Meeting::query()->whereHas('delegateRegistrations')->orderByDesc('starts_on')->get(['id', 'slug', 'title', 'starts_on']),
            'total' => $registrations->flatten()->count(),
            'mailOff' => MailStatus::isOff(),
            'countries' => Country::query()->whereIn('id', DelegateRegistration::query()->whereNotNull('country_id')->select('country_id'))->orderBy('name')->get(),
        ]);
    }

    /** Confirm, decline, or put back to filed. Confirming tells the notify side. */
    public function decide(Request $request, int $registration): RedirectResponse
    {
        Gate::authorize(Permission::ManageMeetings->value);

        $row = DelegateRegistration::query()->findOrFail($registration);
        $status = $request->validate(['status' => ['required', 'in:'.implode(',', self::STATUSES)]])['status'];
        $was = $row->status ?: 'filed';

        $row->update(['status' => $status]);

        if ($status === 'confirmed' && $was !== 'confirmed') {
            event(new RegistrationConfirmed($row));
        }

        return back()->with('status', __('d3_people.reg_updated', [
            'name' => $row->delegate_name, 'status' => __('d3_people.reg_'.$status),
        ]));
    }

    /**
     * Confirm or decline several filings at once (client item 1e). Each row
     * moves as the single action moves it: one audit row per registration
     * through the model, RegistrationConfirmed for each newly confirmed
     * one, and one audit row naming the batch.
     */
    public function bulk(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageMeetings->value);

        $data = $request->validate([
            'action' => ['required', 'in:confirmed,declined'],
            'ids' => ['required', 'array', 'max:2000'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => __('m3_deleg.bulk_none_selected'),
        ]);

        $moved = [];
        foreach (DelegateRegistration::query()->whereIn('id', $data['ids'])->get() as $row) {
            $was = $row->status ?: 'filed';
            if ($was === $data['action']) {
                continue;
            }

            $row->update(['status' => $data['action']]);
            $moved[] = $row->id;

            if ($data['action'] === 'confirmed') {
                event(new RegistrationConfirmed($row));
            }
        }

        AuditLog::record('registrations_bulk_'.($data['action'] === 'confirmed' ? 'confirmed' : 'declined'), null, [
            'ids' => $moved,
            'count' => count($moved),
        ], $request->user());

        return back()->with('status', trans_choice('m3_deleg.bulk_done_'.$data['action'], count($moved), ['count' => count($moved)]));
    }

    /**
     * Send the delegate their copy of what they filed again. Queued like
     * the first one; while outbound mail is off it is written to the log
     * and reaches nobody, and the message says so.
     */
    public function resend(Request $request, int $registration): RedirectResponse
    {
        Gate::authorize(Permission::ManageMeetings->value);

        $row = DelegateRegistration::query()->with(['meeting', 'country'])->findOrFail($registration);

        try {
            Mail::to($row->delegate_email)->queue(new DelegateRegistrationFiled($row));
        } catch (Throwable $e) {
            Log::error('A delegate registration copy could not be queued again.', [
                'registration' => $row->id, 'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['resend' => __('d3_people.resend_failed', ['name' => $row->delegate_name])]);
        }

        AuditLog::record('registration_resent', $row, null, $request->user());

        return back()->with('status', MailStatus::isOff()
            ? __('d3_people.resent_mail_off', ['name' => $row->delegate_name])
            : __('d3_people.resent', ['name' => $row->delegate_name, 'email' => $row->delegate_email]));
    }

    public function csv(Request $request): StreamedResponse
    {
        Gate::authorize(Permission::ManageMeetings->value);

        $filters = $this->filters($request);
        $rows = $this->rows($this->query($filters)->get());
        $this->audit($request, $filters, 'csv', count($rows) - 1);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // Byte order mark, so Excel reads accented names correctly.
            fwrite($handle, "\u{FEFF}");
            foreach ($rows as $row) {
                fputcsv($handle, SpreadsheetCell::row($row));
            }
            fclose($handle);
        }, $this->filename($request, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The same rows as the CSV, as a workbook, built as the comparator builds its own. */
    public function xlsx(Request $request): Response
    {
        Gate::authorize(Permission::ManageMeetings->value);

        $filters = $this->filters($request);
        $rows = $this->rows($this->query($filters)->get());
        $this->audit($request, $filters, 'xlsx', count($rows) - 1);

        return response(SimpleXlsx::build($rows, __('admin.nav_delegates')), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($request, 'xlsx').'"',
        ]);
    }

    /**
     * Who took the delegate list off the platform, for which event and how
     * many rows. The list carries names, addresses and travel details.
     *
     * @param  array{meeting: ?Meeting, status: ?string, country: ?Country}  $filters
     */
    private function audit(Request $request, array $filters, string $format, int $count): void
    {
        AuditLog::record('registrations_exported', $filters['meeting'], [
            'format' => $format,
            'meeting' => $filters['meeting']?->slug,
            'status' => $filters['status'],
            'country' => $filters['country']?->iso_code,
            'rows' => $count,
        ], $request->user());
    }

    /** @return array{meeting: ?Meeting, status: ?string, country: ?Country} */
    private function filters(Request $request): array
    {
        $slug = $request->string('meeting')->toString();
        $status = $request->string('status')->toString();
        $country = $request->string('country')->toString();

        return [
            'meeting' => $slug !== '' ? Meeting::where('slug', $slug)->first() : null,
            'status' => in_array($status, self::STATUSES, true) ? $status : null,
            'country' => $country !== '' ? Country::where('iso_code', $country)->first() : null,
        ];
    }

    private function query(array $filter)
    {
        return DelegateRegistration::query()
            ->with(['country', 'meeting'])
            ->when($filter['meeting'], fn ($q, $m) => $q->where('meeting_id', $m->id))
            ->when($filter['country'] ?? null, fn ($q, $c) => $q->where('country_id', $c->id))
            ->when($filter['status'], fn ($q, $s) => $s === 'filed'
                ? $q->where(fn ($w) => $w->where('status', 'filed')->orWhereNull('status'))
                : $q->where('status', $s))
            ->orderBy('delegate_name');
    }

    /** @return list<list<string|null>> */
    private function rows(Collection $registrations): array
    {
        $rows = [[
            __('d3_people.col_event'), __('d3_people.col_event_dates'),
            __('delegates.col_name'), __('delegates.col_email'), __('delegates.col_role'),
            __('admin.field_country'),
            __('m3_deleg.preferred_language'), __('m3_deleg.interpretation'),
            __('delegates.col_arrival_point'), __('delegates.col_arrival_on'), __('delegates.col_arrival_at'),
            __('delegates.col_arrival_reference'),
            __('delegates.col_departure_on'), __('delegates.col_departure_at'),
            __('delegates.col_departure_reference'),
            __('delegates.col_notes'),
            __('d3_people.status'),
            __('d3_people.col_filed_at'),
        ]];

        foreach ($registrations->sortBy(fn ($r) => [$r->meeting?->starts_on?->toDateString(), $r->delegate_name]) as $r) {
            $rows[] = [
                $r->meeting?->title,
                $r->meeting?->dateRange(),
                $r->delegate_name,
                $r->delegate_email,
                $r->delegate_role,
                $r->country?->name,
                $r->preferred_language ? __('m3_deleg.lang_'.$r->preferred_language) : null,
                $r->interpretation_into ? __('m3_deleg.interp_into_'.$r->interpretation_into) : __('m3_deleg.interp_none'),
                $r->arrival_point,
                $r->arrival_on?->toDateString(),
                $r->arrival_at,
                $r->arrival_reference,
                $r->departure_on?->toDateString(),
                $r->departure_at,
                $r->departure_reference,
                $r->notes,
                __('d3_people.reg_'.($r->status ?: 'filed')),
                $r->created_at?->toDateTimeString(),
            ];
        }

        return $rows;
    }

    private function filename(Request $request, string $extension): string
    {
        $slug = preg_replace('/[^a-z0-9-]/', '', $request->string('meeting')->lower()->toString());

        return 'crasa-delegate-registrations'.($slug !== '' ? '-'.$slug : '').'-'.now()->format('Y-m-d').'.'.$extension;
    }
}
