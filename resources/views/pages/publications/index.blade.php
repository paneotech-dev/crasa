{{-- The regulatory library. WS-05, screen 07. --}}
@extends('layouts.public')

@section('title', __('publications.title'))
@section('description', __('publications.intro'))

@section('content')
    {{-- No photograph. The library has five images and none of them is a
         regulatory library: a test bench, a courier, a parcel handover and a
         team at a laptop. A picture that is not about the section it opens
         is a lie about what the reader is looking at. --}}
    <x-section-band
        :eyebrow="__('publications.band_eyebrow')"
        :title="__('publications.band_heading')"
        :lead="__('publications.intro')"
        :counts="\App\Support\SectionCounts::publications()"
    />

    <div class="c-section">
        <div class="c-page">

            {{-- Screen 07 line 113: display:flex; flex-wrap:wrap;
                 gap:var(--s-6); align-items:flex-start. The sidebar is
                 flex:1 1 270px and the results are flex:999 1 360px, so at
                 one column the sidebar comes first, which is where a filter
                 belongs on a phone. --}}
            <div class="c-library"
                 data-live-filters
                 data-live-swap="#library-filters,#library-results"
                 data-live-status="#library-count">

                <div id="library-filters">
                    <x-facet-panel
                        :action="route('publications', ['locale' => app()->getLocale()])"
                        :clear="route('publications', ['locale' => app()->getLocale()])"
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
                                'options' => $types->map(fn ($t) => ['value' => $t->value, 'label' => __($t->labelKey())]),
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

                <div class="c-library__main">
                    {{-- Screen 07 line 206: "12 of 35 documents match your
                         filters". In the document before any exchange
                         begins, so replacing its text is announced. A count
                         rebuilt from scratch is a count a screen reader
                         never hears. --}}
                    <p class="c-results" id="library-count" role="status">
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
                                'actionUrl' => route('publications', ['locale' => app()->getLocale()]),
                            ])
                        @else
                            <div class="b-rows">
                                @foreach ($publications as $publication)
                                    @include('pages.publications._row', ['publication' => $publication])
                                @endforeach
                            </div>

                            <div class="c-pagination">{{ $publications->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/filters.js') }}" defer></script>
@endpush
