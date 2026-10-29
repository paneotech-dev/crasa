@extends('layouts.admin')

@section('title', __('e.admin_title'))
@section('note', __('e.admin_lead'))

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/events.css') }}">
@endpush
@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/events-admin.js') }}" defer></script>
@endpush

@section('crumbs')
    <li aria-current="page">{{ __('e.admin_title') }}</li>
@endsection

@section('actions')
    @can('create', \App\Models\Meeting::class)
        <a class="a-btn a-btn--primary" href="{{ route('admin.meetings.create', ['locale' => app()->getLocale()]) }}">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>{{ __('e.admin_new') }}</a>
    @endcan
@endsection

@section('content')
    @php($locale = app()->getLocale())
    @if (session('status'))
        <div class="a-notice a-notice--success" role="status">{{ session('status') }}</div>
    @endif

    @if ($upcoming->isEmpty() && $past->isEmpty())
        <div class="a-card"><div class="a-empty"><div class="a-empty__title">{{ __('admin.meetings_empty_title') }}</div><div class="a-empty__body">{{ __('e.no_events') }}</div></div></div>
    @endif

    @foreach (['upcoming' => $upcoming, 'past' => $past] as $key => $meetings)
        @continue($meetings->isEmpty())
        <section class="a-card a-card--flush" aria-labelledby="ev-{{ $key }}">
            <div class="a-card__head"><h2 id="ev-{{ $key }}">{{ __('admin.meetings_'.$key) }}</h2><span class="a-card__meta">{{ $meetings->count() }}</span></div>
            <div class="a-tablewrap">
                <table class="a-table e-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('e.col_event') }}</th>
                            <th scope="col">{{ __('e.col_dates') }}</th>
                            <th scope="col">{{ __('e.col_status') }}</th>
                            <th scope="col" class="a-table__num">{{ __('admin.meetings_col_delegates') }}</th>
                            <th scope="col">{{ __('e.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($meetings as $meeting)
                            @php($g = $meeting->group())
                            <tr>
                                <th scope="row">
                                    <span class="e-admin-row">
                                        @include('pages.events._badge', ['group' => $g])
                                        <span>
                                            <a href="{{ route('admin.meetings.edit', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ $meeting->title }}</a>
                                            <span class="a-table__sub">
                                                {{ collect([$meeting->location, $meeting->clearance === \App\Enums\Clearance::Public ? __('e.visibility_public') : __('e.visibility_members')])->filter()->implode(', ') }}
                                            </span>
                                            @if ($meeting->source === 'newsroom')
                                                <span class="e-admin-source">{{ __('e.source_newsroom') }}</span>
                                            @endif
                                        </span>
                                    </span>
                                </th>
                                <td>{{ $meeting->dateRange() }}</td>
                                <td>
                                    @if ($meeting->isCancelled())
                                        <span class="a-pill" data-status="rejected">{{ __('e.status_cancelled') }}</span>
                                    @elseif ($meeting->isPostponed())
                                        <span class="a-pill" data-status="pending">{{ __('e.status_postponed') }}</span>
                                    @else
                                        <span class="a-pill" data-status="validated">{{ __('e.status_scheduled') }}</span>
                                    @endif
                                </td>
                                <td class="a-table__num"><a href="{{ route('admin.delegates', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ number_format($meeting->delegate_registrations_count) }}<span class="c-sr"> {{ __('e.admin_delegates') }}, {{ $meeting->title }}</span></a></td>
                                <td>
                                    <span class="e-admin-actions">
                                        @can('edit', $meeting)
                                            <a class="a-btn a-btn--secondary a-btn--small" href="{{ route('admin.meetings.edit', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('e.admin_edit') }}<span class="c-sr"> {{ $meeting->title }}</span></a>
                                        @endcan
                                        {{-- The public page answers the meeting policy's view rule; link it only for a reader it will answer. --}}
                                        @can('view', $meeting)
                                            <a class="a-btn a-btn--secondary a-btn--small" href="{{ route('events.show', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('e.admin_preview') }}<span class="c-sr"> {{ $meeting->title }}</span></a>
                                        @endcan
                                        @can('delete', $meeting)
                                            <a class="a-btn a-btn--danger a-btn--small" href="{{ route('admin.meetings.delete', ['locale' => $locale, 'meeting' => $meeting->slug]) }}"
                                               data-delete-event data-title="{{ $meeting->title }}" data-action="{{ route('admin.meetings.destroy', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('e.admin_delete') }}<span class="c-sr"> {{ $meeting->title }}</span></a>
                                        @endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    {{-- One confirmation for every row. showModal() traps focus and closes on Escape;
         events-admin.js fills the title and the address and returns focus to the row. --}}
    <dialog class="a-modal e-admin-dialog" id="event-delete" aria-labelledby="event-delete-title" aria-describedby="event-delete-body">
        <form class="a-modal__inner" method="POST" action="" data-delete-form>
            @csrf
            @method('DELETE')
            <h2 id="event-delete-title">{{ __('e.admin_delete_title') }}</h2>
            <p id="event-delete-body" data-delete-body data-template="{{ __('e.admin_delete_body', ['title' => '__TITLE__']) }}"></p>
            <div class="a-form__actions">
                <button type="button" class="a-btn a-btn--secondary" data-modal-close autofocus>{{ __('e.admin_cancel') }}</button>
                <button type="submit" class="a-btn a-btn--danger">{{ __('e.admin_delete_confirm') }}</button>
            </div>
        </form>
    </dialog>
@endsection
