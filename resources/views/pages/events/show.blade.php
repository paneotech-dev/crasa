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
                <div class="c-prose">{!! nl2br(e($meeting->agenda)) !!}</div>
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
