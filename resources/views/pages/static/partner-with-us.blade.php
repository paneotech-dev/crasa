{{--
    Partner with us (30 October 2026). The page record and its blocks carry
    every word, so the Secretariat edits them in the back office; this
    template lays them out: a short hero, why partner and the ways to
    partner as cards, how it works as three numbered steps, and a closing
    band to the contact page. A block body is plain text in which a short
    line opening a paragraph is a card's heading (StructuredText); a body
    without headings is shown as prose. A block with no text is skipped.
--}}
@extends('layouts.public')
@include('partials.cpages.styles')

@php
    use App\Support\StructuredText;

    $locale = app()->getLocale();
    $title = $page->translate('title');
    $description = $page->translate('meta_description');
    $backOffice = auth()->user()?->can('enter-back-office') ?? false;
    $renderer = app(\App\Services\ContentRenderer::class);
    $blocks = $page->visibleBlocks->keyBy(fn ($b) => $b->anchor ?: 'block-'.$b->id);

    $part = function (string $anchor) use ($blocks, $backOffice) {
        $block = $blocks->get($anchor);
        if ($block === null || ($block->isUnwritten() && ! $backOffice)) {
            return null;
        }
        $body = $block->translate('body');
        $value = $body === null ? '' : ($backOffice ? $body->value : \App\Support\PublishedText::withoutDraftingNotes($body->value)['text']);

        return [
            'block' => $block,
            'heading' => $block->translate('heading')?->value,
            'body' => $value,
            'parts' => StructuredText::sections($value),
            'lang' => $body?->isFallback() ? $body->locale : null,
        ];
    };

    $why = $part('why-partner');
    $ways = $part('ways-to-partner');
    $how = $part('how-it-works');

    // Decorative only: the card's heading names it.
    $whyIcons = ['globe', 'grid', 'people', 'chart'];
    $wayIcons = ['flag', 'book', 'chart', 'calendar', 'info'];

    $cta = $page->visibleBlocks->first(fn ($b) => $b->type() === \App\Enums\BlockType::CallToAction);
    $ctaUrl = $cta ? \App\Support\Navigation::resolve($cta->setting('cta_url')) : null;
    $contactUrl = route('contact', ['locale' => $locale]);

    // ?theme=<slug>, from a thematic area page's button. Only a slug is
    // accepted; its name is the published page's title where one has that
    // slug, the slug in words otherwise, and is printed escaped.
    $themeSlug = request()->query('theme');
    $themeName = null;
    if (is_string($themeSlug) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $themeSlug) && strlen($themeSlug) <= 80) {
        $themePage = \App\Models\Page::published()->where('slug', $themeSlug)->with('translations')->first();
        $themeName = $themePage?->translate('title')?->value ?: \Illuminate\Support\Str::ucfirst(str_replace('-', ' ', $themeSlug));
    }
@endphp

@section('title', $title?->value ?? __('d3_partner.menu'))
@if ($description)
    @section('description', $description->value)
@endif

{{-- Listed under About in the menu, so the trail says so. --}}
@section('breadcrumb')
    <li><a href="{{ route('about', ['locale' => $locale]) }}">{{ __('nav.about') }}</a></li>
    <li aria-current="page">{{ $title?->value ?? __('d3_partner.menu') }}</li>
@endsection

@section('content')
    @include('partials.cpages.hero', [
        'eyebrow' => __('nav.about'),
        'title' => $page->translate('heading')?->value ?: ($title?->value ?? __('d3_partner.menu')),
        'lead' => $page->translate('standfirst')?->value,
        'photoName' => 'institutional',
        'photoAlt' => '',
        'heroActions' => '<a class="u-btn u-btn--primary" href="'.e($ctaUrl ?: $contactUrl).'">'.e($cta?->translate('cta_label')?->value ?: __('d3_partner.contact')).'</a>',
    ])

    @if ($themeName)
        <div class="u-container cp-partner-theme">
            <p class="u-panel u-panel--tint">{{ __('d3_partner.theme', ['theme' => $themeName]) }}</p>
        </div>
    @endif

    @foreach ([['why-partner', $why, $whyIcons, 'u-cols-4', 'white'], ['ways-to-partner', $ways, $wayIcons, 'u-cols-3', 'tint-green']] as [$anchor, $section, $icons, $cols, $ground])
        @continue($section === null)
        <section class="u-section {{ $ground === 'tint-green' ? 'u-section--tint' : '' }}" data-ground="{{ $ground }}" id="{{ $anchor }}" aria-labelledby="{{ $anchor }}-title">
            <div class="u-container" @if ($section['lang']) lang="{{ $section['lang'] }}" @endif>
                <h2 class="u-h2" id="{{ $anchor }}-title">{{ $section['heading'] }}</h2>
                @foreach ($section['parts']['intro'] as $paragraph)
                    <p class="u-lead">{!! $renderer->render($paragraph, 'block:'.$section['block']->id) !!}</p>
                @endforeach
                @if ($section['parts']['sections'] !== [])
                    <ul class="{{ $cols }} cp-partner-cards" role="list">
                        @foreach ($section['parts']['sections'] as $i => $card)
                            <li class="u-card">
                                <div class="u-card__body">
                                    <span class="cp-icon-badge" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false">@include('partials.cpages.icon', ['name' => $icons[$i % count($icons)]])</svg></span>
                                    <h3 class="u-card__title">{{ $card['heading'] }}</h3>
                                    @foreach ($card['paragraphs'] as $paragraph)
                                        <p class="u-card__text">{!! $renderer->render($paragraph, 'block:'.$section['block']->id) !!}</p>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endforeach

    @if ($how)
        <section class="u-section" data-ground="white" id="how-it-works" aria-labelledby="how-it-works-title">
            <div class="u-container" @if ($how['lang']) lang="{{ $how['lang'] }}" @endif>
                <h2 class="u-h2" id="how-it-works-title">{{ $how['heading'] }}</h2>
                @foreach ($how['parts']['intro'] as $paragraph)
                    <p class="u-lead">{!! $renderer->render($paragraph, 'block:'.$how['block']->id) !!}</p>
                @endforeach
                @if ($how['parts']['sections'] !== [])
                    <ol class="u-cols-3 cp-partner-steps" role="list">
                        @foreach ($how['parts']['sections'] as $i => $step)
                            <li class="cp-partner-step">
                                <span class="cp-partner-step__n" aria-hidden="true">{{ $i + 1 }}</span>
                                <h3 class="u-card__title">{{ $step['heading'] }}</h3>
                                @foreach ($step['paragraphs'] as $paragraph)
                                    <p class="u-card__text">{!! $renderer->render($paragraph, 'block:'.$how['block']->id) !!}</p>
                                @endforeach
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>
    @endif

    @include('partials.cpages.cta', [
        'ctaTitle' => $cta?->translate('headline')?->value ?: __('d3_partner.contact'),
        'ctaPrimary' => [$cta?->translate('cta_label')?->value ?: __('d3_partner.contact'), $ctaUrl ?: $contactUrl],
    ])
@endsection
