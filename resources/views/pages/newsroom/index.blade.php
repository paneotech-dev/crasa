{{-- Newsroom listing. WS-06.

     The same filter sidebar the regulatory library uses, per screen 07 line
     115. No screen draws a second filter treatment for the newsroom, and
     two treatments for the same job on the same site is how one of them
     ends up with the counts and the other without. --}}
@extends('layouts.public')

@section('title', __('news.title'))
@section('description', __('news.intro'))

@section('content')
    <x-section-band
        :eyebrow="__('news.band_eyebrow')"
        :title="__('news.band_heading')"
        :lead="__('news.intro')"
        :counts="\App\Support\SectionCounts::newsroom()"
    />

    <div class="c-section">
        <div class="c-page">

            <div class="c-library"
                 data-live-filters
                 data-live-swap="#news-filters,#news-results"
                 data-live-status="#news-count">

                <div id="news-filters">
                    <x-facet-panel
                        :action="route('newsroom', ['locale' => app()->getLocale()])"
                        :clear="route('newsroom', ['locale' => app()->getLocale()])"
                        :label="__('news.filters')"
                        :filters="$filters"
                        :facets="array_values(array_filter([
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
                            $tags->isEmpty() ? null : [
                                'legend' => __('news.tag'),
                                'field' => 'tag',
                                'counts' => $counts['tag'],
                                'options' => $tags->map(fn ($tag) => ['value' => $tag->slug, 'label' => $tag->translated('name', $tag->slug)]),
                            ],
                        ]))"
                    />
                </div>

                <div class="c-library__main">
                    <p class="c-results" id="news-count" role="status">
                        {!! __('news.result_summary', [
                            'shown' => '<strong>'.number_format($items->total()).'</strong>',
                            'total' => number_format($total),
                        ]) !!}
                    </p>

                    <div id="news-results">
                        @if ($items->isEmpty())
                            @include('blocks._empty', [
                                'title' => __('news.empty_title'),
                                'body' => __('news.empty_body'),
                                'actionLabel' => __('news.clear_filters'),
                                'actionUrl' => route('newsroom', ['locale' => app()->getLocale()]),
                            ])
                        @else
                            <div class="b-cards">
                                @php $usedImageSlots = collect(); @endphp
                                @foreach ($items as $item)
                                    @include('pages.newsroom._card', ['item' => $item, 'usedImageSlots' => $usedImageSlots])
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
@endpush
