{{--
    Members (C14, 29 October 2026). The hero carries the heading and the
    paragraph once; the map takes the full width with its controls above it
    (the map component itself, with its legend, exports and island cards,
    is the region-map component's); the directory follows as cards with the
    regulator's mark in a fixed 48px frame.

    The member grid block's own settings still decide: a grid without the
    directory setting is rendered as the block draws it.
--}}
@extends('layouts.public')

@php
    $title = $page->translate('title');
    $description = $page->translate('meta_description');
    $locale = app()->getLocale();
    $grid = $page->visibleBlocks->first(fn ($b) => $b->type() === \App\Enums\BlockType::MemberGrid);
    $directory = $grid && (bool) $grid->setting('directory', false);
    $countries = \App\Models\Country::with(['authority.sectors', 'committees'])->orderBy('name')->get();
    $seatsKnown = \App\Models\Committee::seatsRecorded();
    $counts = \App\Support\SectionCounts::forPage('members');
@endphp

@section('title', $title?->value ?? __('nav.members'))
@if ($description)
    @section('description', $description->value)
@endif

@include('partials.cpages.styles')

@section('content')
    @include('partials.cpages.hero', [
        'eyebrow' => $title?->value,
        'title' => $page->translate('heading')?->value ?: ($title?->value ?? ''),
        'lead' => $page->translate('standfirst')?->value,
        'figures' => $counts,
    ])

    @foreach ($page->visibleBlocks as $block)
        @if ($block->is($grid) && $directory && $countries->isNotEmpty())
            @if ($grid->setting('show_map'))
                <section class="u-section cp-members-map" aria-labelledby="members-map-title">
                    <div class="u-container">
                        <div class="cp-section-head">
                            <h2 class="u-h2" id="members-map-title">{{ __('members.region_heading') }}</h2>
                        </div>
                        @php $mapView = \App\Support\MapView::resolve($countries, request()->query('indicator')); @endphp
                        <div class="cp-members-map__frame" data-live-filters data-live-swap="#members-map" data-live-status="#members-map-status">
                            <p class="c-sr" id="members-map-status" role="status">{{ $mapView->summary() }}</p>
                            <div id="members-map">
                                <x-region-map :countries="$countries" :view="$mapView" />
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            <section class="u-section u-section--blue cp-members-dir" id="directory" aria-labelledby="members-dir-title">
                <div class="u-container">
                    <div class="cp-section-head">
                        <h2 class="u-h2" id="members-dir-title">{{ __('members.directory_heading') }}</h2>
                    </div>
                    <ul class="u-cols-3 cp-members-grid" role="list">
                        @foreach ($countries as $country)
                            @php $authority = $country->authority; @endphp
                            <li class="u-card cp-member" id="member-{{ $country->iso_code }}">
                                <div class="u-card__body">
                                    <div class="cp-member__top">
                                        <span class="cp-member__mark">
                                            @if ($authority?->logo_path)
                                                <img class="cp-member__logo" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($authority->logo_path) }}" alt="{{ $authority->name }}" loading="lazy" decoding="async">
                                            @else
                                                <x-flag :iso="$country->iso_code" />
                                            @endif
                                        </span>
                                        <span class="cp-member__country">
                                            <x-flag :iso="$country->iso_code" />
                                            {{ $country->name }}
                                        </span>
                                    </div>
                                    <h3 class="u-card__title">
                                        <a href="{{ route('members.show', ['locale' => $locale, 'iso' => $country->iso_code]) }}">{{ $authority?->acronym ?: $country->name }}</a>
                                    </h3>
                                    @if ($authority)
                                        <p class="u-card__text">{{ $authority->name }}</p>
                                    @endif
                                    <div class="u-card__meta">
                                        @if ($authority?->sectors->isNotEmpty())
                                            <span>{{ $authority->sectors->pluck('name')->implode(', ') }}</span>
                                        @endif
                                        @if ($seatsKnown)
                                            <span>{{ trans_choice('blocks.committee_count', $country->committees->count(), ['count' => $country->committees->count()]) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @else
            <x-block :block="$block" ground="white" />
        @endif
    @endforeach

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages.members_cta_title'),
        'ctaText' => __('cpages.members_cta_text'),
        'ctaPrimary' => [__('cpages.members_cta_compare'), route('comparator', ['locale' => $locale])],
        'ctaSecondary' => [__('nav.committees'), route('committees', ['locale' => $locale])],
    ])
@endsection
