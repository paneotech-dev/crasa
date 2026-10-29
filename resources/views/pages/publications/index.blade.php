{{-- The regulatory library. WS-05, screen 07. --}}
@extends('layouts.public')

@section('title', __('publications.title'))
@section('description', __('publications.intro'))

@section('content')
    @include('partials.cpages.styles')
    @php
        $loc = app()->getLocale();
        $heroFigures = collect(\App\Support\SectionCounts::publications())
            ->filter(fn ($c) => $c['value'] !== '0')
            ->map(fn ($c) => ['value' => $c['value'], 'label' => $c['label'], 'url' => $c['url'] ?? null])
            ->take(2)->values()->all();
        $sectorLinks = $sectors->filter(fn ($s) => ($counts['sector'][$s->slug] ?? 0) > 0);
    @endphp
    @php
        ob_start();
    @endphp
        @if ($sectorLinks->isNotEmpty())
            <nav class="u-panel cp-pub-browse" aria-labelledby="cp-pub-browse-title">
                <h2 class="u-h3" id="cp-pub-browse-title">{{ __('cpages_b.browse_sector') }}</h2>
                <ul class="cp-pub-browse__list" role="list">
                    @foreach ($sectorLinks as $sector)
                        <li>
                            <a class="cp-pub-browse__link" href="{{ route('publications', ['locale' => $loc, 'sector' => [$sector->slug]]) }}">
                                <span>{{ $sector->name }}</span>
                                <span class="cp-pub-browse__count">{{ trans_choice('cpages_b.documents_word', $counts['sector'][$sector->slug], ['count' => $counts['sector'][$sector->slug]]) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    @php
        $heroAside = trim(ob_get_clean());
    @endphp

    @include('partials.cpages.hero', [
        'eyebrow' => __('publications.band_eyebrow'),
        'title' => __('publications.band_heading'),
        'lead' => __('publications.intro'),
        'figures' => $heroFigures,
        'heroAside' => $heroAside,
    ])

    <section class="u-section cp-library">
        <div class="u-container">
            <div class="c-library cp-library__grid"
                 data-live-filters
                 data-live-swap="#library-filters,#library-results"
                 data-live-status="#library-count">

                <details class="cp-filters" id="library-filters-panel" open data-cp-filters>
                    <summary class="u-btn u-btn--secondary cp-filters__toggle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                        {{ __('cpages_b.show_filters') }}
                    </summary>
                    <div id="library-filters">
                        <x-facet-panel
                            :action="route('publications', ['locale' => $loc])"
                            :clear="route('publications', ['locale' => $loc])"
                            :label="__('publications.filters')"
                            :filters="$filters"
                            :facets="[
                                [
                                    'legend' => __('publications.sector'),
                                    'field' => 'sector',
                                    'counts' => $counts['sector'],
                                    'options' => $sectors->map(fn ($s) => ['value' => $s->slug, 'label' => $s->name]),
                                ],
                                [
                                    'legend' => __('publications.type'),
                                    'field' => 'type',
                                    'counts' => $counts['type'],
                                    'options' => $types->map(fn ($t) => ['value' => $t->slug, 'label' => $t->label()]),
                                ],
                                [
                                    'legend' => __('publications.year'),
                                    'field' => 'year',
                                    'counts' => $counts['year'],
                                    'bars' => true,
                                    'options' => $years->map(fn ($y) => ['value' => $y, 'label' => $y]),
                                ],
                                [
                                    'legend' => __('publications.committee'),
                                    'field' => 'committee',
                                    'counts' => $counts['committee'],
                                    'options' => $committees->map(fn ($c) => ['value' => $c->slug, 'label' => $c->name]),
                                ],
                                [
                                    'legend' => __('publications.country'),
                                    'field' => 'country',
                                    'counts' => $counts['country'],
                                    'options' => $countries->map(fn ($c) => ['value' => $c->iso_code, 'label' => $c->name]),
                                ],
                                [
                                    'legend' => __('publications.language'),
                                    'field' => 'language',
                                    'counts' => $counts['language'],
                                    'options' => collect($languages)->map(fn ($code) => ['value' => $code, 'label' => \App\Support\Locales::nameOf($code)]),
                                ],
                            ]"
                        />
                    </div>
                </details>

                <div class="c-library__main cp-library__main">
                    <p class="c-results cp-results" id="library-count" role="status" aria-live="polite">
                        {!! __('publications.result_summary', [
                            'shown' => '<strong>'.number_format($publications->total()).'</strong>',
                            'total' => number_format($total),
                        ]) !!}
                    </p>

                    <div id="library-results">
                        @if ($publications->isEmpty())
                            @include('blocks._empty', [
                                'title' => __('publications.empty_title'),
                                'body' => __('publications.empty_body'),
                                'actionLabel' => __('publications.clear_filters'),
                                'actionUrl' => route('publications', ['locale' => $loc]),
                            ])
                        @else
                            <ul class="u-cols-3 cp-doc-grid" role="list">
                                @foreach ($publications as $publication)
                                    <li>@include('pages.publications._row', ['publication' => $publication])</li>
                                @endforeach
                            </ul>

                            <div class="c-pagination">{{ $publications->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages_b.cta_library_title'),
        'ctaText' => __('cpages_b.cta_library_text'),
        'ctaPrimary' => [__('cpages_b.cta_comparator'), route('comparator', ['locale' => $loc])],
        'ctaSecondary' => [__('cpages_b.cta_contact'), route('contact', ['locale' => $loc])],
    ])
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/filters.js') }}" defer></script>
    <script>
        // The filter panel starts closed on phones and tablets; it stays a
        // details element, so it opens with no script at all.
        (function () { var d = document.querySelector('[data-cp-filters]'); if (d && window.matchMedia('(max-width: 991.98px)').matches) d.removeAttribute('open'); })();
    </script>
@endpush
