<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditChanges;
use App\Support\SpreadsheetCell;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The audit trail, readable by administrators and writable by nobody.
 *
 * The list and its CSV export read the same filters (user, action, object
 * type, object id or name, date from and to), so the file holds what the
 * screen showed. The export is itself an audit row.
 */
class AuditLogController extends Controller
{
    private const FILTERS = ['user', 'action', 'type', 'object', 'from', 'to'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);

        $entries = $this->filtered($request)
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('pages.admin.audit.index', [
            'entries' => $entries,
            'subjects' => AuditChanges::subjects($entries),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'types' => $this->types()->map(fn ($t) => class_basename($t))->unique()->sort()->values(),
            'actors' => User::whereIn('id', AuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
                ->orderBy('name')->get(),
            'filters' => $request->only(self::FILTERS),
        ]);
    }

    /** Day 3 morning (1g): the filtered list as CSV, one line per changed field. */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = $this->filtered($request)->with('user')->orderByDesc('created_at')->orderByDesc('id');
        $count = (clone $query)->count();

        AuditLog::record('audit_exported', null, [
            'filters' => array_filter($request->only(self::FILTERS), fn ($v) => filled($v)),
            'rows' => $count,
        ], $request->user());

        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                __('m3_retaud.audit_csv_when'), __('m3_retaud.audit_csv_user'), __('m3_retaud.audit_csv_action'),
                __('m3_retaud.audit_csv_type'), __('m3_retaud.audit_csv_id'), __('m3_retaud.audit_object'),
                __('m3_retaud.audit_csv_field'), __('m3_retaud.audit_csv_before'), __('m3_retaud.audit_csv_after'),
                __('m3_retaud.audit_csv_address'),
            ]);

            $query->chunk(500, function ($entries) use ($out): void {
                $subjects = AuditChanges::subjects($entries);

                foreach ($entries as $entry) {
                    $subject = AuditChanges::subject($entry, $subjects);
                    $lead = [
                        $entry->created_at?->displayed()->format('Y-m-d H:i:s'),
                        $entry->user?->anonymised_at ? __('fa.former_user') : ($entry->user?->name ?? __('admin.no_actor')),
                        AuditLog::actionLabel($entry->action),
                        $entry->auditable_type ? AuditLog::typeLabel($entry->auditable_type) : '',
                        $entry->auditable_id,
                        $subject ? ($subject['deleted'] ? $subject['label'].' ('.__('m3_retaud.audit_object_deleted').')' : $subject['label']) : '',
                    ];

                    $rows = AuditChanges::rows($entry) ?: [['field' => '', 'before' => null, 'after' => null, 'masked' => false]];

                    foreach ($rows as $row) {
                        $masked = $row['masked'] ? __('m3_retaud.audit_masked') : null;
                        fputcsv($out, SpreadsheetCell::row([
                            ...$lead,
                            $row['field'],
                            $masked ?? $row['before'] ?? '',
                            $masked ?? $row['after'] ?? '',
                            $entry->ip_address ?? '',
                        ]));
                    }
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The audit rows the filters ask for. */
    private function filtered(Request $request): Builder
    {
        $type = $request->string('type')->value();
        $object = trim($request->string('object')->value());

        return AuditLog::query()
            ->when($request->integer('user'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->string('action')->value(), fn ($q, $v) => $q->where('action', $v))
            ->when($type, fn ($q, $v) => $q->where('auditable_type', 'like', '%'.$v))
            ->when($object !== '', function ($q) use ($object, $type) {
                if (ctype_digit($object)) {
                    return $q->where('auditable_id', (int) $object);
                }

                $types = $this->types()->when($type, fn ($c) => $c->filter(fn ($t) => class_basename($t) === $type))->values();
                $pairs = AuditChanges::search($object, $types);

                if ($pairs === []) {
                    return $q->whereRaw('1 = 0');
                }

                return $q->where(function ($w) use ($pairs) {
                    foreach ($pairs as $class => $ids) {
                        $w->orWhere(fn ($p) => $p->where('auditable_type', $class)->whereIn('auditable_id', $ids));
                    }
                });
            })
            ->when($this->day($request, 'from'), fn ($q, $v) => $q->where('created_at', '>=', $v->startOfDay()))
            ->when($this->day($request, 'to'), fn ($q, $v) => $q->where('created_at', '<=', $v->endOfDay()));
    }

    /** A date filter, or nothing when it is not a date: a typo is not a 500. */
    private function day(Request $request, string $key): ?Carbon
    {
        $value = $request->string($key)->value();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function types()
    {
        return AuditLog::query()->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type');
    }
}
