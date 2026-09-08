<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The audit trail, readable by administrators and writable by nobody.
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);

        $entries = AuditLog::query()
            ->with('user')
            ->when($request->integer('user'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->string('action')->value(), fn ($q, $v) => $q->where('action', $v))
            ->when($request->string('type')->value(), fn ($q, $v) => $q->where('auditable_type', 'like', '%'.$v))
            ->when($request->date('from'), fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->date('to'), fn ($q, $v) => $q->where('created_at', '<=', $v->endOfDay()))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('pages.admin.audit.index', [
            'entries' => $entries,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'types' => AuditLog::query()->whereNotNull('auditable_type')
                ->distinct()->orderBy('auditable_type')->pluck('auditable_type')
                ->map(fn ($t) => class_basename($t))->unique()->values(),
            'actors' => User::whereIn('id', AuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
                ->orderBy('name')->get(),
            'filters' => $request->only(['user', 'action', 'type', 'from', 'to']),
        ]);
    }
}
