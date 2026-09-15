@extends('layouts.public')

@section('title', $meeting->title)

@section('content')
    <div class="c-section c-section--title">
        <div class="c-page">
            <p class="b__eyebrow">{{ $meeting->committee?->name ?? __('extranet.regional') }}</p>
            <h1>{{ $meeting->title }}</h1>
            <p class="c-lead">
                <time datetime="{{ $meeting->starts_on->toDateString() }}">{{ $meeting->starts_on->isoFormat('D MMMM YYYY') }}</time>@if ($meeting->ends_on && ! $meeting->ends_on->isSameDay($meeting->starts_on)) {{ __('extranet.until', ['date' => $meeting->ends_on->isoFormat('D MMMM YYYY')]) }}@endif
                @if ($meeting->location) — {{ $meeting->location }}@endif
            </p>
        </div>
    </div>

    <div class="c-section">
        <div class="c-page">
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

            @if ($meeting->starts_on->isPast())
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
