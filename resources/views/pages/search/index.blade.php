{{--
    Search results (UI system, 29 October 2026). Short hero with the form
    and the corpus counts, results as cards grouped by kind with the query
    marked, filters in a side panel, and a call-to-action band.

    The floor is still the input and the button: with no script the form
    submits. public/js/search.js turns the input into a combobox and
    public/js/filters.js swaps #search-body while #search-count, outside
    the swapped region, announces the new count.
--}}
@extends('layouts.public')

@section('title', $query !== '' ? __('search.results_for', ['query' => $query]) : __('search.title'))

@include('partials.cpages.styles')

@php
    $locale = app()->getLocale();
    // Terms to mark in titles. The title is escaped first, then the marks go
    // in, so a stored title cannot inject markup.
    $terms = collect(preg_split('/\s+/u', trim($query)))->filter(fn ($t) => mb_strlen($t) >= 2)->map(fn ($t) => preg_quote(e($t), '/'))->all();
    $mark = function (string $text) use ($terms): string {
        $safe = e($text);
        return $terms === [] ? $safe : preg_replace('/('.implode('|', $terms).')/iu', '<mark>$1</mark>', $safe);
    };
    $groups = $query !== '' && ! $results->isEmpty() ? collect($results->hits)->groupBy('type') : collect();
    $order = ['instrument', 'publication', 'news', 'member'];
    $groups = $groups->sortBy(fn ($hits, $type) => ($i = array_search($type, $order, true)) === false ? 99 : $i);
    $icons = ['instrument' => 'file', 'publication' => 'book', 'news' => 'news', 'member' => 'globe'];
@endphp

