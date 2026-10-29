@extends('layouts.public')

@section('title', __('events.title'))
@section('description', __('events.meta_description'))

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/events.css') }}">
@endpush
@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/events.js') }}" defer></script>
@endpush

@php
    $locale = app()->getLocale();
    $base = ['locale' => $locale];
    $query = fn (array $over = []) => array_filter(array_merge(
        ['committee' => $committee, 'month' => $month->format('Y-m'), 'view' => $view === 'auto' ? null : $view],
        $over
    ), fn ($v) => $v !== null && $v !== '');
    $todayMonth = \Carbon\CarbonImmutable::parse($today)->format('Y-m');
    $weekdays = collect(range(0, 6))->map(fn ($i) => \Carbon\CarbonImmutable::parse('2026-10-26')->addDays($i)->locale($locale));
@endphp

@section('content')
    <section class="u-hero" aria-labelledby="events-title">
        <div class="u-container u-hero__grid">
            <div>
                <p class="u-eyebrow">{{ __('events.band_eyebrow') }}</p>
                <h1 class="u-h1" id="events-title">{{ __('e.hero_title') }}</h1>
                <p class="u-lead">{{ __('e.hero_lead') }}</p>
                <dl class="u-figures">
                    <div class="u-figure"><dt>{{ trans_choice('events.count_upcoming', $counts['upcoming']) }}</dt><dd>{{ $counts['upcoming'] }}</dd></div>
                    <div class="u-figure"><dt>{{ trans_choice('events.count_past', $counts['past']) }}</dt><dd>{{ $counts['past'] }}</dd></div>
                    <div class="u-figure"><dt>{{ __('e.figure_bodies') }}</dt><dd>{{ count($groups) }}</dd></div>
                </dl>
            </div>
            @if ($next = $upcoming->first())
                <aside class="u-panel e-next" aria-labelledby="next-title">
                    <p class="u-eyebrow" id="next-title">{{ __('e.next_meeting') }}</p>
                    <div class="e-next__row">
                        <span class="u-date e-date" aria-hidden="true"><span class="u-date__day">{{ $next->starts_on->isoFormat('D') }}</span><span class="u-date__month">{{ $next->starts_on->copy()->locale($locale)->isoFormat('MMM') }}</span></span>
                        <div>
                            <p class="e-next__title"><a href="{{ route('events.show', ['locale' => $locale, 'meeting' => $next->slug]) }}">{{ $next->title }}</a></p>
                            <p class="e-next__meta">@include('pages.events._badge', ['meeting' => $next]) <span>{{ $next->dateRange() }}</span></p>
                            @if ($next->location)<p class="e-next__meta">{{ $next->location }}</p>@endif
                        </div>
                    </div>
                    @include('pages.events._add-to-calendar', ['meeting' => $next])
                </aside>
            @endif
        </div>
    </section>

    <section class="u-section e-cal-section" aria-labelledby="cal-title" data-events data-view="{{ $view }}">
        <div class="u-container">
            <div class="e-cal-head">
                <h2 class="u-h2" id="cal-title">{{ __('e.calendar') }}</h2>
                <div class="e-viewswitch" role="group" aria-label="{{ __('e.view_label') }}">
                    <a class="u-chip" data-view-switch="month" href="{{ route('events', $base + $query(['view' => 'month'])) }}#cal-title" @if ($view === 'month') aria-current="true" @endif>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v11M15 10v11M3 15.5h18"/></svg>{{ __('e.view_month') }}</a>
                    <a class="u-chip" data-view-switch="list" href="{{ route('events', $base + $query(['view' => 'list'])) }}#cal-title" @if ($view === 'list') aria-current="true" @endif>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h1M3 12h1M3 18h1"/></svg>{{ __('e.view_list') }}</a>
                </div>
            </div>

            {{-- Committee filter. Links work without script; events.js filters in place. --}}
            <nav class="e-filter" aria-label="{{ __('e.filter_label') }}">
                <ul class="e-filter__list">
                    <li><a class="u-chip" data-filter="" href="{{ route('events', $base + $query(['committee' => null])) }}#cal-title" @if (! $committee) aria-current="true" @endif>{{ __('e.filter_all') }}</a></li>
                    @foreach ($groups as $g)
                        <li><a class="u-chip e-chip-filter" data-filter="{{ $g->code }}" href="{{ route('events', $base + $query(['committee' => $g->code])) }}#cal-title" @if ($committee === $g->code) aria-current="true" @endif>@include('pages.events._badge', ['group' => $g]){{ $g->code === 'CRASA' ? __('e.filter_crasa') : $g->code }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <div class="e-cal-wrap">
                <div class="e-cal-main">
                    <div class="e-monthnav">
                        <a class="u-btn u-btn--secondary e-monthnav__btn" href="{{ route('events', $base + $query(['month' => $month->subMonth()->format('Y-m')])) }}#cal-title" rel="prev">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg><span class="c-sr">{{ __('e.previous_month') }}: </span><span class="e-monthnav__short">{{ $month->subMonth()->locale($locale)->isoFormat('MMM') }}</span></a>
                        <p class="e-monthnav__title" id="cal-month" aria-live="polite">{{ ucfirst($month->locale($locale)->isoFormat('MMMM YYYY')) }}</p>
                        <a class="u-btn u-btn--ghost" href="{{ route('events', $base + $query(['month' => $todayMonth])) }}#cal-title" @if ($month->format('Y-m') === $todayMonth) aria-current="date" @endif>{{ __('e.today') }}</a>
                        <a class="u-btn u-btn--secondary e-monthnav__btn" href="{{ route('events', $base + $query(['month' => $month->addMonth()->format('Y-m')])) }}#cal-title" rel="next">
                            <span class="e-monthnav__short">{{ $month->addMonth()->locale($locale)->isoFormat('MMM') }}</span><span class="c-sr"> : {{ __('e.next_month') }}</span><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a>
                    </div>

                    {{-- Month view: a real table. Arrow keys move between the event links (events.js). --}}
                    <div class="e-month" data-pane="month">
                        <table class="e-grid" aria-labelledby="cal-month" data-grid>
                            <thead>
                                <tr>
                                    @foreach ($weekdays as $wd)
                                        <th scope="col"><abbr title="{{ ucfirst($wd->isoFormat('dddd')) }}">{{ ucfirst($wd->isoFormat('ddd')) }}</abbr></th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($weeks as $week)
                                    <tr>
                                        @foreach ($week as $day)
                                            @php($d = $day['date'])
                                            <td class="e-day @unless ($day['inMonth']) e-day--out @endunless @if ($d->toDateString() === $today) e-day--today @endif @if ($day['meetings']->isNotEmpty()) e-day--busy @endif" data-date="{{ $d->toDateString() }}">
                                                <span class="e-day__num" @if ($d->toDateString() === $today) aria-current="date" @endif><span class="c-sr">{{ $d->locale($locale)->isoFormat('D MMMM') }}</span><span aria-hidden="true">{{ $d->day }}</span>@if ($d->toDateString() === $today)<span class="e-day__today">{{ __('e.today') }}</span>@endif</span>
                                                @foreach ($day['meetings'] as $m)
                                                    @php($g = $m->group())
                                                    <a class="e-chip @if ($m->isCancelled()) e-chip--cancelled @endif @unless ($m->starts_on->isSameDay($d) || $d->isMonday() || $d->day === 1) e-chip--cont @endunless" data-group="{{ $g->code }}" style="--e-c: {{ $g->colour }}" href="{{ route('events.show', ['locale' => $locale, 'meeting' => $m->slug]) }}">
                                                        <span class="e-chip__code" aria-hidden="true">{{ $g->code }}</span>
                                                        <span class="e-chip__text"><span class="c-sr">{{ $d->locale($locale)->isoFormat('D MMMM') }}, {{ $g->label() }}: </span>{{ $m->title }}@if ($m->isCancelled())<span class="c-sr"> ({{ __('e.status_cancelled') }})</span>@endif</span>
                                                    </a>
                                                @endforeach
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($monthEvents->isEmpty())
                            <p class="e-month__empty">{{ __('e.month_empty') }}</p>
                        @endif
                    </div>

                    {{-- List view of the same month: the phone default. --}}
                    <div class="e-list" data-pane="list">
                        @if ($monthEvents->isEmpty())
                            <p class="e-month__empty">{{ __('e.month_empty') }}</p>
                        @else
                            <ul class="e-list__items">
                                @foreach ($monthEvents as $m)
                                    @php($g = $m->group())
                                    <li class="e-list__item" data-group="{{ $g->code }}">
                                        <span class="u-date e-date" aria-hidden="true"><span class="u-date__day">{{ $m->starts_on->isoFormat('D') }}</span><span class="u-date__month">{{ $m->starts_on->copy()->locale($locale)->isoFormat('MMM') }}</span></span>
                                        <div>
                                            <a class="e-list__title" href="{{ route('events.show', ['locale' => $locale, 'meeting' => $m->slug]) }}">{{ $m->title }}</a>
                                            <p class="e-list__meta">@include('pages.events._badge', ['group' => $g]) <time datetime="{{ $m->starts_on->toDateString() }}">{{ $m->dateRange() }}</time>@if ($m->location)<span>{{ $m->location }}</span>@endif @if ($m->isCancelled())<span class="u-pill e-pill--cancelled">{{ __('e.status_cancelled') }}</span>@elseif ($m->isPostponed())<span class="u-pill u-pill--notice">{{ __('e.status_postponed') }}</span>@endif</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <details class="e-legend u-panel u-panel--tint" open data-legend>
                    <summary class="e-legend__title">{{ __('e.legend') }}</summary>
                    <ul class="e-legend__list">
                        @foreach ($groups as $g)
                            <li>@include('pages.events._badge', ['group' => $g])<span>{{ $g->label() }}</span></li>
                        @endforeach
                    </ul>
                    <p class="e-legend__note">{{ __('e.timezone_note') }}</p>
                </details>
            </div>
        </div>
    </section>

    <section class="u-section u-section--pale" id="upcoming" aria-labelledby="upcoming-title">
        <div class="u-container">
            <h2 class="u-h2" id="upcoming-title">{{ __('events.upcoming') }}</h2>
            @if ($upcoming->isEmpty())
                @include('blocks._empty', [
                    'title' => __('events.none_upcoming_title'),
                    'body' => __('events.none_upcoming_body'),
                ])
            @else
                <ul class="{{ $upcoming->count() <= 2 ? 'u-cols-2' : 'u-cols-3' }} e-cards" role="list">
                    @foreach ($upcoming as $meeting)
                        <li data-group="{{ $meeting->group()->code }}">@include('pages.events._card', ['meeting' => $meeting, 'level' => 3])</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    @if ($pastByYear->isNotEmpty())
        <section class="u-section" id="past" aria-labelledby="past-title" data-past>
            <div class="u-container">
                <div class="e-past-head">
                    <h2 class="u-h2" id="past-title">{{ __('events.past') }}</h2>
                    <nav class="e-filter" aria-label="{{ __('e.year_filter') }}">
                        <ul class="e-filter__list">
                            <li><a class="u-chip" data-year-filter="" href="{{ route('events', $base + $query(['year' => 'all'])) }}#past-title" @if (! $year) aria-current="true" @endif>{{ __('e.all_years') }}</a></li>
                            @foreach ($pastByYear->keys() as $y)
                                <li><a class="u-chip" data-year-filter="{{ $y }}" href="{{ route('events', $base + $query(['year' => $y])) }}#past-title" @if ($year === (string) $y) aria-current="true" @endif>{{ $y === 'undated' ? __('events.date_not_stated') : $y }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                </div>
                <p class="c-sr" aria-live="polite" data-past-status></p>
                @foreach ($pastByYear as $y => $items)
                    <div class="e-year" data-year="{{ $y }}" @if ($year && $year !== (string) $y) hidden @endif>
                        <h3 class="e-year__title">{{ $y === 'undated' ? __('events.date_not_stated') : $y }} <span class="e-year__count">{{ trans_choice('e.meetings_count', $items->count(), ['count' => $items->count()]) }}</span></h3>
                        <ul class="u-cols-4 e-cards e-cards--past" role="list">
                            @foreach ($items as $meeting)
                                <li data-group="{{ $meeting->group()->code }}">@include('pages.events._card', ['meeting' => $meeting, 'level' => 4])</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="u-cta" aria-labelledby="events-cta">
        <div class="u-container u-cta__inner">
            <div>
                <h2 class="u-cta__title" id="events-cta">{{ __('e.cta_title') }}</h2>
                <p class="u-cta__text">{{ __('e.cta_text') }}</p>
            </div>
            <div class="u-actions">
                <a class="u-btn u-btn--primary" href="{{ route('extranet.calendar', ['locale' => $locale]) }}">{{ __('e.cta_member') }}</a>
                <a class="u-btn u-btn--secondary" href="{{ route('newsroom', ['locale' => $locale]) }}">{{ __('e.cta_news') }}</a>
            </div>
        </div>
    </section>
@endsection
