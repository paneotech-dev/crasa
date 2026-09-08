@extends('layouts.public')
@section('title', __('admin.audit_title'))
@section('content')
    <div class="c-section">
        <div class="c-page">
            <h1>{{ __('admin.audit_title') }}</h1>
            <p class="c-lead">{{ __('admin.audit_intro') }}</p>

            <form method="GET" class="c-form c-form--filters">
                <div class="c-field">
                    <label class="c-label" for="user">{{ __('admin.actor') }}</label>
                    <select class="c-input" id="user" name="user">
                        <option value="">{{ __('news.all') }}</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}" @selected(($filters['user'] ?? null) == $actor->id)>{{ $actor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="c-field">
                    <label class="c-label" for="action">{{ __('admin.action') }}</label>
                    <select class="c-input" id="action" name="action">
                        <option value="">{{ __('news.all') }}</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="c-field">
                    <label class="c-label" for="type">{{ __('admin.record_type') }}</label>
                    <select class="c-input" id="type" name="type">
                        <option value="">{{ __('news.all') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="c-field">
                    <label class="c-label" for="from">{{ __('admin.from') }}</label>
                    <input class="c-input" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="c-field">
                    <label class="c-label" for="to">{{ __('admin.to') }}</label>
                    <input class="c-input" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}">
                </div>
                <button type="submit" class="c-btn c-btn--secondary">{{ __('admin.apply_filters') }}</button>
            </form>

            <p class="c-results" role="status">{{ trans_choice('admin.entry_count', $entries->total(), ['count' => $entries->total()]) }}</p>

            @if ($entries->isEmpty())
                @include('blocks._empty', ['title' => __('admin.audit_empty_title'), 'body' => __('admin.audit_empty_body')])
            @else
                <div class="c-scroll">
                    <table class="c-table">
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
                                    <td class="c-num">{{ $entry->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $entry->user?->name ?? __('admin.no_actor') }}</td>
                                    <td>{{ $entry->action }}</td>
                                    <td>{{ $entry->auditable_type ? class_basename($entry->auditable_type).' #'.$entry->auditable_id : '—' }}</td>
                                    <td class="c-num">{{ $entry->ip_address ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="c-pagination">{{ $entries->links() }}</div>
            @endif
        </div>
    </div>
@endsection
