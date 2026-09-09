{{-- The regulatory library. WS-05, screen 07. --}}
@extends('layouts.public')

@section('title', __('publications.title'))
@section('description', __('publications.intro'))

@section('content')
    <div class="c-section">
        <div class="c-page">
            <h1>{{ __('publications.title') }}</h1>
            <p class="c-lead">{{ __('publications.intro') }}</p>

            {{-- The filters answer without reloading the page, and they
                 are chip links, so the floor is a page that filters and
                 paginates with no script at all. See public/js/filters.js:
                 nothing there draws a control. --}}
            <div data-live-filters
                 data-live-swap="#library-filters,#library-results"
                 data-live-status="#library-count">
            <nav class="c-filters" id="library-filters" aria-label="{{ __('publications.filters') }}">
                @include('pages.publications._facet', [
                    'legend' => __('publications.type'), 'key' => 'type', 'active' => $filters['type'],
                    'options' => collect($types)->map(fn ($t) => ['value' => $t->value, 'label' => __($t->labelKey())]),
                ])
                @include('pages.publications._facet', [
                    'legend' => __('publications.country'), 'key' => 'country', 'active' => $filters['country'],
                    'options' => $countries->map(fn ($c) => ['value' => $c->iso_code, 'label' => $c->name]),
                ])
                @include('pages.publications._facet', [
                    'legend' => __('publications.committee'), 'key' => 'committee', 'active' => $filters['committee'],
                    'options' => $committees->map(fn ($c) => ['value' => $c->slug, 'label' => $c->name]),
                ])
                @include('pages.publications._facet', [
                    'legend' => __('publications.year'), 'key' => 'year', 'active' => $filters['year'],
                    'options' => $years->map(fn ($y) => ['value' => $y, 'label' => $y]),
                ])
            </nav>

            {{-- In the document before any exchange begins, so replacing
                 its text is announced. A results count rebuilt from
                 scratch is a results count a screen reader never hears. --}}
            <p class="c-results" id="library-count" role="status">{{ trans_choice('publications.result_count', $publications->total(), ['count' => $publications->total()]) }}</p>

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
@endsection

@push('scripts')
    <script src="{{ asset('js/filters.js') }}" defer></script>
@endpush
