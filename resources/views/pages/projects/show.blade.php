{{-- One project. --}}
@extends('layouts.public')

@php
    $title = $project->translate('title');
    $summary = $project->translate('summary');
    $body = $project->translate('body');
@endphp

@section('title', $title?->value ?? __('projects.title'))
@if ($summary)
    @section('description', \Illuminate\Support\Str::limit($summary->value, 155))
@endif

@section('content')
    <article class="c-section">
        <div class="c-page c-page--reading">
            <nav class="c-breadcrumb" aria-label="{{ __('projects.title') }}">
                <a href="{{ route('projects', ['locale' => app()->getLocale()]) }}">{{ __('projects.title') }}</a>
                <span aria-hidden="true">/</span>
                <span>{{ $project->isCurrent() ? __('projects.state_current') : __('projects.state_past') }}</span>
            </nav>

            @if ($title)
                <h1 @if ($title->isFallback()) lang="{{ $title->locale }}" @endif>{{ $title->value }}</h1>
            @endif

            @if ($title?->isFallback())
                <x-fallback-notice />
            @endif

            @if ($summary)
                <p class="c-lead">{{ $summary->value }}</p>
            @endif

            @if ($project->hero_image_path)
                <figure class="c-article__hero">
                    <img class="c-img" alt="" loading="eager" decoding="async"
                         src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($project->hero_image_path) }}">
                </figure>
            @endif

            <dl class="c-meta">
                <div><dt>{{ __('projects.title') }}</dt><dd>{{ $project->isCurrent() ? __('projects.state_current') : __('projects.state_past') }}</dd></div>
                @if ($project->sector)
                    <div><dt>{{ __('projects.sector') }}</dt><dd>{{ $project->sector->name }}</dd></div>
                @endif
                @if ($project->partner)
                    <div><dt>{{ __('projects.partner') }}</dt><dd>{{ $project->partner }}</dd></div>
                @endif
                @if ($project->starts_on)
                    <div><dt>{{ __('projects.period') }}</dt><dd>{{ $project->starts_on->isoFormat('MMMM YYYY') }}@if ($project->ends_on) &ndash; {{ $project->ends_on->isoFormat('MMMM YYYY') }}@endif</dd></div>
                @endif
            </dl>

            @if ($body)
                <div class="c-prose" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                    {!! $body->value !!}
                </div>
            @endif

            @if ($project->documents->isNotEmpty())
                <h2>{{ __('projects.documents') }}</h2>
                <ul class="c-list" role="list">
                    @foreach ($project->documents as $document)
                        <li>
                            <a href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ $document->original_filename }}</a>
                            <span class="c-meta">{{ $document->readableSize() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </article>

    @if ($related->isNotEmpty())
        <div class="c-section">
            <div class="c-page">
                <h2>{{ __('projects.other_projects') }}</h2>
                <div class="b-cards">
                    @foreach ($related as $other)
                        @php($otherTitle = $other->translate('title'))
                        <article class="b-card">
                            <div class="b-card__body">
                                <div class="b-card__kicker">{{ $other->isCurrent() ? __('projects.state_current') : __('projects.state_past') }}</div>
                                @if ($otherTitle)
                                    <h3 class="b-card__title">
                                        <a href="{{ route('projects.show', ['locale' => app()->getLocale(), 'project' => $other->slug]) }}">{{ $otherTitle->value }}</a>
                                    </h3>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endsection
