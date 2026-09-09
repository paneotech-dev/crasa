@extends('layouts.public')

@section('title', __('events.title'))
@section('description', __('events.meta_description'))

@section('content')
    <div class="c-section c-section--title">
        <div class="c-page">
            <h1>{{ __('events.title') }}</h1>
            <p class="c-lead">{{ __('events.lead') }}</p>
        </div>
    </div>

    <div class="c-section">
        <div class="c-page">
            <h2>{{ __('events.upcoming') }}</h2>

            @if ($upcoming->isEmpty())
                <div class="c-empty">
                    <p class="c-empty__title">{{ __('events.none_upcoming_title') }}</p>
                    <p class="c-empty__body">{{ __('events.none_upcoming_body') }}</p>
                </div>
            @else
                <ul class="c-events">
                    @foreach ($upcoming as $meeting)
                        <li class="c-event">
                            <p class="c-event__when">
                                <time datetime="{{ $meeting->starts_on->toDateString() }}">{{ $meeting->starts_on->isoFormat('D MMMM YYYY') }}</time>
                                @if ($meeting->ends_on && ! $meeting->ends_on->isSameDay($meeting->starts_on))
                                    <span>{{ __('extranet.until', ['date' => $meeting->ends_on->isoFormat('D MMMM')]) }}</span>
                                @endif
                            </p>
                            <h3 class="c-event__title">
                                <a href="{{ route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}">{{ $meeting->title }}</a>
                            </h3>
                            @if ($meeting->location)
                                <p class="c-event__where">{{ $meeting->location }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($past->isNotEmpty())
                <h2>{{ __('events.past') }}</h2>
                <ul class="c-events">
                    @foreach ($past as $meeting)
                        <li class="c-event">
                            <p class="c-event__when">
                                <time datetime="{{ $meeting->starts_on->toDateString() }}">{{ $meeting->starts_on->isoFormat('D MMMM YYYY') }}</time>
                            </p>
                            <h3 class="c-event__title">
                                <a href="{{ route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}">{{ $meeting->title }}</a>
                            </h3>
                            @if ($meeting->location)<p class="c-event__where">{{ $meeting->location }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
