{{-- Article page. --}}
@extends('layouts.public')

@php
    $title = $item->translate('title');
    $summary = $item->translate('summary');
    $body = $item->translate('body');
    $url = route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $item->slug]);
@endphp

@section('title', $title?->value ?? __('news.title'))
@if ($summary)
    @section('description', $summary->value)
@endif

@section('content')
    @include('partials.cpages.styles')
    @php
        $loc = app()->getLocale();
        $typeLabel = __($item->category->labelKey());
        $dateText = $item->date_unknown ? __('events.date_not_stated') : \App\Support\NewsFormat::longDate($item->published_at);
        $heroPhoto = null;
        if ($item->hero_image_path) {
            $p = \App\Support\ResizedImage::attributes($item->hero_image_path, 'hero');
            // The media library's alt text in the reader's language, when the
            // photograph has one; decorative otherwise, the title carries it.
            $heroAsset = \App\Models\MediaAsset::where('path', $item->hero_image_path)->first();
            $heroAlt = $heroAsset?->mayAppearIn($loc) ? ($heroAsset->translationValuesFor('alt')[$loc] ?? '') : '';
            $heroPhoto = ['src' => $p['src'], 'srcset' => $p['srcset'] ?? null, 'alt' => $heroAlt, 'width' => $p['width'] ?? 1200, 'height' => $p['height'] ?? 800, 'position' => '50% 30%'];
        }
    @endphp

    <section class="u-hero cp-hero cp-article-hero">
        <div class="u-container">
            <div class="u-hero__grid">
                <div>
                    <p class="cp-news-card__line cp-article-hero__line">
                        @if ($item->category->value !== 'news')<span class="u-card__label">{{ $typeLabel }}</span>@endif
                        <span class="cp-news-card__date">{{ $dateText }}</span>
                        @if ($item->sector)<span class="cp-news-card__date">{{ $item->sector->name }}</span>@endif
                    </p>
                    @if ($title)
                        <h1 class="u-h1" @if ($title->isFallback()) lang="{{ $title->locale }}" @endif>{{ $title->value }}</h1>
                    @endif
                    @if ($summary)
                        <p class="u-lead">{{ $summary->value }}</p>
                    @endif
                    @if ($item->showsMachineDraft(['title', 'summary', 'body']))<p class="c-machine-note">{{ __('a11y.machine_draft') }}</p>@endif
                    @if ($title?->isFallback())
                        <x-fallback-notice />
                    @endif
                </div>
                <figure class="u-hero__photo cp-article-hero__photo">
                    @if ($heroPhoto)
                        <img src="{{ $heroPhoto['src'] }}" @if ($heroPhoto['srcset']) srcset="{{ $heroPhoto['srcset'] }}" sizes="(max-width: 991px) 100vw, 500px" @endif
                             width="{{ $heroPhoto['width'] }}" height="{{ $heroPhoto['height'] }}" alt="{{ $heroPhoto['alt'] }}" loading="eager" fetchpriority="high" style="object-position: {{ $heroPhoto['position'] }}">
                    @else
                        <span class="cp-news-ph" aria-hidden="true">
                            <img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="72" height="72">
                        </span>
                    @endif
                </figure>
            </div>
        </div>
    </section>

    <section class="u-section">
        <div class="u-container">
            <div class="u-main-side">
                <article class="cp-article__main">
                    @if ($body)
                        <div class="c-prose cp-article__body" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                            {{-- Through the renderer, which turns the stored <p> and
                                 <sup> into a paragraph and a superscript. --}}
                            {!! app(\App\Services\ContentRenderer::class)->render($body->value, 'news:'.$item->id) !!}
                        </div>
                    @endif

                    @if ($item->tags->isNotEmpty())
                        <nav class="cp-article__tags" aria-label="{{ __('news.tag') }}">
                            @foreach ($item->tags as $tag)
                                <a class="u-chip" href="{{ route('newsroom', ['locale' => $loc, 'tag' => $tag->slug]) }}">{{ $tag->translated('name', $tag->slug) }}</a>
                            @endforeach
                        </nav>
                    @endif

                    <nav class="cp-article__pager" aria-label="{{ __('news.title') }}">
                        @if ($newer)
                            <a class="cp-article__step" rel="prev" href="{{ route('newsroom.show', ['locale' => $loc, 'slug' => $newer->slug]) }}">
                                <span class="cp-article__step-label">{{ __('news.newer') }}</span>
                                <span>{{ \App\Support\NewsFormat::excerpt($newer->translated('title'), 70) }}</span>
                            </a>
                        @endif
                        @if ($older)
                            <a class="cp-article__step cp-article__step--older" rel="next" href="{{ route('newsroom.show', ['locale' => $loc, 'slug' => $older->slug]) }}">
                                <span class="cp-article__step-label">{{ __('news.older') }}</span>
                                <span>{{ \App\Support\NewsFormat::excerpt($older->translated('title'), 70) }}</span>
                            </a>
                        @endif
                    </nav>
                </article>

                <aside class="cp-article__side">
                    <div class="u-panel">
                        <h2 class="u-h3">{{ __('cpages_b.in_this_article') }}</h2>
                        <dl class="u-facts">
                            <div><dt>{{ __('cpages_b.published') }}</dt><dd>{{ $dateText }}</dd></div>
                            <div><dt>{{ __('cpages_b.type') }}</dt><dd>{{ $typeLabel }}</dd></div>
                            @if ($item->sector)
                                <div><dt>{{ __('news.sector') }}</dt><dd>{{ $item->sector->name }}</dd></div>
                            @endif
                        </dl>
                    </div>

                    {{-- Share links, not share widgets. A third-party button
                         script tracks every reader whether they click or not;
                         a plain link tracks nobody. --}}
                    <nav class="u-panel cp-article__share" aria-label="{{ __('news.share') }}">
                        <h2 class="u-h3">{{ __('cpages_b.share') }}</h2>
                        <div class="cp-article__share-row">
                            <a class="u-chip" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($url) }}">LinkedIn</a>
                            <a class="u-chip" rel="noopener" href="https://x.com/intent/tweet?url={{ urlencode($url) }}&amp;text={{ urlencode($title?->value ?? '') }}">X</a>
                            <a class="u-chip" href="mailto:?subject={{ rawurlencode($title?->value ?? '') }}&amp;body={{ rawurlencode($url) }}">{{ __('news.share_email') }}</a>
                        </div>
                        <div class="u-field cp-article__link">
                            <label for="news-link">{{ __('news.copy_link') }}</label>
                            <input class="u-input" id="news-link" type="text" readonly value="{{ $url }}">
                        </div>
                    </nav>

                    <a class="u-btn u-btn--secondary cp-article__back" href="{{ route('newsroom', ['locale' => $loc]) }}">{{ __('cpages_b.back_to_newsroom') }}</a>
                </aside>
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="u-section u-section--blue" aria-labelledby="cp-related-news">
            <div class="u-container">
                <div class="cp-section-head">
                    <h2 class="u-h2" id="cp-related-news">{{ __('cpages_b.related_news') }}</h2>
                    <a class="u-btn u-btn--secondary" href="{{ route('newsroom', ['locale' => $loc]) }}">{{ __('cpages_b.more_news') }}</a>
                </div>
                <ul class="u-cols-3 cp-news-grid" role="list">
                    @foreach ($related->take(3) as $relatedItem)
                        <li>@include('pages.newsroom._ucard', ['item' => $relatedItem, 'featured' => false, 'headingLevel' => 3])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages_b.cta_news_title'),
        'ctaText' => __('cpages_b.cta_news_text'),
        'ctaPrimary' => [__('cpages_b.cta_events'), route('events', ['locale' => $loc])],
        'ctaSecondary' => [__('cpages_b.more_news'), route('newsroom', ['locale' => $loc])],
    ])
@endsection
