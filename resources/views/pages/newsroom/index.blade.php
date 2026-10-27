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
            'options' => $categories->map(fn ($case) => ['value' => $case->value, 'label' => __($case->labelKey())]),
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
    <div class="n-band">
        <x-section-band
            :eyebrow="__('news.band_eyebrow')"
            :title="__('news.band_heading')"
            :lead="__('news.intro')"
            :counts="\App\Support\SectionCounts::newsroom()"
        />
    </div>

    <div class="c-section n-section">
        <div class="c-page">

            <div class="c-library n-library"
                 data-live-filters
                 data-live-swap="#news-filters,#news-results"
                 data-live-status="#news-count">

                <details class="n-filters" open>
                    <summary class="n-filters__toggle c-btn c-btn--secondary">{{ __('news.filter_button') }}</summary>
                    <div id="news-filters">
                        <x-facet-panel
                            :action="route('newsroom', ['locale' => $locale])"
                            :clear="route('newsroom', ['locale' => $locale])"
                            :label="__('news.filters')"
                            :filters="$filters"
                            :facets="$facets"
                        >
                            <div class="n-controls">
                                <label class="n-controls__field" for="news-q">
                                    <span>{{ __('news.search_label') }}</span>
                                    <input class="c-input" id="news-q" type="search" name="q" value="{{ $search }}" placeholder="{{ __('news.search_placeholder') }}">
                                </label>
                                @if ($years !== [])
                                    <label class="n-controls__field" for="news-year">
                                        <span>{{ __('news.year') }}</span>
                                        <select class="c-input" id="news-year" name="year">
                                            <option value="">{{ __('news.all_years') }}</option>
                                            @foreach ($years as $y => $n)
                                                <option value="{{ $y }}" @selected($year === $y)>{{ $y }} ({{ $n }})</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                                <label class="n-controls__field" for="news-sort">
                                    <span>{{ __('news.sort') }}</span>
                                    <select class="c-input" id="news-sort" name="sort">
                                        <option value="newest" @selected($sort === 'newest')>{{ __('news.sort_newest') }}</option>
                                        <option value="oldest" @selected($sort === 'oldest')>{{ __('news.sort_oldest') }}</option>
                                    </select>
                                </label>
                            </div>
                        </x-facet-panel>
                    </div>
                </details>

                <div class="c-library__main">
                    <div id="news-results">
                        <p class="c-results" id="news-count" role="status" data-total="{{ $matching }}">
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
                                @include('pages.newsroom._card', ['item' => $featured, 'featured' => true])
                            @endif

                            <div class="n-grid">
                                @foreach ($items as $item)
                                    @include('pages.newsroom._card', ['item' => $item, 'featured' => false])
                                @endforeach
                            </div>

                            <div class="c-pagination">{{ $items->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/filters.js') }}" defer></script>
    <script>
        // On a phone the filters start folded behind the "Filter" button.
        (function () {
            var panel = document.querySelector('.n-filters');
            if (panel && window.matchMedia('(max-width: 760px)').matches) panel.removeAttribute('open');
        })();
    </script>
@endpush
