{{-- Newsroom listing. WS-06.

     The same filter sidebar the regulatory library uses, per screen 07 line
     115, with the newsroom's own keyword, year and order inside the same
     form so every state is a URL. Options with nothing behind them are not
     offered. The newest item leads page 1 as a wide card; the grid below is
     whole rows of three. --}}
@extends('layouts.public')

@section('title', __('news.title'))
@section('description', __('news.intro'))

@php
    $locale = app()->getLocale();
    $keep = fn (array $facet) => array_merge($facet, [
        'options' => collect($facet['options'])
            ->filter(fn ($option) => ($facet['counts'][(string) $option['value']] ?? 0) > 0
                || in_array((string) $option['value'], array_map('strval', $filters[$facet['field']] ?? []), true))
            ->values(),
    ]);
    $facets = collect([
        [
            'legend' => __('news.category'),
            'field' => 'category',
            'counts' => $counts['category'],
            'options' => $categories->map(fn ($term) => ['value' => $term->slug, 'label' => $term->label()]),
        ],
        [
            'legend' => __('news.sector'),
            'field' => 'sector',
            'counts' => $counts['sector'],
            'options' => $sectors->map(fn ($sector) => ['value' => $sector->slug, 'label' => $sector->name]),
        ],
        [
            'legend' => __('news.tag'),
            'field' => 'tag',
            'counts' => $counts['tag'],
            'options' => $tags->map(fn ($tag) => ['value' => $tag->slug, 'label' => $tag->translated('name', $tag->slug)]),
        ],
    ])->map($keep)->filter(fn ($facet) => $facet['options']->isNotEmpty())->values()->all();
@endphp

@section('content')
    @include('partials.cpages.styles')
    @php
        // Two counts beside the title; the third entry (the date of the
        // latest item) is already on the featured card below.
        $newsFigures = collect(\App\Support\SectionCounts::newsroom())
            ->map(fn ($c) => ['value' => $c['value'], 'label' => $c['label'], 'url' => $c['url'] ?? null])
            ->take(2)->values()->all();
    @endphp
    @include('partials.cpages.hero', [
        'eyebrow' => __('news.band_eyebrow'),
        'title' => __('news.band_heading'),
        'lead' => __('news.intro'),
        'figures' => $newsFigures,
    ])

    <section class="u-section cp-library cp-newsroom">
        <div class="u-container">
            <div class="c-library cp-library__grid"
                 data-live-filters
                 data-live-swap="#news-filters,#news-results"
                 data-live-status="#news-count">

                <details class="cp-filters n-filters" open data-cp-filters>
                    <summary class="u-btn u-btn--secondary cp-filters__toggle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                        {{ __('news.filter_button') }}
                    </summary>
                    <div id="news-filters">
                        <x-facet-panel
                            :action="route('newsroom', ['locale' => $locale])"
                            :clear="route('newsroom', ['locale' => $locale])"
                            :label="__('news.filters')"
                            :filters="$filters"
                            :facets="$facets"
                        >
                            <div class="n-controls cp-news-controls">
                                <div class="u-field">
                                    <label for="news-q">{{ __('news.search_label') }}</label>
                                    <input class="u-input" id="news-q" type="search" name="q" value="{{ $search }}" placeholder="{{ __('news.search_placeholder') }}">
                                </div>
                                @if ($years !== [])
                                    <div class="u-field">
                                        <label for="news-year">{{ __('news.year') }}</label>
                                        <select class="u-select" id="news-year" name="year">
                                            <option value="">{{ __('news.all_years') }}</option>
                                            @foreach ($years as $y => $n)
                                                <option value="{{ $y }}" @selected($year === $y)>{{ $y }} ({{ $n }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="u-field">
                                    <label for="news-sort">{{ __('news.sort') }}</label>
                                    <select class="u-select" id="news-sort" name="sort">
                                        <option value="newest" @selected($sort === 'newest')>{{ __('news.sort_newest') }}</option>
                                        <option value="oldest" @selected($sort === 'oldest')>{{ __('news.sort_oldest') }}</option>
                                    </select>
                                </div>
                            </div>
                        </x-facet-panel>
                    </div>
                </details>

                <div class="c-library__main cp-library__main">
                    <div id="news-results">
                        <p class="c-results cp-results" id="news-count" role="status" aria-live="polite" data-total="{{ $matching }}">
                            @if ($matching > 0)
                                {{ __('news.showing', [
                                    'from' => $featured ? 1 : $items->firstItem() + ($matching - $items->total()),
                                    'to' => $items->lastItem() !== null ? $items->lastItem() + ($matching - $items->total()) : $matching,
                                    'total' => $matching,
                                ]) }}
                            @endif
                        </p>

                        @if ($matching === 0)
                            @include('blocks._empty', [
                                'title' => __('news.empty_title'),
                                'body' => __('news.empty_body'),
                                'actionLabel' => __('news.clear_filters'),
                                'actionUrl' => route('newsroom', ['locale' => $locale]),
                            ])
                        @else
                            @if ($featured)
                                @include('pages.newsroom._ucard', ['item' => $featured, 'featured' => true])
                            @endif

                            <ul class="u-cols-3 cp-news-grid" role="list">
                                @foreach ($items as $item)
                                    <li>@include('pages.newsroom._ucard', ['item' => $item, 'featured' => false])</li>
                                @endforeach
                            </ul>

                            <div class="c-pagination">{{ $items->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Posts from CRASA's channels: the feed partial is supplied separately.
         The empty Facebook page box that stood here is gone (C13). --}}
    {{-- The footer links to #social here, so the section is always present:
         the feed partial carries id="social" itself; until it exists the
         channel cards stand in under the same id. --}}
    @if (view()->exists('partials.newsroom.social'))
        @include('partials.newsroom.social')
    @else
        <section class="u-section u-section--pale" id="social" aria-labelledby="news-social-heading">
            <div class="u-container">
                <h2 class="u-h2" id="news-social-heading">{{ __('social.heading') }}</h2>
                @include('partials.social-channels')
            </div>
        </section>
    @endif

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages_b.cta_news_title'),
        'ctaText' => __('cpages_b.cta_news_text'),
        'ctaPrimary' => [__('cpages_b.cta_events'), route('events', ['locale' => $locale])],
        'ctaSecondary' => [__('cpages_b.cta_library'), route('publications', ['locale' => $locale])],
    ])
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/filters.js') }}" defer></script>
    <script>
        // On a phone the filters start folded behind the "Filter" button.
        (function () {
            var panel = document.querySelector('.n-filters');
            if (panel && window.matchMedia('(max-width: 991.98px)').matches) panel.removeAttribute('open');
        })();
    </script>
@endpush
