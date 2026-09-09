@extends('layouts.extranet')

@section('title', __('extranet.calendar'))
@section('note', __('extranet.calendar_note'))

@section('crumbs')
    <li><a href="{{ route('extranet.folders', ['locale' => app()->getLocale()]) }}">{{ __('extranet.title') }}</a></li>
    <li aria-current="page">{{ __('extranet.calendar') }}</li>
@endsection

@section('content')
    @foreach (['upcoming' => $upcoming, 'past' => $past] as $key => $meetings)
        <section class="a-resource">
            <div class="a-resource__head">
                <h2 class="a-resource__title">{{ __('extranet.'.$key) }}</h2>
                <div class="a-resource__note">{{ __('extranet.'.$key.'_note') }}</div>
            </div>

            @if ($meetings->isEmpty())
                <div style="padding:var(--s-5)">
                    <div class="a-empty">
                        <div class="a-empty__title">{{ __('extranet.no_meetings_title') }}</div>
                        <div class="a-empty__body">{{ __('extranet.no_meetings_body') }}</div>
                    </div>
                </div>
            @else
                <div class="a-tablewrap">
                    <table class="a-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('extranet.col_when') }}</th>
                                <th scope="col">{{ __('extranet.col_meeting') }}</th>
                                <th scope="col">{{ __('extranet.col_committee') }}</th>
                                <th scope="col" data-quiet>{{ __('extranet.col_where') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($meetings as $meeting)
                                <tr>
                                    <th scope="row">
                                        {{ $meeting->starts_on->isoFormat('D MMM YYYY') }}
                                        @if ($meeting->ends_on && ! $meeting->ends_on->isSameDay($meeting->starts_on))
                                            <span class="a-table__sub">{{ __('extranet.until', ['date' => $meeting->ends_on->isoFormat('D MMM')]) }}</span>
                                        @endif
                                    </th>
                                    <td>
                                        {{ $meeting->title }}
                                        <span class="a-pill" data-status="{{ $meeting->clearance->value }}">{{ $meeting->clearance->label() }}</span>
                                    </td>
                                    <td>{{ $meeting->committee?->name ?? __('extranet.regional') }}</td>
                                    <td data-quiet>{{ $meeting->location }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
@endsection