@section('content')
    @php
        ob_start();
    @endphp
    <form method="GET" action="{{ route('search', ['locale' => $locale]) }}" class="cp-search__form" role="search">
        <label class="u-label" for="q">{{ __('search.label') }}</label>
        <div class="cp-search__field c-suggest">
            <input class="u-input" type="search" id="q" name="q" value="{{ $query }}"
                   autocomplete="off" @if ($query === '') autofocus @endif
                   aria-describedby="search-hint"
                   data-search-input
                   data-suggest-url="{{ route('search.suggest', ['locale' => $locale]) }}">
            <button type="submit" class="u-btn u-btn--primary">
                <svg class="cp-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">@include('partials.cpages.icon', ['name' => 'search'])</svg>
                {{ __('search.submit') }}
            </button>
            <ul class="c-suggest__list" id="search-suggestions" role="listbox"
                aria-label="{{ __('search.suggest_list') }}" data-suggestions hidden></ul>
        </div>
        <p class="u-help" id="search-hint">{{ __('search.hint') }}</p>
        <p class="c-sr" role="status" data-suggest-status></p>
    </form>
    @php
        $form = ob_get_clean();
    @endphp

    @include('partials.cpages.hero', [
        'eyebrow' => __('search.band_eyebrow'),
        'title' => $query !== '' ? __('cpages_c.search_title_query', ['query' => $query]) : __('search.band_heading'),
        'lead' => __('search.band_lead'),
        'figures' => array_slice(\App\Support\SectionCounts::search(), 0, 3),
        'heroAside' => $form,
    ])

    <section class="u-section cp-search">
        <div class="u-container"
             data-live-filters
             data-live-swap="#search-body"
             data-live-status="#search-count">

            <p class="cp-search__count" id="search-count" role="status" aria-live="polite">{{ $query === '' ? '' : trans_choice('search.result_count', $results->total, ['count' => $results->total, 'query' => $query]) }}</p>

            <div id="search-body">
            @if ($query === '' || $results->isEmpty())
                <div class="cp-search__empty">
                    <div class="u-panel u-panel--tint">
                        <h2 class="u-h3">{{ $query === '' ? __('search.empty_title') : __('search.none_title', ['query' => $query]) }}</h2>
                        <p class="u-text">{{ $query === '' ? __('search.empty_body') : __('search.none_body') }}</p>
                        @if ($query !== '')
                            <a class="u-btn u-btn--secondary" href="{{ route('search', ['locale' => $locale]) }}">{{ __('search.clear') }}</a>
                        @endif
                    </div>
                    <div>
                        <h2 class="u-h3">{{ __('cpages_c.browse_heading') }}</h2>
                        <ul class="u-cols-2 cp-linkcards" role="list">
                            @foreach ([
                                ['publications', __('nav.publications'), __('cpages_c.browse_publications'), 'publication'],
                                ['newsroom', __('nav.newsroom'), __('cpages_c.browse_newsroom'), 'news'],
                                ['members', __('nav.members'), __('cpages_c.browse_members'), 'member'],
                                ['comparator', __('nav.comparator'), __('cpages_c.browse_comparator'), 'instrument'],
                            ] as [$route, $label, $line, $icon])
                                <li class="u-card cp-linkcard">
                                    <div class="u-card__body">
                                        <span class="cp-icon-badge"><svg class="cp-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">@include('partials.cpages.icon', ['name' => $icons[$icon]])</svg></span>
                                        <h3 class="u-card__title"><a href="{{ route($route, ['locale' => $locale]) }}">{{ $label }}</a></h3>
                                        <p class="u-card__text">{{ $line }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @else
                <div class="u-main-side cp-search__layout">
                    <div class="cp-search__results">
                        @foreach ($groups as $type => $hits)
                            <section class="cp-search__group" aria-labelledby="group-{{ $type }}">
                                <h2 class="cp-search__group-title" id="group-{{ $type }}">
                                    <span class="cp-icon-badge"><svg class="cp-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">@include('partials.cpages.icon', ['name' => $icons[$type] ?? 'book'])</svg></span>
                                    {{ __('cpages_c.group_'.$type) }}
                                    <span class="u-pill u-pill--muted">{{ count($hits) }}</span>
                                </h2>
                                <ul class="u-cols-2 cp-search__cards" role="list">
                                    @foreach ($hits as $hit)
                                        <li class="u-card cp-result">
                                            <div class="u-card__body">
                                                <span class="u-card__label">{{ __('search.type_'.$hit->type) }}</span>
                                                <h3 class="u-card__title"><a href="{{ $hit->url }}">{!! $mark($hit->title) !!}</a></h3>
                                                @if ($hit->snippet !== '')
                                                    {{-- Escaped in the driver before its marks go in. --}}
                                                    <p class="u-card__text cp-result__snippet">{!! $hit->snippet !!}</p>
                                                @endif
                                                @php $meta = array_filter([$hit->meta, $hit->country, $hit->sector, $hit->year]); @endphp
                                                @if ($meta !== [])
                                                    <div class="u-card__meta">
                                                        @foreach ($meta as $m)<span>{{ $m }}</span>@endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endforeach

                        @if ($results->lastPage() > 1)
                            <nav class="cp-pager" aria-label="{{ __('search.pages') }}">
                                @if ($results->page > 1)
                                    <a class="u-btn u-btn--secondary" href="{{ request()->fullUrlWithQuery(['page' => $results->page - 1]) }}">{{ __('search.previous') }}</a>
                                @endif
                                <span>{{ __('search.page_of', ['page' => $results->page, 'total' => $results->lastPage()]) }}</span>
                                @if ($results->page < $results->lastPage())
                                    <a class="u-btn u-btn--secondary" href="{{ request()->fullUrlWithQuery(['page' => $results->page + 1]) }}">{{ __('search.next') }}</a>
                                @endif
                            </nav>
                        @endif
                    </div>

                    @if ($results->facets !== [])
                        <aside class="u-panel cp-search__facets" aria-labelledby="search-filters-title">
                            <h2 class="u-h3" id="search-filters-title">{{ __('search.filters') }}</h2>
                            <nav class="c-filters" aria-label="{{ __('search.filters') }}">
                                @foreach (['type', 'country', 'sector', 'year'] as $facet)
                                    @continue(empty($results->facets[$facet]))
                                    <x-facet
                                        :legend="__('search.facet_'.$facet)"
                                        :field="$facet"
                                        :active="$filters[$facet]"
                                        :counts="$results->facets[$facet]"
                                        :options="collect($results->facets[$facet])->map(fn ($count, $value) => [
                                            'value' => $value,
                                            'label' => $facet === 'type' ? __('search.type_'.$value) : $value,
                                        ])->values()"
                                    />
                                @endforeach
                            </nav>
                        </aside>
                    @endif
                </div>
            @endif
            </div>
        </div>
    </section>

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages_c.search_cta_title'),
        'ctaText' => __('cpages_c.search_cta_text'),
        'ctaPrimary' => [__('cpages_c.search_cta_primary'), route('publications', ['locale' => $locale])],
        'ctaSecondary' => [__('cpages_c.search_cta_secondary'), route('contact', ['locale' => $locale])],
    ])
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/filters.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/search.js') }}" defer></script>
@endpush
