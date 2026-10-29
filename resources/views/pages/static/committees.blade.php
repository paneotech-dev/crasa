{{--
    The committees (C17, UI system, 29 October 2026). The page record still
    carries the hero text and the committee grid block, so editors change
    the words and the grid settings from the back office; this template
    lays them out as a short hero, six equal cards and a closing band.
--}}
@extends('layouts.public')
@include('partials.cpages.styles')

@php
    $locale = app()->getLocale();
    $title = $page->translate('title');
    $description = $page->translate('meta_description');
    $heading = $page->translate('heading')?->value ?: ($title?->value ?? __('nav.committees'));
    $standfirst = $page->translate('standfirst')?->value;
    $seatsKnown = \App\Models\Committee::seatsRecorded();
    $figures = collect(\App\Support\SectionCounts::committees())
        ->filter(fn ($f, $i) => $i === 0 || (int) preg_replace('/\D/', '', (string) $f['value']) > 0)
        ->reject(fn ($f, $i) => $i === 1 && ! $seatsKnown)
        ->take(2)->values();
    $gridBlock = $page->visibleBlocks->first(fn ($b) => $b->type() === \App\Enums\BlockType::CommitteeGrid);
    $gridData = $gridBlock ? \App\Support\BlockData::for($gridBlock) : ['committees' => collect()];
    $committees = $gridData['committees'] ?? collect();
    $otherBlocks = $page->visibleBlocks->reject(fn ($b) => $b === $gridBlock);
    $introBlock = $otherBlocks->first(fn ($b) => $b->type() === \App\Enums\BlockType::RichText && ! $b->isUnwritten());
    $backOffice = auth()->user()?->can('enter-back-office') ?? false;
@endphp

@section('title', $title?->value ?? __('nav.committees'))
@if ($description)
    @section('description', $description->value)
@endif

@section('content')
<section class="u-hero cp-hero">
    <div class="u-container">
        <div class="u-hero__grid">
            <div>
                <p class="u-eyebrow">{{ __('cpages_a.committees_eyebrow') }}</p>
                <h1 class="u-h1">{{ $heading }}</h1>
                @if ($standfirst)<p class="u-lead">{{ $standfirst }}</p>@endif
                @if ($figures->isNotEmpty())
                    <dl class="u-figures">
                        @foreach ($figures as $figure)
                            <div class="u-figure"><dd>{{ $figure['value'] }}</dd><dt>{{ $figure['label'] }}</dt></div>
                        @endforeach
                    </dl>
                @endif
            </div>
            @if (\App\Support\ImageLibrary::has('institutional'))
                <figure class="u-hero__photo">
                    <x-responsive-image name="institutional" decorative sizes="(min-width: 992px) 480px, 100vw" loading="eager" />
                </figure>
            @endif
        </div>
    </div>
</section>

<section class="u-section" id="{{ $gridBlock?->anchor ?: 'committees' }}" data-ground="white">
    <div class="u-container">
        <div class="cp-com-head">
            <div>
                @if ($gridBlock?->translate('heading'))<h2 class="u-h2">{{ $gridBlock->translate('heading')->value }}</h2>@endif
                @if ($gridBlock?->translate('intro'))<p class="u-text u-muted cp-com-intro">{{ $gridBlock->translate('intro')->value }}</p>@endif
            </div>
            @if ($introBlock?->translate('body'))
                {{-- The page's own introduction, beside the grid heading
                     rather than a narrow column of its own. --}}
                <div class="u-panel u-panel--tint cp-prose" @if ($introBlock->anchor) id="{{ $introBlock->anchor }}" @endif>
                    {!! app(\App\Services\ContentRenderer::class)->render(\App\Support\PublishedText::withoutDraftingNotes($introBlock->translate('body')->value)['text'], 'block:'.$introBlock->id) !!}
                </div>
            @endif
        </div>

        @if ($committees->isEmpty())
            @include('blocks._empty', [
                'editorOnly' => true,
                'title' => __('blocks.committees_empty_title'),
                'body' => __('blocks.committees_empty_body'),
            ])
        @else
            <ul class="u-cols-3 cp-com-grid" role="list">
                @foreach ($committees as $committee)
                    @php
                        $seats = (int) ($committee->countries_count ?? 0);
                        $documents = (int) ($committee->publications_count ?? 0);
                        $meetings = (int) ($committee->meetings_count ?? 0);
                        $dormant = $meetings === 0 && $documents === 0;
                        $sectorName = $committee->sector?->name ?: __('blocks.committee_cross_sector');
                    @endphp
                    <li class="u-card cp-com-card" id="committee-{{ $committee->slug }}">
                        <div class="u-card__body">
                            <div class="cp-com-card__top">
                                <span class="u-card__label">{{ $sectorName }}</span>
                            </div>
                            <h3 class="u-card__title"><a href="{{ route('committees.show', ['locale' => $locale, 'committee' => $committee->slug]) }}">{{ $committee->name }}</a></h3>
                            @if ($committee->translate('mandate'))
                                <p class="u-card__text">{{ $committee->translated('mandate') }}</p>
                            @endif
                            @if ($committee->chairAuthority)
                                <p class="u-card__text u-muted">{{ __('blocks.committee_chaired_by', ['authority' => $committee->chairAuthority->name]) }}</p>
                            @endif
                            @if ($dormant)
                                <p class="cp-com-card__dormant">{{ __('blocks.committee_dormant') }}</p>
                            @else
                                <dl class="cp-com-tally">
                                    @foreach (array_filter([
                                        $seatsKnown ? ['count' => $seats, 'key' => 'blocks.committee_seats', 'route' => 'members'] : null,
                                        ['count' => $documents, 'key' => 'blocks.committee_documents', 'route' => 'publications'],
                                        ['count' => $meetings, 'key' => 'blocks.committee_meetings', 'route' => 'events'],
                                    ]) as $item)
                                        <div>
                                            <dd>
                                                @if ($item['count'] > 0)
                                                    <a href="{{ route($item['route'], array_filter(['locale' => $locale, 'committee' => $item['route'] === 'publications' ? $committee->slug : null])) }}">{{ $item['count'] }}</a>
                                                @else
                                                    {{ $item['count'] }}
                                                @endif
                                            </dd>
                                            <dt>{{ trans_choice($item['key'], $item['count'], ['count' => $item['count']]) }}</dt>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @unless ($seatsKnown)
                <p class="u-help cp-com-note">{{ __('blocks.committee_seats_pending') }}</p>
            @endunless
        @endif
    </div>
</section>

@php $ground = 'white'; @endphp
@foreach ($otherBlocks as $block)
    @php
        $hidden = $block->type() === \App\Enums\BlockType::RichText && $block->isUnwritten() && ! $backOffice;
    @endphp
    @continue($hidden)
    @continue($block === $introBlock)
    @php $ground = $ground === 'white' ? 'cream' : 'white'; @endphp
    <x-block :block="$block" :ground="$ground" />
@endforeach

@include('partials.cpages.cta', [
    'ctaTitle' => __('cpages_a.committees_cta_title'),
    'ctaText' => __('cpages_a.committees_cta_text'),
    'ctaPrimary' => [__('cpages_a.browse_library'), route('publications', ['locale' => $locale])],
    'ctaSecondary' => [__('cpages_a.see_events'), route('events', ['locale' => $locale])],
])
@endsection
