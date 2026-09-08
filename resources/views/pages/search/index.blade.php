{{--
    Search results. WS-04, a contracted gap with no design screen.

    Built from the foundations rather than a new pattern: the publications
    library's facet chips, and screen 07's document row for each result.
--}}
@extends('layouts.public')

@section('title', $query !== '' ? __('search.results_for', ['query' => $query]) : __('search.title'))

@section('content')
    <div class="c-section">
        <div class="c-page">
            <h1>{{ __('search.title') }}</h1>

            <form method="GET" action="{{ route('search', ['locale' => app()->getLocale()]) }}" class="c-form c-search__form" role="search">
                <div class="c-field">
                    <label class="c-label" for="q">{{ __('search.label') }}</label>
                    <div class="c-search__field">
                        <input class="c-input" type="search" id="q" name="q" value="{{ $query }}"
                               autocomplete="off" autofocus
                               list="search-suggestions"
                               aria-describedby="search-hint"
                               data-search-input
                               data-suggest-url="{{ route('search.suggest', ['locale' => app()->getLocale()]) }}">
                        <button type="submit" class="c-btn c-btn--primary">{{ __('search.submit') }}</button>
                    </div>
                    <p class="c-field__hint" id="search-hint">{{ __('search.hint') }}</p>
                    {{-- Native suggestions: no dropdown to build, no keyboard
                         trap to get wrong, and it works without JavaScript. --}}
                    <datalist id="search-suggestions" data-suggestions></datalist>
                </div>
            </form>

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

                <p class="c-results" role="status">
                    {{ trans_choice('search.result_count', $results->total, ['count' => $results->total, 'query' => $query]) }}
                </p>

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
@endsection

@push('scripts')
    <script src="{{ asset('js/search.js') }}" defer></script>
@endpush
