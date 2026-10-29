@php
    $title = $publication->translate('title');
    $document = $publication->primaryDocument();
    $superseded = $publication->supersededBy->isNotEmpty();
    $headingTag = $headingTag ?? 'h2';
@endphp

{{-- A document card (UI system): thumbnail in a 4:3 frame, type label,
     title, metadata, Download. The whole card opens the publication through
     its title link; Download sits above the stretched hit area. --}}
<article class="u-card cp-doc-card">
    <div class="u-card__media u-card__media--doc cp-doc-card__media">
        @if ($document?->thumbnail_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->thumbnail_path) }}"
                 alt="" loading="lazy" decoding="async" width="300" height="225">
        @else
            <span class="cp-placeholder" aria-hidden="true">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>
                {{ $publication->typeLabel() }}
            </span>
        @endif
    </div>
    <div class="u-card__body">
        <span class="u-card__label">{{ $publication->typeLabel() }}@if ($publication->sector), {{ $publication->sector->name }}@endif</span>
        <{{ $headingTag }} class="u-card__title">
            <a href="{{ route('publications.show', ['locale' => app()->getLocale(), 'slug' => $publication->slug]) }}"
               @if ($title?->isFallback()) lang="{{ $title->locale }}" @endif>{{ $title?->value ?? $publication->slug }}</a>
        </{{ $headingTag }}>
        <div class="u-card__meta">
            <span>{{ $publication->published_on?->format('Y') ?? __('office.date_not_recorded') }}</span>
            @if ($publication->country)<span>{{ $publication->country->name }}</span>@endif
            @if ($document)
                <span>{{ $document->readableSize() }}</span>
                <span class="u-pill u-pill--muted cp-lang">{{ strtoupper($document->locale) }}</span>
            @endif
            @if ($superseded)
                <span class="u-pill u-pill--muted">{{ __('publications.superseded') }}</span>
            @endif
        </div>
    </div>
    @if ($document)
        <div class="u-card__actions">
            <a class="u-btn u-btn--secondary cp-doc-card__dl" href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
                {{ __('blocks.download') }}<span class="c-sr">: {{ $title?->value ?? $publication->slug }}</span>
            </a>
        </div>
    @endif
</article>
