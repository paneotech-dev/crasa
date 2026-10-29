@extends('layouts.extranet')

@section('title', __('extranet.calendar'))
@section('note', __('extranet.calendar_note'))

@section('crumbs')
    <li><a href="{{ route('extranet.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('extranet.title') }}</a></li>
    <li aria-current="page">{{ __('extranet.calendar') }}</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/member-calendar.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/d3-people-public.css') }}">
@endpush

@section('content')
    {{-- Evening pass: the UI system (u- components) with the public calendar's
         committee colours and codes (App\Support\EventGroup). Links rather
         than buttons: a month filtered to one committee is a URL a chair sends. --}}
    <nav class="mc-filter" aria-label="{{ __('extranet.filter_committee') }}">
        <p class="mc-filter__legend" id="facet-committee">{{ __('extranet.filter_committee') }}</p>
        <ul class="mc-filter__chips" role="list" aria-labelledby="facet-committee">
            <li><a class="u-chip" @if (! $committeeFilter) aria-current="true" @endif
                   href="{{ request()->fullUrlWithQuery(['committee' => null]) }}">{{ __('blocks.filter_all') }}</a></li>
            @foreach ($groups as $g)
                <li><a class="u-chip" @if ($committeeFilter === $g->code) aria-current="true" @endif
                       href="{{ request()->fullUrlWithQuery(['committee' => $g->code]) }}"><span class="mc-badge" style="--e-c: {{ $g->colour }}" aria-hidden="true">{{ $g->code }}</span>{{ $g->label() }}</a></li>
            @endforeach
        </ul>
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

    <section class="u-panel mc-month" aria-labelledby="mc-month-title">
        <div class="mc-month__head">
            <div>
                <h2 class="mc-month__title" id="mc-month-title">{{ ucfirst($month->isoFormat('MMMM YYYY')) }}</h2>
                <p class="mc-month__count">{{ trans_choice('extranet.month_count', $inMonth->count(), ['count' => $inMonth->count()]) }}</p>
            </div>
            <div class="mc-month__nav">
                <a class="u-btn u-btn--secondary" href="{{ request()->fullUrlWithQuery(['month' => $month->subMonth()->format('Y-m')]) }}" rel="prev">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>{{ __('extranet.month_previous') }}</a>
                <a class="u-btn u-btn--secondary" href="{{ request()->fullUrlWithQuery(['month' => $month->addMonth()->format('Y-m')]) }}" rel="next">
                    {{ __('extranet.month_next') }}<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a>
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
                            @php($g = $meeting->group())
                            <a class="e-cal__meeting mc-chip" data-kind="{{ $meeting->kind }}" data-group="{{ $g->code }}" style="--e-c: {{ $g->colour }}"
                               href="{{ route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}">
                                <span class="mc-chip__top"><span class="mc-badge" aria-hidden="true">{{ $g->code }}</span><span class="e-cal__kind">{{ __('extranet.kind_'.$meeting->kind) }}</span></span>
                                <span class="mc-chip__title"><span class="c-sr">{{ $g->label() }}: </span>{{ $meeting->title }}@if ($meeting->displayStatus() !== 'scheduled')<span class="c-sr"> ({{ $meeting->displayStatusLabel() }})</span>@endif</span>
                            </a>
                        @endforeach
                    </div>
                @endfor
            </div>
        </div>

        @if ($inMonth->isEmpty())
            <div class="mc-empty">
                <p class="mc-empty__title">{{ __('extranet.month_empty_title') }}</p>
                <p class="u-muted">{{ __('extranet.month_empty_body') }}</p>
            </div>
        @endif

        @if ($groups !== [])
            <ul class="mc-legend" role="list" aria-label="{{ __('e.legend') }}">
                @foreach ($groups as $g)
                    <li><span class="mc-badge" style="--e-c: {{ $g->colour }}" aria-hidden="true">{{ $g->code }}</span>{{ $g->label() }}</li>
                @endforeach
            </ul>
        @endif

        <div class="e-cal__export mc-export">
            <div>
                <h3 class="mc-export__title">{{ __('extranet.export') }}</h3>
                <p class="u-muted">{{ __('extranet.export_note') }}</p>
            </div>
            <a class="u-btn u-btn--secondary"
               href="{{ route('extranet.calendar.ics', ['locale' => app()->getLocale(), 'month' => $month->format('Y-m')]) }}">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>{{ __('extranet.export_month') }}
            </a>
        </div>
    </section>

    @foreach (['upcoming' => $upcoming, 'past' => $past] as $key => $meetings)
        {{-- The month above already says there is nothing; a second
             "nothing scheduled" straight under it said it twice. --}}
        @continue($meetings->isEmpty() && $inMonth->isEmpty())
        <section class="mc-list" aria-labelledby="mc-{{ $key }}">
            <div class="mc-list__head">
                <h2 class="mc-list__title" id="mc-{{ $key }}">{{ __('extranet.'.$key) }}</h2>
                <p class="u-muted">{{ __('extranet.'.$key.'_note') }}</p>
            </div>

            @if ($meetings->isEmpty())
                <div class="mc-empty">
                    <p class="mc-empty__title">{{ __('extranet.no_meetings_title') }}</p>
                    <p class="u-muted">{{ __('extranet.no_meetings_body') }}</p>
                </div>
            @else
                <ul class="mc-cards" role="list">
                    @foreach ($meetings as $meeting)
                        @php($g = $meeting->group())
                        <li class="u-card mc-card" style="--e-c: {{ $g->colour }}">
                            @if ($meeting->date_unknown)
                                <span class="u-date mc-card__date"><span class="u-date__month">{{ __('events.date_not_stated') }}</span></span>
                            @else
                                <span class="u-date mc-card__date" aria-hidden="true">
                                    <span class="u-date__day">{{ $meeting->starts_on->isoFormat('D') }}</span>
                                    <span class="u-date__month">{{ $meeting->starts_on->copy()->locale(app()->getLocale())->isoFormat('MMM YYYY') }}</span>
                                </span>
                            @endif
                            <div class="mc-card__body">
                                <p class="mc-card__group"><span class="mc-badge" aria-hidden="true">{{ $g->code }}</span>{{ $meeting->committee?->name ?? $g->label() }}</p>
                                <h3 class="mc-card__title"><a href="{{ route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}">{{ $meeting->title }}</a></h3>
                                <p class="mc-card__meta">
                                    @unless ($meeting->date_unknown)
                                        <time datetime="{{ $meeting->starts_on->toDateString() }}">{{ $meeting->dateRange() }}</time>
                                    @endunless
                                    @if ($meeting->location)<span>{{ $meeting->location }}</span>@endif
                                    <span class="u-pill {{ $meeting->clearance === \App\Enums\Clearance::Public ? 'u-pill--muted' : '' }}">{{ $meeting->clearance->label() }}</span>
                                    @include('pages.events._status', ['meeting' => $meeting, 'quiet' => true])
                                </p>
                                @if ($key === 'upcoming' && $meeting->registrationIsOpen())
                                    <p class="mc-card__actions"><a class="u-btn u-btn--primary" href="{{ route('events.register', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}">{{ __('d3_people.register_attendance') }}<span class="c-sr">: {{ $meeting->title }}</span></a></p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
@endsection
