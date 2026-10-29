@php
    $title = $publication->translate('title');
    $document = $publication->primaryDocument();
    $superseded = $publication->supersededBy->isNotEmpty();
    $headingTag = $headingTag ?? 'h2';
    $format = $document ? strtoupper(pathinfo((string) $document->original_filename, PATHINFO_EXTENSION)) : null;
@endphp

{{-- A document card (UI system): thumbnail in a 4:3 frame, type label,
     title, one grey line of metadata (year, size, language), and a text
     link "Download (PDF, 440 KB)": the library has no button per card. The
     whole card opens the publication through its title link; the download
     link sits above the stretched hit area. --}}
<article class="u-card cp-doc-card">
    <div class="u-card__media u-card__media--doc cp-doc-card__media">
        @if ($document?->thumbnail_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->thumbnail_path) }}"
                 alt="" loading="lazy" decoding="async" width="300" height="225">
        @else
            <span class="cp-placeholder" aria-hidden="true"><img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="56" height="56"></span>
        @endif
    </div>
    <div class="u-card__body">
        <span class="u-card__label">{{ __($publication->publication_type->labelKey()) }}@if ($publication->sector), {{ $publication->sector->name }}@endif</span>
        <{{ $headingTag }} class="u-card__title">
            <a href="{{ route('publications.show', ['locale' => app()->getLocale(), 'slug' => $publication->slug]) }}"
               @if ($title?->isFallback()) lang="{{ $title->locale }}" @endif>{{ $title?->value ?? $publication->slug }}</a>
        </{{ $headingTag }}>
        <div class="u-card__meta">
            <span>{{ $publication->published_on?->format('Y') ?? __('office.date_not_recorded') }}</span>
            @if ($publication->country)<span>{{ $publication->country->name }}</span>@endif
            @if ($document)
                <span>{{ $document->readableSize() }}</span>
                <span class="cp-lang">{{ strtoupper($document->locale) }}</span>
            @endif
            @if ($superseded)
                <span class="u-pill u-pill--muted">{{ __('publications.superseded') }}</span>
            @endif
        </div>
    </div>
    @if ($document)
        <div class="u-card__actions">
            <a class="cp-doc-card__dl" href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ __('d3_polish.download_with', ['facts' => collect([$format, $document->readableSize()])->filter()->implode(', ')]) }}<span class="c-sr">: {{ $title?->value ?? $publication->slug }}</span></a>
        </div>
    @endif
</article>
