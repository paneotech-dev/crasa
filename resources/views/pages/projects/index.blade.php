{{-- Projects. WS-12's no-content-loss clause: the legacy site carries
     these behind a top-level menu item and WS-03's IA lists neither. --}}
@extends('layouts.public')

@section('title', __('projects.title'))
@section('description', __('projects.meta_description'))

@section('content')
    <x-section-band
        :eyebrow="__('projects.band_eyebrow')"
        :title="__('projects.band_heading')"
        :lead="__('projects.lead')"
    />

    <div class="c-section">
        <div class="c-page">
            {{-- Two states, not two collections. The links filter one list
                 rather than sending a reader to a second page, so a project
                 that finishes keeps its URL. --}}
            <nav class="c-filters__options" aria-label="{{ __('projects.title') }}">
                <a class="c-chip" @if ($state === null) aria-current="page" @endif
                   href="{{ route('projects', ['locale' => app()->getLocale()]) }}">{{ __('projects.all') }}</a>
                <a class="c-chip" @if ($state === 'current') aria-current="page" @endif
                   href="{{ route('projects', ['locale' => app()->getLocale(), 'state' => 'current']) }}">{{ __('projects.current') }} ({{ $currentCount }})</a>
                <a class="c-chip" @if ($state === 'past') aria-current="page" @endif
                   href="{{ route('projects', ['locale' => app()->getLocale(), 'state' => 'past']) }}">{{ __('projects.past') }} ({{ $pastCount }})</a>
            </nav>

            @if ($projects->isEmpty())
                @include('blocks._empty', [
                    'title' => __('projects.none_title'),
                    'body' => __('projects.none_body'),
                ])
            @else
                {{-- The newsroom's card, because a project listing is the
                     same shape of thing and a second card design would be a
                     second card design. --}}
                <div class="b-cards">
                    @foreach ($projects as $project)
                        @php($title = $project->translate('title'))
                        @php($summary = $project->translate('summary'))
                        <article class="b-card">
                            @if ($project->hero_image_path)
                                <div class="b-card__media">
                                    <img class="c-img c-img--cover" alt="" loading="lazy" decoding="async"
                                         src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($project->hero_image_path) }}">
                                </div>
                            @endif
                            <div class="b-card__body">
                                <div class="b-card__kicker">{{ $project->isCurrent() ? __('projects.state_current') : __('projects.state_past') }}</div>
                                @if ($title)
                                    <h2 class="b-card__title" @if ($title->isFallback()) lang="{{ $title->locale }}" @endif>
                                        <a href="{{ route('projects.show', ['locale' => app()->getLocale(), 'project' => $project->slug]) }}">{{ $title->value }}</a>
                                    </h2>
                                @endif
                                @if ($summary)
                                    <p class="b-card__summary">{{ \Illuminate\Support\Str::limit($summary->value, 180) }}</p>
                                @endif
                                @if ($project->sector)
                                    <div class="b-card__meta"><span>{{ $project->sector->name }}</span></div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
