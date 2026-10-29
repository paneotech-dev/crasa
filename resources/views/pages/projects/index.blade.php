{{-- Projects (S8, UI system, 29 October 2026). One collection in two
     states: a project that finishes keeps its URL and its documents. --}}
@extends('layouts.public')
@include('partials.cpages.styles')

@section('title', __('projects.title'))
@section('description', __('projects.meta_description'))

@php
    $locale = app()->getLocale();
    // The same photograph twice on one page reads as a page that ran out of
    // pictures: a repeat falls back to the sector panel.
    $seen = [];
@endphp

@section('content')
    @include('partials.cpages.hero', [
        'eyebrow' => __('projects.band_eyebrow'),
        'title' => __('projects.band_heading'),
        'lead' => __('projects.lead'),
        'figures' => [
            ['value' => $currentCount, 'label' => __('cpages_a.figure_current')],
            ['value' => $pastCount, 'label' => __('cpages_a.figure_completed')],
        ],
        'heroAside' => ($partnerNames = \App\Models\Project::published()->whereNotNull('partner')->pluck('partner')
                ->flatMap(fn ($p) => array_map('trim', explode(',', $p)))->filter()->unique()->values())->isNotEmpty()
            ? view('pages.projects._hero-aside', ['partners' => $partnerNames])->render()
            : null,
    ])

    <section class="u-section">
        <div class="u-container">
            <div class="cp-section-head">
                <h2 class="u-h2">{{ $state === 'current' ? __('cpages_a.figure_current') : ($state === 'past' ? __('cpages_a.figure_completed') : __('projects.all')) }}</h2>
                {{-- Links, so the toggle works without script and each state has an address. --}}
                <nav class="cp-chips" aria-label="{{ __('cpages_a.filter_label') }}">
                    <a class="u-chip" @if ($state === null) aria-current="page" @endif href="{{ route('projects', ['locale' => $locale]) }}">{{ __('cpages_a.tab_all') }}</a>
                    <a class="u-chip" @if ($state === 'current') aria-current="page" @endif href="{{ route('projects', ['locale' => $locale, 'state' => 'current']) }}">{{ __('cpages_a.tab_current') }} ({{ $currentCount }})</a>
                    <a class="u-chip" @if ($state === 'past') aria-current="page" @endif href="{{ route('projects', ['locale' => $locale, 'state' => 'past']) }}">{{ __('cpages_a.tab_past') }} ({{ $pastCount }})</a>
                </nav>
            </div>

            @if ($projects->isEmpty())
                @include('blocks._empty', [
                    'title' => __('projects.none_title'),
                    'body' => __('projects.none_body'),
                ])
            @else
                {{-- Two or four projects sit two to a row as wide cards, so no row is
                     left two thirds empty. --}}
                <ul class="{{ in_array($projects->count(), [1, 2, 4], true) ? 'u-cols-2 cp-proj-grid--wide' : 'u-cols-3' }} cp-proj-grid" role="list">
                    @foreach ($projects as $project)
                        @php
                            $image = $project->hero_image_path && ! in_array($project->hero_image_path, $seen, true) ? $project->hero_image_path : null;
                            if ($image) { $seen[] = $image; }
                        @endphp
                        @include('pages.projects._card', ['project' => $project, 'image' => $image])
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages_a.projects_cta_title'),
        'ctaText' => __('cpages_a.projects_cta_text'),
        'ctaPrimary' => [__('cpages_a.browse_library'), route('publications', ['locale' => $locale])],
        'ctaSecondary' => [__('cpages_a.news_cta'), route('newsroom', ['locale' => $locale])],
    ])
@endsection
