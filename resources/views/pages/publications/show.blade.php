{{-- A publication, with its supersession lineage. --}}
@extends('layouts.public')

@php
    $title = $publication->translate('title');
    $summary = $publication->translate('summary');
    $document = $publication->primaryDocument();
    $superseded = $publication->supersededBy->isNotEmpty();
@endphp

@section('title', $title?->value ?? __('publications.title'))
@if ($summary)
    @section('description', $summary->value)
@endif

@section('content')
    <div class="c-section">
        <div class="c-page c-page--reading">
            <nav class="c-breadcrumb" aria-label="{{ __('publications.breadcrumb') }}">
                <a href="{{ route('publications', ['locale' => app()->getLocale()]) }}">{{ __('publications.title') }}</a>
                <span aria-hidden="true">/</span>
                <span>{{ __($publication->publication_type->labelKey()) }}</span>
            </nav>

            @if ($superseded)
                {{-- A reader who arrives at a superseded document from a
                     search result or an old citation must be told before
                     they read it, not after. --}}
                <div class="c-notice c-notice--pending" role="status">
                    <svg class="c-notice__icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="var(--c-amber-dark)" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M12 9v4"></path><path d="M12 17h.01"></path>
                        <path d="M10.3 3.9L2.4 18a1.8 1.8 0 001.6 2.7h16a1.8 1.8 0 001.6-2.7L13.7 3.9a1.8 1.8 0 00-3.4 0z"></path>
                    </svg>
                    <div>
                        <div class="c-notice__title">{{ __('publications.superseded_title') }}</div>
                        <div class="c-notice__body">
                            @foreach ($publication->supersededBy as $replacement)
                                <a href="{{ route('publications.show', ['locale' => app()->getLocale(), 'slug' => $replacement->slug]) }}">
                                    {{ $replacement->translated('title', $replacement->slug) }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <h1 @if ($title?->isFallback()) lang="{{ $title->locale }}" @endif>{{ $title?->value ?? $publication->slug }}</h1>

            @if ($title?->isFallback())
                <x-fallback-notice />
            @endif

            <dl class="c-meta">
                <div><dt>{{ __('publications.type') }}</dt><dd>{{ __($publication->publication_type->labelKey()) }}</dd></div>
                <div><dt>{{ __('publications.published') }}</dt><dd>{{ $publication->published_on->translatedFormat('j F Y') }}</dd></div>
                @if ($publication->country)
                    <div><dt>{{ __('publications.country') }}</dt><dd>{{ $publication->country->name }}</dd></div>
                @endif
                @if ($publication->committee)
                    <div><dt>{{ __('publications.committee') }}</dt><dd>{{ $publication->committee->name }}</dd></div>
                @endif
                @if ($document)
                    <div><dt>{{ __('publications.downloads') }}</dt><dd class="c-num">{{ number_format($publication->downloadCount()) }}</dd></div>
                @endif
            </dl>

            @if ($summary)
                <p class="c-lead">{{ $summary->value }}</p>
            @endif

            @if ($document)
                <div class="c-preview">
                    <div class="c-preview__pane">
                        @if ($document->thumbnail_path)
                            <img class="c-img" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->thumbnail_path) }}"
                                 alt="{{ __('publications.preview_alt') }}" loading="lazy" decoding="async">
                        @else
                            <div class="b__slot" style="aspect-ratio: 3/2">
                                <div>
                                    <p class="b__slot-note">{{ __('publications.no_preview') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="c-preview__actions">
                        <a class="c-btn c-btn--primary" href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">
                            {{ __('publications.download_file', ['size' => $document->readableSize()]) }}
                        </a>
                        <a class="c-btn c-btn--secondary" href="{{ route('documents.show', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">
                            {{ __('publications.open_in_browser') }}
                        </a>
                        {{-- Screen 15: "any document you explicitly save".
                             Nothing is downloaded to a device because a
                             reader happened to open a page; a document is
                             kept because they asked.

                             Hidden until a service worker is in control,
                             because a button that does nothing is worse
                             than a button that is not there. --}}
                        <button class="c-btn c-btn--secondary" type="button" hidden
                                data-save-offline="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}"
                                data-saving-label="{{ __('app.saving') }}"
                                data-saved-label="{{ __('app.saved') }}"
                                data-refused-label="{{ __('app.save_refused') }}">
                            {{ __('app.save_offline') }}
                        </button>
                        <p class="c-preview__note">{{ __('publications.served_here') }}</p>
                    </div>
                </div>

                {{-- "Available in", screen 07.

                     A regulator who works in French needs to know before
                     they download whether this document exists in French.
                     Saying "English only" is more useful than letting them
                     open it and find out, and a language that is missing is
                     stated rather than left to be inferred from a list of
                     one. --}}
                <div class="c-languages">
                    <h2 class="c-languages__title">{{ __('publications.available_in') }}</h2>
                    <ul class="c-languages__list" role="list">
                        @foreach (\App\Support\Locales::SUPPORTED as $code)
                            @php $file = $publication->documents->firstWhere('locale', $code); @endphp
                            <li @class(['c-languages__item', 'is-missing' => ! $file])>
                                <span class="c-lang-badge">{{ strtoupper($code) }}</span>
                                @if ($file)
                                    <a href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $file->id]) }}">
                                        {{ \App\Support\Locales::nameOf($code) }}
                                    </a>
                                    <span class="c-languages__size">{{ $file->readableSize() }}</span>
                                @else
                                    {{ \App\Support\Locales::nameOf($code) }}
                                    <span class="c-languages__size">{{ __('publications.language_missing') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Share and cite, screen 07.

                     A document that will be quoted in a ministerial brief
                     needs a citation, and a reader who has to assemble one
                     by hand assembles it differently every time. The
                     permanent URL is the point: the slug never changes,
                     because a citation has to keep resolving. --}}
                <div class="c-cite">
                    <h2 class="c-cite__title">{{ __('publications.cite') }}</h2>
                    <p class="c-cite__reference">{{ __('publications.cite_reference', [
                        'author' => __('site.acronym'),
                        'year' => $publication->published_on->format('Y'),
                        'title' => $publication->translated('title', $publication->slug),
                        'url' => route('publications.show', ['locale' => app()->getLocale(), 'slug' => $publication->slug]),
                    ]) }}</p>
                    <p class="c-cite__note">{{ __('publications.cite_note') }}</p>
                </div>
            @endif

            @if ($lineage->count() > 1)
                {{--
                    The lineage is this page's one bold moment.

                    Regulators care about this more than anything else here:
                    which edition is in force, what it replaced, and what
                    replaced it. An indented list states that; a spine with
                    the current edition marked shows it, and shows the shape
                    of the chain at a glance.
                --}}
                <section class="c-lineage" aria-labelledby="lineage-heading">
                    <h2 id="lineage-heading" class="b__heading">{{ __('publications.lineage') }}</h2>
                    <p class="c-small">{{ __('publications.lineage_note') }}</p>

                    <ol class="c-lineage__track">
                        @foreach ($lineage as $index => $step)
                            @php
                                $isCurrent = $step->is($publication);
                                $isLatest = $step->supersededBy->isEmpty();
                            @endphp
                            <li class="c-lineage__node @if ($isCurrent) is-current @endif @if ($isLatest) is-latest @endif">
                                <span class="c-lineage__year c-num">{{ $step->published_on->format('Y') }}</span>

                                <span class="c-lineage__marker" aria-hidden="true"></span>

                                <span class="c-lineage__body">
                                    @if ($isCurrent)
                                        <span class="c-lineage__title">{{ $step->translated('title', $step->slug) }}</span>
                                    @else
                                        <a class="c-lineage__title" href="{{ route('publications.show', ['locale' => app()->getLocale(), 'slug' => $step->slug]) }}">
                                            {{ $step->translated('title', $step->slug) }}
                                        </a>
                                    @endif

                                    <span class="c-lineage__state">
                                        @if ($isLatest)
                                            <span class="c-pill c-pill--validated">{{ __('publications.in_force') }}</span>
                                        @else
                                            <span class="c-pill c-pill--archived">{{ __('publications.superseded') }}</span>
                                        @endif
                                        @if ($isCurrent)
                                            <span class="c-lineage__here">{{ __('publications.you_are_here') }}</span>
                                        @endif
                                    </span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

        </div>
    </div>
@endsection
