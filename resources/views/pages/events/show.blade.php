@extends('layouts.public')

@section('title', $meeting->title)

@section('content')
    <div class="c-section c-section--title">
        <div class="c-page">
            <p class="b__eyebrow">{{ $meeting->committee?->name ?? __('extranet.regional') }}</p>
            <h1>{{ $meeting->title }}</h1>
            <p class="c-lead">
                @if ($meeting->date_unknown)
                    {{ __('events.date_not_stated') }}
                @else
                    <time datetime="{{ $meeting->starts_on->toDateString() }}">{{ $meeting->starts_on->isoFormat('D MMMM YYYY') }}</time>@if ($meeting->ends_on && ! $meeting->ends_on->isSameDay($meeting->starts_on)) {{ __('extranet.until', ['date' => $meeting->ends_on->isoFormat('D MMMM YYYY')]) }}@endif
                @endif
                @if ($meeting->location) — {{ $meeting->location }}@endif
            </p>
        </div>
    </div>

    <div class="c-section">
        <div class="c-page">
            {{-- The registration form redirects here; without this the
                 delegate filed and saw no confirmation. --}}
            @if (session('status'))
                <div class="c-notice c-notice--success" role="status">
                    <svg class="c-notice__icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="var(--c-green-dark)" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                        <path d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div>
                        <div class="c-notice__body">{{ session('status') }}</div>
                    </div>
                </div>
            @endif

            @if ($meeting->agenda)
                <h2>{{ __('events.agenda') }}</h2>

                {{-- Four of the forty-three agendas that came across from
                     the events host are the WordPress theme's demo Latin,
                     on public meetings with genuine titles and dates.
                     Removing the shortcodes around that text would leave
                     lorem ipsum on a treaty body's events page, so a
                     reader is told the agenda is not settled instead.

                     The stored value is untouched, and the notice stops
                     appearing by itself the moment CRASA supplies a real
                     agenda. See App\Support\PlaceholderContent. --}}
                @if (\App\Support\PlaceholderContent::isFiller($meeting->agenda))
                    <p class="c-prose c-note">{{ \App\Support\PlaceholderContent::agendaNotice() }}</p>
                @else
                    <div class="c-prose">{!! app(\App\Services\ContentRenderer::class)->render($meeting->agenda, 'meeting:'.$meeting->id) !!}</div>
                @endif
            @endif

            <h2>{{ __('events.registration') }}</h2>

            @if ($meeting->date_unknown || $meeting->starts_on->isPast())
                <p>{{ __('delegates.closed') }}</p>
            @else
                <p>{{ __('events.registration_body') }}</p>
                <p>
                    <a class="c-btn c-btn--primary" href="{{ route('events.register', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}">
                        {{ __('delegates.register_action') }}
                    </a>
                    @if ($meeting->registration_url)
                        {{-- The website links out. Registration for the
                             meeting itself is run on the Event Management
                             Platform; this platform collects arrival and
                             departure details and nothing more. --}}
                        <a class="c-btn c-btn--secondary" href="{{ $meeting->registration_url }}" rel="noopener">{{ __('events.register_elsewhere') }}</a>
                    @endif
                </p>
            @endif
        </div>
    </div>
@endsection
