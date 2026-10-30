@extends('layouts.admin')
@section('title', __('admin.audit_title'))
@section('note', __('admin.audit_note'))

@section('actions')
    <a class="a-btn a-btn--secondary" href="{{ route('admin.audit.export', ['locale' => app()->getLocale()] + array_filter($filters, fn ($v) => filled($v))) }}">{{ __('m3_retaud.audit_export') }}</a>
@endsection

{{--
    Day 3 morning (1g): each entry shows what changed as a small table of
    field, before and after, in the labels the forms use. Long values are
    shortened with the full text one click away; secrets are masked
    (App\Support\AuditChanges). The object links to the record while it
    exists and says "deleted" once it does not.
--}}
@section('content')
    <p class="a-hint">{{ __('admin.audit_intro') }}</p>
    {{-- Day 3: auditlock. The retention, from config/crasa.php. --}}
    @php
        $years = (int) config('crasa.audit_archive_years');
        $yearsText = \Illuminate\Support\Facades\Lang::has('d3_auditlock.years.'.$years) ? __('d3_auditlock.years.'.$years) : __('d3_auditlock.years_n', ['n' => $years]);
    @endphp
    <p class="a-hint" data-audit-retention>{{ __('d3_auditlock.retention', ['months' => (int) config('crasa.audit_retention_months'), 'years' => $yearsText]) }}</p>

    <form method="GET" class="a-filters">
        <div class="a-field">
            <label class="a-label" for="user">{{ __('m3_retaud.audit_user') }}</label>
            <select class="a-input" id="user" name="user">
        <option value="">{{ __('news.all') }}</option>
        @foreach ($actors as $actor)
            <option value="{{ $actor->id }}" @selected(($filters['user'] ?? null) == $actor->id)>{{ $actor->anonymised_at ? __('fa.former_user') : $actor->name }}</option>
        @endforeach
            </select>
        </div>
        <div class="a-field">
            <label class="a-label" for="action">{{ __('admin.action') }}</label>
            <select class="a-input" id="action" name="action">
        <option value="">{{ __('news.all') }}</option>
        @foreach ($actions as $action)
            <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ \App\Models\AuditLog::actionLabel($action) }}</option>
        @endforeach
            </select>
        </div>
        <div class="a-field">
            <label class="a-label" for="type">{{ __('admin.record_type') }}</label>
            <select class="a-input" id="type" name="type">
        <option value="">{{ __('news.all') }}</option>
        @foreach ($types as $type)
            <option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ \App\Models\AuditLog::typeLabel($type) }}</option>
        @endforeach
            </select>
        </div>
        <div class="a-field">
            <label class="a-label" for="object">{{ __('m3_retaud.audit_object_search') }}</label>
            <input class="a-input" type="search" id="object" name="object" value="{{ $filters['object'] ?? '' }}" aria-describedby="object-hint">
            <span class="a-hint" id="object-hint">{{ __('m3_retaud.audit_object_search_hint') }}</span>
        </div>
        <div class="a-field">
            <label class="a-label" for="from">{{ __('admin.from') }}</label>
            <input class="a-input" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="a-field">
            <label class="a-label" for="to">{{ __('admin.to') }}</label>
            <input class="a-input" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
        </div>
        <button type="submit" class="a-btn a-btn--secondary">{{ __('admin.apply_filters') }}</button>
    </form>

    <p class="c-results" role="status">{{ trans_choice('admin.entry_count', $entries->total(), ['count' => $entries->total()]) }}</p>
    <p class="a-hint">{{ __('m3_retaud.audit_export_note') }}</p>

    @if ($entries->isEmpty())
        @include('blocks._empty', ['title' => __('admin.audit_empty_title'), 'body' => __('admin.audit_empty_body')])
    @else
        <div class="a-tablewrap">
            <table class="a-table">
        <caption class="c-sr">{{ __('admin.audit_title') }}</caption>
        <thead>
            <tr>
                <th scope="col">{{ __('admin.when') }}</th>
                <th scope="col">{{ __('admin.actor') }}</th>
                <th scope="col">{{ __('admin.action') }}</th>
                <th scope="col">{{ __('m3_retaud.audit_object') }}</th>
                <th scope="col">{{ __('m3_retaud.audit_changes') }}</th>
                <th scope="col">{{ __('admin.address') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                @php
                    $subject = \App\Support\AuditChanges::subject($entry, $subjects);
                    $rows = \App\Support\AuditChanges::rows($entry);
                @endphp
                <tr>
            <td class="c-num">{{ $entry->created_at?->displayed()->format('Y-m-d H:i') }}</td>
            <td>{{ $entry->user?->anonymised_at ? __('fa.former_user') : ($entry->user?->name ?? __('admin.no_actor')) }}</td>
            <td>{{ \App\Models\AuditLog::actionLabel($entry->action) }}</td>
            <td>
                @if ($subject === null)
                    –
                @elseif ($subject['url'])
                    <a href="{{ $subject['url'] }}">{{ $subject['label'] }}</a>
                @else
                    {{ $subject['label'] }}@if ($subject['deleted'])<span class="a-table__sub">{{ __('m3_retaud.audit_object_deleted') }}</span>@endif
                @endif
            </td>
            <td>
                @if ($rows === [])
                    <span data-quiet>{{ __('m3_retaud.audit_no_changes') }}</span>
                @else
                    <table class="a-auditdiff">
                        <caption class="c-sr">{{ __('m3_retaud.audit_changes') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('m3_retaud.audit_field') }}</th>
                                <th scope="col">{{ __('m3_retaud.audit_before') }}</th>
                                <th scope="col">{{ __('m3_retaud.audit_after') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <th scope="row">{{ $row['field'] }}</th>
                                    @foreach (['before', 'after'] as $side)
                                        <td>
                                            @if ($row['masked'])
                                                <span class="a-auditdiff__quiet">{{ __('m3_retaud.audit_masked') }}</span>
                                            @elseif ($row[$side] === null)
                                                <span class="a-auditdiff__quiet">{{ __('m3_retaud.audit_empty_value') }}</span>
                                            @elseif (mb_strlen($row[$side]) > \App\Support\AuditChanges::SHORT)
                                                <details class="a-auditdiff__long">
                                                    <summary>{{ \App\Support\AuditChanges::short($row[$side]) }} <span class="a-auditdiff__more">{{ __('m3_retaud.audit_show_full') }}</span></summary>
                                                    <div class="a-auditdiff__full">{{ $row[$side] }}</div>
                                                </details>
                                            @else
                                                {{ $row[$side] }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
            <td class="c-num">{{ $entry->ip_address ?? '–' }}</td>
                </tr>
            @endforeach
        </tbody>
            </table>
        </div>
        <div class="a-pager">{{ $entries->links() }}</div>
    @endif
@endsection
