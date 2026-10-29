@extends('layouts.admin')
@section('title', __('admin.audit_title'))
@section('note', __('admin.audit_note'))

@section('content')
    <p class="a-hint">{{ __('admin.audit_intro') }}</p>

    <form method="GET" class="a-filters">
        <div class="a-field">
            <label class="a-label" for="user">{{ __('admin.actor') }}</label>
            <select class="a-input" id="user" name="user">
        <option value="">{{ __('news.all') }}</option>
        @foreach ($actors as $actor)
            <option value="{{ $actor->id }}" @selected(($filters['user'] ?? null) == $actor->id)>{{ $actor->name }}</option>
        @endforeach
            </select>
        </div>
        <div class="a-field">
            <label class="a-label" for="action">{{ __('admin.action') }}</label>
            <select class="a-input" id="action" name="action">
        <option value="">{{ __('news.all') }}</option>
        @foreach ($actions as $action)
            <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
        @endforeach
            </select>
        </div>
        <div class="a-field">
            <label class="a-label" for="type">{{ __('admin.record_type') }}</label>
            <select class="a-input" id="type" name="type">
        <option value="">{{ __('news.all') }}</option>
        @foreach ($types as $type)
            <option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ $type }}</option>
        @endforeach
            </select>
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
                <th scope="col">{{ __('admin.record') }}</th>
                <th scope="col">{{ __('admin.address') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
            <td class="c-num">{{ $entry->created_at?->displayed()->format('Y-m-d H:i') }}</td>
            <td>{{ $entry->user?->anonymised_at ? __('fa.former_user') : ($entry->user?->name ?? __('admin.no_actor')) }}</td>
            <td>{{ $entry->action }}</td>
            <td>{{ $entry->auditable_type ? class_basename($entry->auditable_type).' #'.$entry->auditable_id : '–' }}</td>
            <td class="c-num">{{ $entry->ip_address ?? '–' }}</td>
                </tr>
            @endforeach
        </tbody>
            </table>
        </div>
        <div class="a-pager">{{ $entries->links() }}</div>
    @endif
@endsection
