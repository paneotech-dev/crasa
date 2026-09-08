{{-- Newsroom listing. WS-06. Built from the foundations components. --}}
@extends('layouts.public')

@section('title', __('news.title'))
@section('description', __('news.intro'))

@section('content')
    <div class="c-section">
        <div class="c-page">
            <h1>{{ __('news.title') }}</h1>
            <p class="c-lead">{{ __('news.intro') }}</p>

            <nav class="c-filters" aria-label="{{ __('news.filters') }}">
                <div class="c-filters__group">
                    <span class="c-filters__legend" id="filter-category">{{ __('news.category') }}</span>
                    <div class="c-filters__options" role="list" aria-labelledby="filter-category">
                        <a role="listitem" class="c-chip @if (! $activeCategory) is-active @endif"
                           @if (! $activeCategory) aria-current="true" @endif
                           href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}">{{ __('news.all') }}</a>
                        @foreach ($categories as $case)
                            <a role="listitem" class="c-chip @if ($activeCategory === $case->value) is-active @endif"
                               @if ($activeCategory === $case->value) aria-current="true" @endif
                               href="{{ request()->fullUrlWithQuery(['category' => $case->value, 'page' => null]) }}">{{ __($case->labelKey()) }}</a>
                        @endforeach
                    </div>
                </div>

                <div class="c-filters__group">
                    <span class="c-filters__legend" id="filter-sector">{{ __('news.sector') }}</span>
                    <div class="c-filters__options" role="list" aria-labelledby="filter-sector">
                        <a role="listitem" class="c-chip @if (! $activeSector) is-active @endif"
                           href="{{ request()->fullUrlWithQuery(['sector' => null, 'page' => null]) }}">{{ __('news.all') }}</a>
                        @foreach ($sectors as $sector)
                            <a role="listitem" class="c-chip @if ($activeSector === $sector->slug) is-active @endif"
                               @if ($activeSector === $sector->slug) aria-current="true" @endif
                               href="{{ request()->fullUrlWithQuery(['sector' => $sector->slug, 'page' => null]) }}">{{ $sector->name }}</a>
                        @endforeach
                    </div>
                </div>

                @if ($tags->isNotEmpty())
                    <div class="c-filters__group">
                        <span class="c-filters__legend" id="filter-tag">{{ __('news.tag') }}</span>
                        <div class="c-filters__options" role="list" aria-labelledby="filter-tag">
                            <a role="listitem" class="c-chip @if (! $activeTag) is-active @endif"
                               href="{{ request()->fullUrlWithQuery(['tag' => null, 'page' => null]) }}">{{ __('news.all') }}</a>
                            @foreach ($tags as $tagOption)
                                <a role="listitem" class="c-chip @if ($activeTag === $tagOption->slug) is-active @endif"
                                   @if ($activeTag === $tagOption->slug) aria-current="true" @endif
                                   href="{{ request()->fullUrlWithQuery(['tag' => $tagOption->slug, 'page' => null]) }}">{{ $tagOption->translated('name', $tagOption->slug) }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </nav>

            <p class="c-results" role="status">{{ trans_choice('news.result_count', $items->total(), ['count' => $items->total()]) }}</p>

            @if ($items->isEmpty())
                @include('blocks._empty', [
                    'title' => __('news.empty_title'),
                    'body' => __('news.empty_body'),
                    'actionLabel' => __('news.clear_filters'),
                    'actionUrl' => route('newsroom', ['locale' => app()->getLocale()]),
                ])
            @else
                <div class="b-cards">
                    @foreach ($items as $item)
                        @include('pages.newsroom._card', ['item' => $item])
                    @endforeach
                </div>

                <div class="c-pagination">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
@endsection
