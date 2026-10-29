@extends('layouts.admin')

@section('title', __('delegates.list_title'))
@section('note', $meeting->title)

@section('crumbs')
    <li><a href="{{ route('admin.meetings', ['locale' => app()->getLocale()]) }}">{{ __('e.admin_title') }}</a></li>
    <li aria-current="page">{{ $meeting->title }}</li>
@endsection

@section('actions')
    {{-- The handover point. The website has collected the information;
         this is how it reaches the platform that schedules with it. --}}
    <a class="a-btn a-btn--primary" href="{{ route('admin.delegates.csv', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}">{{ __('delegates.export') }}</a>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/events.css') }}">
@endpush

@section('content')
    @if (session('status'))
        <div class="a-notice a-notice--success" role="status">{{ session('status') }}</div>
    @endif
    <div class="a-notice a-notice--pending" role="note">{{ __('delegates.boundary_note') }}</div>

    <section class="a-resource">
        <div class="a-resource__head">
            <h2 class="a-resource__title">{{ __('delegates.list_title') }}</h2>
            <div class="a-resource__note">{{ trans_choice('delegates.count', $registrations->count(), ['count' => $registrations->count()]) }}</div>
        </div>

        @if ($registrations->isEmpty())
            <div style="padding:var(--s-5)">
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('delegates.empty_title') }}</div>
                    <div class="a-empty__body">{{ __('delegates.empty_body') }}</div>
                </div>
            </div>
        @else
            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            @foreach ($sorts as $column)
                                @php
                                    $isCurrent = $sort === $column;
                                    $next = $isCurrent && $direction === 'asc' ? 'desc' : 'asc';
                                @endphp
                                <th scope="col" @if ($isCurrent) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                    <a href="{{ route('admin.delegates', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug, 'sort' => $column, 'direction' => $next]) }}">
                                        {{ $column === 'country_id' ? __('admin.field_country') : __('delegates.col_'.str_replace('delegate_', '', $column)) }}
                                    </a>
                                </th>
                            @endforeach
                            <th scope="col">{{ __('delegates.col_arrival_reference') }}</th>
                            <th scope="col">{{ __('delegates.col_notes') }}</th>
                            <th scope="col">{{ __('e.delegate_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($registrations as $row)
                            <tr>
                                <th scope="row">
                                    {{ $row->delegate_name }}
                                    <span class="a-table__sub">{{ $row->delegate_role }}</span>
                                </th>
                                <td>
                                    @if ($row->country)
                                        <x-flag :iso="$row->country->iso_code" />
                                        {{ $row->country->displayName() }}
                                    @endif
                                </td>
                                <td>
                                    {{ $row->arrival_on?->isoFormat('D MMM') }}
                                    @if ($row->arrival_at)<span class="a-table__sub">{{ $row->arrival_at }} {{ $row->arrival_point }}</span>@endif
                                </td>
                                <td>
                                    {{ $row->departure_on?->isoFormat('D MMM') }}
                                    @if ($row->departure_at)<span class="a-table__sub">{{ $row->departure_at }}</span>@endif
                                </td>
                                <td data-quiet>{{ $row->arrival_reference }}</td>
                                <td data-quiet>{{ $row->notes }}</td>
                                <td>
                                    @php($st = $row->status ?: 'filed')
                                    <span class="a-pill" data-status="{{ ['filed' => 'pending', 'confirmed' => 'validated', 'declined' => 'rejected'][$st] ?? 'pending' }}">{{ __('e.delegate_'.$st) }}</span>
                                    <span class="e-delegate-actions">
                                        @foreach (['confirmed' => 'confirm', 'declined' => 'decline'] as $to => $verb)
                                            @continue($st === $to)
                                            <form method="POST" action="{{ route('admin.delegates.decide', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug, 'registration' => $row->id]) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="{{ $to }}">
                                                <button type="submit" class="a-btn {{ $to === 'confirmed' ? 'a-btn--primary' : 'a-btn--secondary' }} a-btn--small">{{ __('e.delegate_'.$verb) }}<span class="c-sr"> {{ $row->delegate_name }}</span></button>
                                            </form>
                                        @endforeach
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
