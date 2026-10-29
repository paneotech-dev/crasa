{{-- A newsroom card on the UI system (C4): 16:10 frame, title, one line,
     date. The type is named only when it is not plain news (the section
     already says news): a press release, a tender or a consultation. An item
     without its own photograph gets the CRASA mark on white, with no label,
     never a photograph borrowed from another item. --}}
@php
    $title = $item->translate('title');
    $summary = $item->translate('summary') ?? $item->translate('body');
    $url = route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $item->slug]);
    $featured ??= false;
    $titleTag = ($headingLevel ?? 2) === 3 ? 'h3' : 'h2';
    $typeLabel = $item->categorySlug() === 'news' ? null : $item->categoryLabel();
@endphp

<article @class(['u-card', 'cp-news-card', 'cp-news-card--featured' => $featured])>
    <div class="u-card__media cp-news-card__media">
        @if ($item->hero_image_path)
            @php $photo = \App\Support\ResizedImage::attributes($item->hero_image_path, 'card'); @endphp
            <img src="{{ $photo['src'] }}"
                 @if ($photo['srcset']) srcset="{{ $photo['srcset'] }}" sizes="{{ $featured ? '(max-width: 760px) 100vw, 60vw' : '(max-width: 640px) 100vw, (max-width: 1100px) 50vw, 33vw' }}" @endif
                 @if ($photo['width'] && $photo['height']) width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" @endif
                 alt="" loading="{{ $featured ? 'eager' : 'lazy' }}" @if ($featured) fetchpriority="high" @endif decoding="{{ $featured ? 'sync' : 'async' }}" @if ($item->hero_focal_x !== null) style="object-position: {{ $item->heroPosition() }}" @endif>
        @else
            <span class="cp-news-ph" aria-hidden="true">
                <img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="56" height="56">
            </span>
        @endif
    </div>
    <div class="u-card__body">
        <p class="cp-news-card__line">
            @if ($typeLabel)<span class="u-card__label">{{ $typeLabel }}</span>@endif
            <span class="cp-news-card__date">{{ $item->date_unknown ? __('events.date_not_stated') : \App\Support\NewsFormat::longDate($item->published_at) }}</span>
        </p>
        @if ($title)
            <{{ $titleTag }} class="u-card__title" @if ($title->isFallback()) lang="{{ $title->locale }}" @endif>
                <a href="{{ $url }}">{{ $title->value }}</a>
            </{{ $titleTag }}>
        @endif
        @if ($summary)
            <p class="u-card__text cp-news-card__text" @if ($summary->isFallback()) lang="{{ $summary->locale }}" @endif>{{ \App\Support\NewsFormat::excerpt($summary->value) }}</p>
        @endif
        @if ($item->sector)
            <div class="u-card__meta"><span>{{ $item->sector->name }}</span></div>
        @endif
    </div>
    @if ($featured)
        <div class="u-card__actions">
            <a class="u-btn u-btn--primary" href="{{ $url }}">{{ __('cpages_b.read_more') }}<span class="c-sr">: {{ $title?->value }}</span></a>
        </div>
    @endif
</article>
