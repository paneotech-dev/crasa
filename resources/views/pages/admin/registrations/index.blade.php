@extends('layouts.admin')
@php
    $locale = app()->getLocale();
    $query = array_filter(['meeting' => $filter['meeting']?->slug, 'status' => $filter['status']]);
    $pill = ['filed' => 'pending', 'confirmed' => 'validated', 'declined' => 'rejected'];
@endphp
@section('title', __('admin.nav_delegates'))
@section('note', __('d3_people.reg_note'))

@section('crumbs')
    <li><a href="{{ route('admin.dashboard', ['locale' => $locale]) }}">{{ __('admin.dashboard') }}</a></li>
    <li aria-current="page">{{ __('admin.nav_delegates') }}</li>
@endsection

@section('actions')
    <a class="a-btn a-btn--secondary" href="{{ route('admin.registrations.csv', ['locale' => $locale] + $query) }}">{{ __('d3_people.export_csv') }}</a>
    <a class="a-btn a-btn--secondary" href="{{ route('admin.registrations.xlsx', ['locale' => $locale] + $query) }}">{{ __('d3_people.export_xlsx') }}</a>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/d3-people.css') }}">
@endpush

@section('content')
    @if (session('status'))
        <div class="a-notice a-notice--success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="a-notice a-notice--error" role="alert">{{ $errors->first() }}</div>
    @endif

    {{-- The chain, in one paragraph: where a filing comes from and where it goes. --}}
    <p class="a-hint">{{ __('d3_people.reg_chain') }}</p>

    @if ($mailOff)
        <div class="a-notice a-notice--pending d3p-mail" role="note">{{ __('d3_people.mail_off_note') }}</div>
    @endif

    <form method="GET" class="a-filters" action="{{ route('admin.registrations', ['locale' => $locale]) }}">
        <div class="a-field">
            <label class="a-label" for="f-meeting">{{ __('d3_people.col_event') }}</label>
            <select class="a-input" id="f-meeting" name="meeting">
                <option value="">{{ __('d3_people.all_events') }}</option>
                @foreach ($allMeetings as $m)
                    <option value="{{ $m->slug }}" @selected($filter['meeting']?->is($m))>{{ $m->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="a-field">
            <label class="a-label" for="f-status">{{ __('d3_people.status') }}</label>
            <select class="a-input" id="f-status" name="status">
                <option value="">{{ __('d3_people.all_statuses') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected($filter['status'] === $s)>{{ __('d3_people.reg_'.$s) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="a-btn a-btn--secondary">{{ __('admin.apply_filters') }}</button>
    </form>

    <p class="c-results" role="status">{{ trans_choice('d3_people.reg_count', $total, ['count' => $total, 'events' => $meetings->count()]) }}</p>

    @if ($meetings->isEmpty())
        <div class="a-card"><div class="a-empty">
            <div class="a-empty__title">{{ __('d3_people.reg_empty_title') }}</div>
            <div class="a-empty__body">{{ __('d3_people.reg_empty_body') }}</div>
        </div></div>
    @endif

    @foreach ($meetings as $meeting)
        @php
            $rows = $registrations->get($meeting->id, collect());
            $counts = $rows->countBy(fn ($r) => $r->status ?: 'filed');
            $headId = 'reg-'.$meeting->id;
        @endphp
        <section class="a-card a-card--flush" aria-labelledby="{{ $headId }}">
            <div class="a-card__head">
                <h2 id="{{ $headId }}">{{ $meeting->title }}</h2>
                <span class="a-card__meta">{{ $meeting->dateRange() }} @include('pages.events._status', ['meeting' => $meeting, 'surface' => 'admin'])</span>
            </div>
            <div class="d3p-body">
                <p class="a-hint">
                    {{ __('d3_people.reg_counts', ['filed' => $counts['filed'] ?? 0, 'confirmed' => $counts['confirmed'] ?? 0, 'declined' => $counts['declined'] ?? 0]) }}
                </p>
                <p class="d3p-event__links">
                    @if ($meeting->registrationIsOpen() && auth()->user()->can('view', $meeting))
                        <a class="a-btn a-btn--secondary a-btn--small" href="{{ route('events.register', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('d3_people.public_form') }}<span class="c-sr"> {{ $meeting->title }}</span></a>
                    @elseif (! $meeting->registrationIsOpen())
                        <span class="a-hint">{{ __('d3_people.form_closed') }}</span>
                        @can('view', $meeting)
                            <a class="a-btn a-btn--secondary a-btn--small" href="{{ route('events.show', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('d3_people.public_page') }}<span class="c-sr"> {{ $meeting->title }}</span></a>
                        @endcan
                    @endif
                    <a class="a-btn a-btn--secondary a-btn--small" href="{{ route('admin.delegates', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('d3_people.roster') }}<span class="c-sr"> {{ $meeting->title }}</span></a>
                    <a class="a-btn a-btn--secondary a-btn--small" href="{{ route('admin.registrations.csv', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('d3_people.export_csv') }}<span class="c-sr"> {{ $meeting->title }}</span></a>
                    <a class="a-btn a-btn--secondary a-btn--small" href="{{ route('admin.registrations.xlsx', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('d3_people.export_xlsx') }}<span class="c-sr"> {{ $meeting->title }}</span></a>
                </p>
            </div>

            @if ($rows->isEmpty())
                <div class="d3p-body"><div class="a-empty">
                    <div class="a-empty__title">{{ __('delegates.empty_title') }}</div>
                    <div class="a-empty__body">{{ __('d3_people.reg_event_empty') }}</div>
                </div></div>
            @else
                <div class="a-tablewrap">
                    <table class="a-table">
                        <caption class="c-sr">{{ __('d3_people.reg_caption', ['event' => $meeting->title]) }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('delegates.col_name') }}</th>
                                <th scope="col">{{ __('admin.field_country') }}</th>
                                <th scope="col">{{ __('delegates.col_arrival_on') }}</th>
                                <th scope="col">{{ __('delegates.col_departure_on') }}</th>
                                <th scope="col">{{ __('d3_people.status') }}</th>
                                <th scope="col">{{ __('admin.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php($st = $row->status ?: 'filed')
                                <tr>
                                    <th scope="row">
                                        {{ $row->delegate_name }}
                                        <span class="a-table__sub">{{ $row->delegate_email }}</span>
                                        @if ($row->delegate_role)<span class="a-table__sub">{{ $row->delegate_role }}</span>@endif
                                    </th>
                                    <td>{{ $row->country?->name }}</td>
                                    <td>{{ $row->arrival_on?->locale($locale)->isoFormat('D MMM YYYY') }}@if ($row->arrival_at)<span class="a-table__sub">{{ substr((string) $row->arrival_at, 0, 5) }} {{ $row->arrival_point }}</span>@endif</td>
                                    <td>{{ $row->departure_on?->locale($locale)->isoFormat('D MMM YYYY') }}@if ($row->departure_at)<span class="a-table__sub">{{ substr((string) $row->departure_at, 0, 5) }}</span>@endif</td>
                                    <td><span class="a-pill" data-status="{{ $pill[$st] ?? 'pending' }}">{{ __('d3_people.reg_'.$st) }}</span></td>
                                    <td>
                                        <span class="d3p-actions">
                                            @foreach (['confirmed' => 'confirm', 'declined' => 'decline'] as $to => $verb)
                                                @continue($st === $to)
                                                <form method="POST" action="{{ route('admin.registrations.decide', ['locale' => $locale, 'registration' => $row->id]) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="{{ $to }}">
                                                    <button type="submit" class="a-btn {{ $to === 'confirmed' ? 'a-btn--primary' : 'a-btn--secondary' }} a-btn--small">{{ __('d3_people.reg_'.$verb) }}<span class="c-sr"> {{ $row->delegate_name }}</span></button>
                                                </form>
                                            @endforeach
                                            <form method="POST" action="{{ route('admin.registrations.resend', ['locale' => $locale, 'registration' => $row->id]) }}">
                                                @csrf
                                                <button type="submit" class="a-btn a-btn--secondary a-btn--small" @if ($mailOff) aria-describedby="resend-off" @endif>{{ __('d3_people.resend') }}<span class="c-sr"> {{ $row->delegate_name }}</span></button>
                                            </form>
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach

    @if ($mailOff)
        <p class="c-sr" id="resend-off">{{ __('d3_people.resend_off_short') }}</p>
    @endif
@endsection
