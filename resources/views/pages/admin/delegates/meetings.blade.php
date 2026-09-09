@extends('layouts.admin')

@section('title', __('admin.meetings_title'))
@section('note', __('admin.meetings_note'))

@section('crumbs')
    <li aria-current="page">{{ __('admin.meetings_title') }}</li>
@endsection

@section('content')
    @if ($upcoming->isEmpty() && $past->isEmpty())
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('admin.meetings_empty_title') }}</div>
                <div class="a-empty__body">{{ __('admin.meetings_empty_body') }}</div>
            </div>
        </div>
    @endif

    @foreach (['upcoming' => $upcoming, 'past' => $past] as $key => $meetings)
        @continue($meetings->isEmpty())

        <section class="a-card a-card--flush">
            <div class="a-card__head"><h2>{{ __('admin.meetings_'.$key) }}</h2></div>

            <table class="a-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.meetings_col_meeting') }}</th>
                        <th scope="col">{{ __('admin.meetings_col_dates') }}</th>
                        <th scope="col" class="a-table__num">{{ __('admin.meetings_col_delegates') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($meetings as $meeting)
                        <tr>
                            <th scope="row">
                                <a href="{{ route('admin.delegates', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}">{{ $meeting->title }}</a>
                                @if ($meeting->committee)
                                    <span class="a-results__meta">{{ $meeting->committee->name }}</span>
                                @endif
                            </th>
                            <td>
                                {{ $meeting->starts_on->isoFormat('D MMM YYYY') }}@if ($meeting->ends_on && ! $meeting->ends_on->isSameDay($meeting->starts_on)) to {{ $meeting->ends_on->isoFormat('D MMM YYYY') }}@endif
                            </td>
                            <td class="a-table__num">{{ number_format($meeting->delegate_registrations_count) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
@endsection
