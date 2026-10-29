@extends('layouts.extranet')

@section('title', __('extranet.calendar'))
@section('note', __('extranet.calendar_note'))

@section('crumbs')
    <li><a href="{{ route('extranet.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('extranet.title') }}</a></li>
    <li aria-current="page">{{ __('extranet.calendar') }}</li>
@endsection

@section('content')
    {{-- Screen 12's committee filter. Links rather than buttons: a month
         filtered to one committee is a URL a chair sends. --}}
    <nav class="c-filters e-cal__filters" aria-label="{{ __('extranet.filter_committee') }}">
        <div class="c-filters__group">
            <span class="c-filters__legend" id="facet-committee">{{ __('extranet.filter_committee') }}</span>
            <div class="c-filters__options" role="list" aria-labelledby="facet-committee">
                <a role="listitem" class="c-chip @if (! $committeeFilter) is-active @endif"
                   @if (! $committeeFilter) aria-current="true" @endif
                   href="{{ request()->fullUrlWithQuery(['committee' => null]) }}">{{ __('blocks.filter_all') }}</a>
                @foreach ($committees as $committee)
                    <a role="listitem" class="c-chip @if ($committeeFilter === $committee->slug) is-active @endif"
                       @if ($committeeFilter === $committee->slug) aria-current="true" @endif
                       href="{{ request()->fullUrlWithQuery(['committee' => $committee->slug]) }}">{{ $committee->name }}</a>
                @endforeach
            </div>
        </div>
    </nav>

    @php
        $start = $month->startOfMonth();
        // Monday-first, which is how a regional meeting calendar is read.
        $lead = ($start->dayOfWeekIso - 1);
        // A meeting sits on every day it runs within this month, not only
        // on its first day.
        $byDay = [];
        foreach ($inMonth as $m) {
            $from = $m->starts_on->toDateString() < $start->toDateString() ? 1 : (int) $m->starts_on->format('j');
            $endsOn = $m->ends_on ?? $m->starts_on;
            $to = $endsOn->toDateString() > $start->endOfMonth()->toDateString() ? $month->daysInMonth : (int) $endsOn->format('j');
            for ($d = $from; $d <= $to; $d++) {
                $byDay[$d][] = $m;
            }
        }
    @endphp

    @if ($movedFrom)
        <p class="a-alert" role="status">
            {{ __('office.calendar_moved', ['empty' => $movedFrom->isoFormat('MMMM YYYY'), 'month' => $month->isoFormat('MMMM YYYY')]) }}
        </p>
    @endif

    <section class="a-resource">
        <div class="a-resource__head">
            <h2 class="a-resource__title">{{ $month->isoFormat('MMMM YYYY') }}</h2>
            <div class="a-resource__note">
                {{ trans_choice('extranet.month_count', $inMonth->count(), ['count' => $inMonth->count()]) }}
            </div>
            <div class="e-cal__nav">
                <a class="c-btn c-btn--secondary" href="{{ request()->fullUrlWithQuery(['month' => $month->subMonth()->format('Y-m')]) }}"
                   rel="prev">{{ __('extranet.month_previous') }}</a>
                <a class="c-btn c-btn--secondary" href="{{ request()->fullUrlWithQuery(['month' => $month->addMonth()->format('Y-m')]) }}"
                   rel="next">{{ __('extranet.month_next') }}</a>
            </div>
        </div>

        {{-- A grid, not a table: these cells are a layout of dates rather
             than rows and columns of data, and the meetings inside them are
             a list. The month, its dates and every meeting on them are all
             in the list below as well, which is what a screen reader is
             sent to. --}}
        <div class="e-cal" role="presentation">
            <div class="e-cal__weekdays" aria-hidden="true">
                @foreach ([1, 2, 3, 4, 5, 6, 7] as $iso)
                    <span>{{ $start->startOfWeek()->addDays($iso - 1)->isoFormat('dd') }}</span>
                @endforeach
            </div>
            <div class="e-cal__grid">
                @for ($i = 0; $i < $lead; $i++)
                    <div class="e-cal__cell e-cal__cell--outside" aria-hidden="true"></div>
                @endfor
                @for ($day = 1; $day <= $month->daysInMonth; $day++)
                    @php $onDay = $byDay[$day] ?? []; @endphp
                    <div @class(['e-cal__cell', 'is-today' => $month->setDay($day)->toDateString() === $today])>
                        <span class="e-cal__daynum">{{ $day }}</span>
                        @foreach ($onDay as $meeting)
                            {{-- Type is carried by colour AND by the label
                                 in every cell, which is screen 12's own
                                 rule. Colour alone would be a meeting type
                                 a colour-blind chair cannot read. --}}
                            <span class="e-cal__meeting" data-kind="{{ $meeting->kind }}">
                                <span class="e-cal__kind">{{ __('extranet.kind_'.$meeting->kind) }}</span>
                                {{ $meeting->title }}
                            </span>
                        @endforeach
                    </div>
                @endfor
            </div>
        </div>

        @if ($inMonth->isEmpty())
            <div class="a-empty">
                <p class="a-empty__title">{{ __('extranet.month_empty_title') }}</p>
                <p class="a-empty__body">{{ __('extranet.month_empty_body') }}</p>
            </div>
        @endif

        <div class="e-cal__export">
            <h3 class="a-resource__title">{{ __('extranet.export') }}</h3>
            <p class="a-resource__note">{{ __('extranet.export_note') }}</p>
            <a class="c-btn c-btn--secondary"
               href="{{ route('extranet.calendar.ics', ['locale' => app()->getLocale(), 'month' => $month->format('Y-m')]) }}">
                {{ __('extranet.export_month') }}
            </a>
        </div>
    </section>

    @foreach (['upcoming' => $upcoming, 'past' => $past] as $key => $meetings)
        {{-- The month above already says there is nothing; a second
             "nothing scheduled" straight under it said it twice. --}}
        @continue($meetings->isEmpty() && $inMonth->isEmpty())
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
                                        {{ $meeting->date_unknown ? __('events.date_not_stated') : $meeting->starts_on->isoFormat('D MMM YYYY') }}
                                        @if (! $meeting->date_unknown && $meeting->ends_on && ! $meeting->ends_on->isSameDay($meeting->starts_on))
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
