@php
    $title = $publication->translate('title');
    $document = $publication->primaryDocument();
    $superseded = $publication->supersededBy->isNotEmpty();
@endphp

{{-- The whole row opens the publication; the download button is a second
     destination raised above the stretched hit area. Only the title carries
     the link, so a screen reader hears one link named after the document
     rather than the whole row's text read out as a link. --}}
<div class="b-row">
    <div class="b-row__thumb">
        @if ($document?->thumbnail_path)
            <img class="c-img" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->thumbnail_path) }}"
                 alt="" loading="lazy" decoding="async" width="80">
        @else
            {{-- No thumbnail is a normal state, not an error. The file-type
                 mark stands in for it. --}}
            <span class="c-filetype" aria-hidden="true">{{ $document ? strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION)) : '—' }}</span>
        @endif
    </div>

    <div class="b-row__main">
        <div class="b-row__title">
            <a href="{{ route('publications.show', ['locale' => app()->getLocale(), 'slug' => $publication->slug]) }}"
               @if ($title?->isFallback()) lang="{{ $title->locale }}" @endif>
                {{ $title?->value ?? $publication->slug }}
            </a>
        </div>
        <div class="b-row__meta">
            <span>{{ __($publication->publication_type->labelKey()) }}</span>
            @if ($publication->country)<span>{{ $publication->country->name }}</span>@endif
            @if ($publication->sector)<span>{{ $publication->sector->name }}</span>@endif
            <span>{{ $publication->published_on?->format('Y') ?? __('office.date_not_recorded') }}</span>
            @if ($document)
                <span>{{ $document->readableSize() }}</span>
                <span class="c-lang-badge">{{ strtoupper($document->locale) }}</span>
            @endif
        </div>
    </div>

    <div class="b-row__actions">
        @if ($superseded)
            <span class="c-pill c-pill--archived">{{ __('publications.superseded') }}</span>
        @endif
        @if ($document)
            <a class="c-btn c-btn--secondary" href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">
                {{ __('blocks.download') }}
            </a>
        @endif
    </div>
</div>
