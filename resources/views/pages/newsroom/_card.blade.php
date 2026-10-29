{{-- One newsroom card: a 16:10 frame, the title, the excerpt and one line of
     metadata. An item without its own photograph gets the placeholder of its
     type, never a photograph borrowed from another item. --}}
@php
    $title = $item->translate('title');
    $summary = $item->translate('summary') ?? $item->translate('body');
    $url = route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $item->slug]);
    $featured ??= false;
    // h2 on the newsroom; h3 where the card sits under a section heading.
    $titleTag = ($headingLevel ?? 2) === 3 ? 'h3' : 'h2';
@endphp

<article @class(['b-card', 'n-card', 'n-card--featured' => $featured])>
    <div class="n-card__media">
        @if ($item->hero_image_path)
            @php $photo = \App\Support\ResizedImage::attributes($item->hero_image_path, 'card'); @endphp
            <img src="{{ $photo['src'] }}"
                 @if ($photo['srcset']) srcset="{{ $photo['srcset'] }}" sizes="{{ $featured ? '(max-width: 760px) 100vw, 50vw' : '(max-width: 760px) 100vw, (max-width: 1100px) 50vw, 33vw' }}" @endif
                 @if ($photo['width'] && $photo['height']) width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" @endif
                 alt="" loading="{{ $featured ? 'eager' : 'lazy' }}" @if ($featured) fetchpriority="high" @endif decoding="async">
        @else
            <img src="{{ \App\Support\NewsFormat::placeholder($item->category->value) }}"
                 alt="" loading="lazy" decoding="async">
        @endif
    </div>
    <div class="n-card__body">
        @include('pages.newsroom._meta', ['item' => $item])
        @if ($title)
            <{{ $titleTag }} class="n-card__title" @if ($title->isFallback()) lang="{{ $title->locale }}" @endif>
                <a href="{{ $url }}">{{ $title->value }}</a>
            </{{ $titleTag }}>
        @endif
        @if ($summary)
            <p class="n-card__summary" @if ($summary->isFallback()) lang="{{ $summary->locale }}" @endif>{{ \App\Support\NewsFormat::excerpt($summary->value) }}</p>
        @endif
        @if ($featured)
            <p class="n-card__more"><a href="{{ $url }}" tabindex="-1" aria-hidden="true">{{ __('news.read_more') }}</a></p>
        @endif
    </div>
</article>
