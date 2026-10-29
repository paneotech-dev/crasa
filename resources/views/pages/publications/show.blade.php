{{-- A publication, with its supersession lineage. --}}
@extends('layouts.public')

@php
    $title = $publication->translate('title');
    $summary = $publication->translate('summary');
    $document = $publication->primaryDocument();
    $superseded = $publication->supersededBy->isNotEmpty();
    $loc = app()->getLocale();
    // Related documents: the same sector first, then the same type; only
    // listed publications, so nothing without a file appears.
    $related = \App\Models\Publication::listed()
        ->whereKeyNot($publication->getKey())
        ->when($publication->sector_id, fn ($q) => $q->where('sector_id', $publication->sector_id), fn ($q) => $q->where('publication_type', $publication->publication_type))
        ->with(['translations', 'documents', 'sector', 'country', 'supersededBy'])
        ->orderByDesc('published_on')
        ->take(3)
        ->get();
@endphp

@section('title', $title?->value ?? __('publications.title'))
@if ($summary)
    @section('description', $summary->value)
@endif

@section('content')
    @include('partials.cpages.styles')

    <section class="u-hero cp-hero cp-doc-hero">
        <div class="u-container">
            @if ($superseded)
                {{-- A reader who arrives at a superseded document from a
                     search result or an old citation must be told before
                     they read it, not after. --}}
                <div class="c-notice c-notice--pending cp-doc-notice" role="status">
                    <svg class="c-notice__icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="var(--c-amber-dark)" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M12 9v4"></path><path d="M12 17h.01"></path>
                        <path d="M10.3 3.9L2.4 18a1.8 1.8 0 001.6 2.7h16a1.8 1.8 0 001.6-2.7L13.7 3.9a1.8 1.8 0 00-3.4 0z"></path>
                    </svg>
                    <div>
                        <div class="c-notice__title">{{ __('publications.superseded_title') }}</div>
                        <div class="c-notice__body">
                            @foreach ($publication->supersededBy as $replacement)
                                <a href="{{ route('publications.show', ['locale' => $loc, 'slug' => $replacement->slug]) }}">
                                    {{ $replacement->translated('title', $replacement->slug) }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
            <p class="u-eyebrow">{{ __($publication->publication_type->labelKey()) }}@if ($publication->sector), {{ $publication->sector->name }}@endif</p>
            <h1 class="u-h1" @if ($title?->isFallback()) lang="{{ $title->locale }}" @endif>{{ $title?->value ?? $publication->slug }}</h1>
            @if ($summary)
                <p class="u-lead">{{ $summary->value }}</p>
            @endif
            @if ($title?->isFallback())
                <x-fallback-notice />
            @endif
        </div>
    </section>

    <section class="u-section">
        <div class="u-container">

            <div class="u-main-side">
                <div class="cp-doc-main">
                    @if ($document)
                        <section class="cp-viewer" aria-labelledby="cp-viewer-title">
                            <h2 class="c-sr" id="cp-viewer-title">{{ __('cpages_b.viewer_title') }}</h2>
                            <div class="cp-viewer__frame">
                                @if ($document->thumbnail_path)
                                    <a href="{{ route('documents.show', ['locale' => $loc, 'document' => $document->id]) }}" class="cp-viewer__page" tabindex="-1" aria-hidden="true">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->thumbnail_path) }}"
                                             alt="{{ __('publications.preview_alt') }}" decoding="async" width="600" height="800">
                                    </a>
                                @else
                                    <div class="cp-placeholder cp-viewer__empty">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg>
                                        {{ __('publications.no_preview') }}
                                    </div>
                                @endif
                            </div>
                            <div class="cp-viewer__bar">
                                <div class="u-actions">
                                    <a class="u-btn u-btn--primary" href="{{ route('documents.download', ['locale' => $loc, 'document' => $document->id]) }}">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
                                        {{ __('publications.download_file', ['size' => $document->readableSize()]) }}
                                    </a>
                                    <a class="u-btn u-btn--secondary" href="{{ route('documents.show', ['locale' => $loc, 'document' => $document->id]) }}">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
                                        {{ __('cpages_b.open_document') }}
                                    </a>
                                    {{-- Save keeps a copy: once the service worker is in
                                         control it also stores the file for offline reading
                                         (screen 15), and without one it saves the file. --}}
                                    <a class="u-btn u-btn--ghost" href="{{ route('documents.download', ['locale' => $loc, 'document' => $document->id]) }}" download
                                       aria-describedby="cp-save-hint"
                                       data-save-offline="{{ route('documents.download', ['locale' => $loc, 'document' => $document->id]) }}"
                                       data-saving-label="{{ __('app.saving') }}"
                                       data-saved-label="{{ __('app.saved') }}"
                                       data-refused-label="{{ __('app.save_refused') }}">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4z"/></svg>
                                        {{ __('app.save_offline') }}
                                    </a>
                                </div>
                                <p class="u-help" id="cp-save-hint">{{ __('cpages_b.save_hint') }} {{ __('publications.served_here') }}</p>
                            </div>
                        </section>
                    @elseif ($publication->isExternal())
                        {{-- Published elsewhere, with no file of its own (UX-14). --}}
                        <p><a class="u-btn u-btn--primary" href="{{ $publication->external_url }}" rel="noopener external">{{ __('office.publication_external_open') }}</a></p>
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
                    <h2 id="lineage-heading" class="u-h3">{{ __('publications.lineage') }}</h2>
                    <p class="c-small">{{ __('publications.lineage_note') }}</p>

                    <ol class="c-lineage__track">
                        @foreach ($lineage as $index => $step)
                            @php
                                $isCurrent = $step->is($publication);
                                $isLatest = $step->supersededBy->isEmpty();
                            @endphp
                            <li class="c-lineage__node @if ($isCurrent) is-current @endif @if ($isLatest) is-latest @endif">
                                <span class="c-lineage__year c-num">{{ $step->published_on?->format('Y') ?? __('office.no_date_cite') }}</span>

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

                <aside class="cp-doc-side" aria-label="{{ __('cpages_b.about_document') }}">
                    <div class="u-panel">
                        <h2 class="u-h3">{{ __('cpages_b.about_document') }}</h2>
                        <dl class="u-facts">
                            <div><dt>{{ __('publications.type') }}</dt><dd>{{ __($publication->publication_type->labelKey()) }}</dd></div>
                            <div><dt>{{ __('publications.published') }}</dt><dd>{{ $publication->published_on?->translatedFormat('j F Y') ?? __('office.date_not_recorded') }}</dd></div>
                            @if ($publication->sector)
                                <div><dt>{{ __('publications.sector') }}</dt><dd>{{ $publication->sector->name }}</dd></div>
                            @endif
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
                    </div>

                    @if ($document)
                        {{-- "Available in", screen 07: a missing language is
                             stated rather than left to be inferred. --}}
                        <div class="u-panel">
                            <h2 class="u-h3">{{ __('publications.available_in') }}</h2>
                            <ul class="cp-doc-langs__list" role="list">
                                @foreach (\App\Support\Locales::SUPPORTED as $code)
                                    @php $file = $publication->documents->firstWhere('locale', $code); @endphp
                                    <li @class(['cp-doc-langs__item', 'is-missing' => ! $file])>
                                        <span class="u-pill {{ $file ? 'u-pill--ok' : 'u-pill--muted' }}">{{ strtoupper($code) }}</span>
                                        @if ($file)
                                            <a href="{{ route('documents.download', ['locale' => $loc, 'document' => $file->id]) }}">{{ \App\Support\Locales::nameOf($code) }}</a>
                                            <span class="u-muted u-small">{{ $file->readableSize() }}</span>
                                        @else
                                            <span>{{ \App\Support\Locales::nameOf($code) }}</span>
                                            <span class="u-muted u-small">{{ __('publications.language_missing') }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="u-panel u-panel--tint">
                            <h2 class="u-h3">{{ __('publications.cite') }}</h2>
                            <p class="cp-doc-cite__ref">{{ __('publications.cite_reference', [
                                'author' => __('site.acronym'),
                                'year' => $publication->published_on?->format('Y') ?? __('office.no_date_cite'),
                                'title' => $publication->translated('title', $publication->slug),
                                'url' => route('publications.show', ['locale' => $loc, 'slug' => $publication->slug]),
                            ]) }}</p>
                            <p class="u-help">{{ __('publications.cite_note') }}</p>
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="u-section u-section--blue cp-related" aria-labelledby="cp-related-title">
            <div class="u-container">
                <div class="cp-section-head">
                    <h2 class="u-h2" id="cp-related-title">{{ __('cpages_b.related_documents') }}</h2>
                    <a class="u-btn u-btn--secondary" href="{{ route('publications', ['locale' => $loc]) }}">{{ __('cpages_b.cta_library') }}</a>
                </div>
                <ul class="u-cols-3 cp-doc-grid" role="list">
                    @foreach ($related as $item)
                        <li>@include('pages.publications._row', ['publication' => $item, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages_b.cta_document_title'),
        'ctaText' => __('cpages_b.cta_document_text'),
        'ctaPrimary' => [__('cpages_b.cta_library'), route('publications', ['locale' => $loc])],
        'ctaSecondary' => [__('cpages_b.cta_comparator'), route('comparator', ['locale' => $loc])],
    ])
@endsection
