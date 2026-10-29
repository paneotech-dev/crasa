{{--
    One event as a card: date block, committee code, title, date range,
    place or Online, who can see it, its status, and the action that fits:
    Add to my calendar and Register before it, the report after it.
    $level is the heading level (3 on the events page, 3 on home).
--}}
@php
    $locale = app()->getLocale();
    $url = route('events.show', ['locale' => $locale, 'meeting' => $meeting->slug]);
    $g = $meeting->group();
    $isPast = $meeting->date_unknown || $meeting->hasEnded();
    $h = 'h'.($level ?? 3);
@endphp
<article class="u-card e-card @if ($meeting->isCancelled()) e-card--cancelled @endif" data-group="{{ $g->code }}" data-year="{{ $meeting->date_unknown ? 'undated' : $meeting->starts_on->format('Y') }}">
    <div class="u-card__body">
        <div class="e-card__top">
            @if ($meeting->date_unknown)
                <span class="u-date e-date e-date--none"><span class="u-date__month">{{ __('e.no_date') }}</span></span>
            @else
                <span class="u-date e-date" aria-hidden="true">
                    <span class="u-date__day">{{ $meeting->starts_on->isoFormat('D') }}</span>
                    <span class="u-date__month">{{ $meeting->starts_on->copy()->locale($locale)->isoFormat('MMM') }}</span>
                    <span class="e-date__year">{{ $meeting->starts_on->format('Y') }}</span>
                </span>
            @endif
            <span class="e-card__group">@include('pages.events._badge', ['group' => $g])<span class="e-card__group-name">{{ $g->label() }}</span></span>
        </div>
        <{{ $h }} class="u-card__title"><a href="{{ $url }}">{{ $meeting->title }}</a></{{ $h }}>
        <p class="e-card__line">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            @if ($meeting->date_unknown)
                <span>{{ __('events.date_not_stated') }}</span>
            @else
                <time datetime="{{ $meeting->starts_on->toDateString() }}">{{ $meeting->dateRange() }}</time>
            @endif
        </p>
        @if ($meeting->isOnline() || $meeting->location)
            <p class="e-card__line">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                <span>{{ $meeting->isOnline() && strcasecmp((string) $meeting->location, 'Online') === 0 ? __('e.online') : $meeting->location }}</span>
            </p>
        @endif
        <p class="e-card__pills">
            @if ($meeting->isCancelled())
                <span class="u-pill e-pill--cancelled"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>{{ __('e.status_cancelled') }}</span>
            @elseif ($meeting->isPostponed())
                <span class="u-pill u-pill--notice"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ __('e.status_postponed') }}</span>
            @endif
            @if ($meeting->clearance === \App\Enums\Clearance::Public && $isPast)
            @elseif ($meeting->clearance === \App\Enums\Clearance::Public)
                <span class="u-pill u-pill--muted"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>{{ __('e.visibility_public') }}</span>
            @else
                <span class="u-pill"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>{{ __('e.visibility_members') }}</span>
            @endif
            @if ($isPast && ($label ?? false))
                <span class="u-pill u-pill--muted">{{ __('events.past') }}</span>
            @endif
        </p>
    </div>
    @php($hasReport = $isPast && $meeting->newsItem && $meeting->newsItem->status === 'published')
    @if (! $isPast || $hasReport)
    <div class="u-card__actions e-card__actions">
        @if (! $isPast)
            @include('pages.events._add-to-calendar', ['meeting' => $meeting, 'compact' => true])
            @if ($meeting->registrationIsOpen() && ! ($noRegister ?? false))
                <a class="u-btn u-btn--secondary e-card__btn" href="{{ route('events.register', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('e.register') }}<span class="c-sr"> {{ __('events.calendar_for', ['event' => $meeting->title]) }}</span></a>
            @endif
        @else
            <a class="u-btn u-btn--ghost e-card__btn" href="{{ route('newsroom.show', ['locale' => $locale, 'slug' => $meeting->newsItem->slug]) }}">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 4h11l3 3v13H5z"/><path d="M8 10h8M8 14h8M8 18h5"/></svg>{{ __('e.read_report') }}<span class="c-sr"> {{ __('events.calendar_for', ['event' => $meeting->title]) }}</span></a>
        @endif
    </div>
    @endif
</article>
