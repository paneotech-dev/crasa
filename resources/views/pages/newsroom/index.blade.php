{{-- Newsroom listing. WS-06. Built from the foundations components. --}}
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

            <div data-live-filters
                 data-live-swap="#news-filters,#news-results"
                 data-live-status="#news-count">
            {{-- The same facet partial the library uses. Three
                 hand-written copies of the same chip list is how one of
                 them ends up with the count and the other two without. --}}
            <nav class="c-filters" id="news-filters" aria-label="{{ __('news.filters') }}">
                <x-facet
                    :legend="__('news.category')"
                    field="category"
                    :active="$activeCategory"
                    :counts="$counts['category']"
                    :options="collect($categories)->map(fn ($case) => ['value' => $case->value, 'label' => __($case->labelKey())])"
                />
                <x-facet
                    :legend="__('news.sector')"
                    field="sector"
                    :active="$activeSector"
                    :counts="$counts['sector']"
                    :options="$sectors->map(fn ($sector) => ['value' => $sector->slug, 'label' => $sector->name])"
                />
                @if ($tags->isNotEmpty())
                    <x-facet
                        :legend="__('news.tag')"
                        field="tag"
                        :active="$activeTag"
                        :counts="$counts['tag']"
                        :options="$tags->map(fn ($tag) => ['value' => $tag->slug, 'label' => $tag->translated('name', $tag->slug)])"
                    />
                @endif
            </nav>

            <p class="c-results" id="news-count" role="status">{{ trans_choice('news.result_count', $items->total(), ['count' => $items->total()]) }}</p>

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
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/filters.js') }}" defer></script>
@endpush
