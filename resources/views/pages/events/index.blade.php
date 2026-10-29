@extends('layouts.public')

@section('title', __('events.title'))
@section('description', __('events.meta_description'))

@section('content')
    <x-section-band
        :eyebrow="__('events.band_eyebrow')"
        :title="__('events.band_heading')"
        :lead="__('events.lead')"
        :counts="\App\Support\SectionCounts::events()"
    

        {{-- Events are meetings, which is what the boardroom photograph shows. --}}

        :image="'institutional'"

    />

    <div class="c-section">
        <div class="c-page">
            <h2>{{ __('events.upcoming') }}</h2>

            @if ($upcoming->isEmpty())
                {{-- The platform's own empty state, not a second one. This
                     page had invented c-empty and c-empty__title, neither of
                     which had any CSS, so an events page with nothing on it
                     rendered as two unstyled paragraphs. --}}
                @include('blocks._empty', [
                    'title' => __('events.none_upcoming_title'),
                    'body' => __('events.none_upcoming_body'),
                ])
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
                            @include('pages.events._add-to-calendar', ['meeting' => $meeting, 'compact' => true])
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
                                @if ($meeting->date_unknown)
                                    <span>{{ __('events.date_not_stated') }}</span>
                                @else
                                    <time datetime="{{ $meeting->starts_on->toDateString() }}">{{ $meeting->starts_on->isoFormat('D MMMM YYYY') }}</time>
                                @endif
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
