{{-- One newsroom card: a 16:10 frame, the title, the excerpt and one line of
     metadata. An item without its own photograph gets the placeholder of its
     type, never a photograph borrowed from another item. --}}
@php
    $title = $item->translate('title');
    $summary = $item->translate('summary') ?? $item->translate('body');
    $url = route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $item->slug]);
    $featured ??= false;
@endphp

<article @class(['b-card', 'n-card', 'n-card--featured' => $featured])>
    <div class="n-card__media">
        @if ($item->hero_image_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->hero_image_path) }}"
                 alt="" loading="{{ $featured ? 'eager' : 'lazy' }}" decoding="async">
        @else
            <img src="{{ \App\Support\NewsFormat::placeholder($item->category->value) }}"
                 alt="" loading="lazy" decoding="async">
        @endif
    </div>
    <div class="n-card__body">
        @include('pages.newsroom._meta', ['item' => $item])
        @if ($title)
            <h2 class="n-card__title" @if ($title->isFallback()) lang="{{ $title->locale }}" @endif>
                <a href="{{ $url }}">{{ $title->value }}</a>
            </h2>
        @endif
        @if ($summary)
            <p class="n-card__summary" @if ($summary->isFallback()) lang="{{ $summary->locale }}" @endif>{{ \App\Support\NewsFormat::excerpt($summary->value) }}</p>
        @endif
        @if ($featured)
            <p class="n-card__more"><a href="{{ $url }}" tabindex="-1" aria-hidden="true">{{ __('news.read_more') }}</a></p>
        @endif
    </div>
</article>
