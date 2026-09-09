{{--
    Search results. WS-04, a contracted gap with no design screen.

    Built from the foundations rather than a new pattern: the publications
    library's facet chips, and screen 07's document row for each result.
--}}
@extends('layouts.public')

@section('title', $query !== '' ? __('search.results_for', ['query' => $query]) : __('search.title'))

@section('content')
    {{-- The corpus, stated before a reader types anything. It is the
         honest answer to "is it worth searching here". --}}
    <x-section-band
        :eyebrow="__('search.band_eyebrow')"
        :title="__('search.band_heading')"
        :lead="__('search.band_lead')"
        :counts="\App\Support\SectionCounts::search()"
    />

    <div class="c-section">
        <div class="c-page">

            <form method="GET" action="{{ route('search', ['locale' => app()->getLocale()]) }}" class="c-form c-search__form" role="search">
                <div class="c-field">
                    <label class="c-label" for="q">{{ __('search.label') }}</label>

                    {{-- WS-04 predictive search.

                         The floor is this input and this button. With no
                         script the field takes a query and the form submits
                         it; nothing below is required to search.

                         With a script it becomes an ARIA combobox: the
                         listbox is built by public/js/search.js, the input
                         gains aria-expanded and aria-activedescendant, and
                         the count is announced. The suggestions themselves
                         are scoped server-side to what this reader could
                         open — a member state representative must not learn
                         another country's restricted document titles by
                         typing three letters. --}}
                    <div class="c-search__field c-suggest">
                        <input class="c-input" type="search" id="q" name="q" value="{{ $query }}"
                               autocomplete="off" autofocus
                               aria-describedby="search-hint"
                               data-search-input
                               data-suggest-url="{{ route('search.suggest', ['locale' => app()->getLocale()]) }}">
                        <button type="submit" class="c-btn c-btn--primary">{{ __('search.submit') }}</button>
                        <ul class="c-suggest__list" id="search-suggestions" role="listbox"
                            aria-label="{{ __('search.suggest_list') }}" data-suggestions hidden></ul>
                    </div>

                    <p class="c-field__hint" id="search-hint">{{ __('search.hint') }}</p>

                    {{-- How many suggestions arrived, for a reader who
                         cannot see them appear. Polite, so it waits for a
                         pause in typing rather than interrupting it. --}}
                    <p class="c-sr" role="status" data-suggest-status></p>
                </div>
            </form>

            <div data-live-filters
                 data-live-swap="#search-body"
                 data-live-status="#search-count">

            {{-- Outside the swapped region on purpose. This element has to
                 survive the exchange for its text change to be announced;
                 a status region destroyed and rebuilt announces nothing,
                 which is how an "instant" filter quietly locks a screen
                 reader out of its own results. --}}
            <p class="c-results" id="search-count" role="status">{{ $query === '' ? '' : trans_choice('search.result_count', $results->total, ['count' => $results->total, 'query' => $query]) }}</p>

            <div id="search-body">
            @if ($query === '')
                {{-- Empty query. Not an error, and not a blank page. --}}
                @include('blocks._empty', [
                    'title' => __('search.empty_title'),
                    'body' => __('search.empty_body'),
                    'actionLabel' => __('nav.publications'),
                    'actionUrl' => route('publications', ['locale' => app()->getLocale()]),
                ])
            @elseif ($results->isEmpty())
                @include('blocks._empty', [
                    'title' => __('search.none_title', ['query' => $query]),
                    'body' => __('search.none_body'),
                    'actionLabel' => __('search.clear'),
                    'actionUrl' => route('search', ['locale' => app()->getLocale()]),
                ])
            @else
                @if ($results->facets !== [])
                    <nav class="c-filters" aria-label="{{ __('search.filters') }}">
                        @foreach (['type', 'country', 'sector', 'year'] as $facet)
                            @continue(empty($results->facets[$facet]))
                            <div class="c-filters__group">
                                <span class="c-filters__legend" id="facet-{{ $facet }}">{{ __('search.facet_'.$facet) }}</span>
                                <div class="c-filters__options" role="list" aria-labelledby="facet-{{ $facet }}">
                                    <a role="listitem" class="c-chip @if (! $filters[$facet]) is-active @endif"
                                       href="{{ request()->fullUrlWithQuery([$facet => null, 'page' => null]) }}">{{ __('search.all') }}</a>
                                    @foreach ($results->facets[$facet] as $value => $count)
                                        <a role="listitem" class="c-chip @if ((string) $filters[$facet] === (string) $value) is-active @endif"
                                           @if ((string) $filters[$facet] === (string) $value) aria-current="true" @endif
                                           href="{{ request()->fullUrlWithQuery([$facet => $value, 'page' => null]) }}">
                                            {{ $facet === 'type' ? __('search.type_'.$value) : $value }}
                                            <span class="c-chip__count">{{ $count }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </nav>
                @endif



                <div class="b-rows">
                    @foreach ($results->hits as $hit)
                        <div class="b-row">
                            <div class="b-row__thumb">
                                <span class="c-filetype" aria-hidden="true">{{ __('search.type_'.$hit->type) }}</span>
                            </div>
                            <div class="b-row__main">
                                <div class="b-row__title">
                                    <a href="{{ $hit->url }}">{{ $hit->title }}</a>
                                </div>
                                @if ($hit->snippet !== '')
                                    {{-- The snippet is escaped in the driver
                                         before the marks go in, so a title
                                         cannot inject markup here. --}}
                                    <p class="b-row__summary">{!! $hit->snippet !!}</p>
                                @endif
                                <div class="b-row__meta">
                                    <span>{{ __('search.type_'.$hit->type) }}</span>
                                    @if ($hit->meta)<span>{{ $hit->meta }}</span>@endif
                                    @if ($hit->country)<span>{{ $hit->country }}</span>@endif
                                    @if ($hit->sector)<span>{{ $hit->sector }}</span>@endif
                                    @if ($hit->year)<span>{{ $hit->year }}</span>@endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($results->lastPage() > 1)
                    <nav class="c-pagination" aria-label="{{ __('search.pages') }}">
                        @if ($results->page > 1)
                            <a href="{{ request()->fullUrlWithQuery(['page' => $results->page - 1]) }}">{{ __('search.previous') }}</a>
                        @endif
                        <span>{{ __('search.page_of', ['page' => $results->page, 'total' => $results->lastPage()]) }}</span>
                        @if ($results->page < $results->lastPage())
                            <a href="{{ request()->fullUrlWithQuery(['page' => $results->page + 1]) }}">{{ __('search.next') }}</a>
                        @endif
                    </nav>
                @endif
            @endif
            </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/filters.js') }}" defer></script>
    <script src="{{ asset('js/search.js') }}" defer></script>
@endpush
