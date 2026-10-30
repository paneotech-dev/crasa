{{--
    One thematic area (30 October 2026): its icon, title and explanation,
    the committee and library links that exist for it, and the way to
    partner with CRASA on it (the partner page reads ?theme=<slug>).
--}}
@extends('layouts.public')
@include('partials.cpages.styles')
@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/themes.css') }}">
@endpush

@php
    $locale = app()->getLocale();
    $partnerUrl = \App\Support\Themes::partnerUrl($theme['slug']);
@endphp

@section('title', $theme['title'])
@section('description', $theme['summary'])

@section('content')
<section class="u-hero cp-hero th-hero">
    <div class="u-container">
        <span class="th-hero__ico">{{ \App\Support\Themes::icon($theme['icon']) }}</span>
        <p class="u-eyebrow">{{ __('d3_themes.show_eyebrow') }}</p>
        <h1 class="u-h1">{{ $theme['title'] }}</h1>
        <p class="u-lead">{{ $theme['summary'] }}</p>
        @include('partials.k.share')
        <div class="u-actions">
            <a class="u-btn u-btn--primary" href="{{ $partnerUrl }}" data-partner-theme="{{ $theme['slug'] }}">{{ __('d3_themes.partner_button') }}</a>
        </div>
    </div>
</section>

<section class="u-section">
    <div class="u-container u-main-side">
        <div class="th-body u-text">
            @foreach ($theme['body'] as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>

        <aside class="cp-side-stack" aria-labelledby="th-related-h">
            <div class="u-panel u-panel--tint">
                <h2 class="cp-side-title" id="th-related-h">{{ __('d3_themes.related_heading') }}</h2>
                <ul class="th-related" role="list">
                    @foreach ($committees as $committee)
                        <li>{{ __('d3_themes.related_committee') }}: <a href="{{ route('committees.show', ['locale' => $locale, 'committee' => $committee->slug]) }}">{{ $committee->name }}</a></li>
                    @endforeach
                    @if ($committees->isNotEmpty())
                        <li><a href="{{ route('publications', ['locale' => $locale, 'committee' => $committees->first()->slug]) }}">{{ __('d3_themes.related_publications') }}</a></li>
                    @elseif ($theme['sector'])
                        <li><a href="{{ route('publications', ['locale' => $locale, 'sector' => $theme['sector']]) }}">{{ __('d3_themes.related_publications') }}</a></li>
                    @endif
                    <li><a href="{{ route('themes', ['locale' => $locale]) }}">{{ __('d3_themes.all_themes') }}</a></li>
                </ul>
            </div>
            <nav class="u-panel" aria-label="{{ __('d3_themes.others_heading') }}">
                <h2 class="cp-side-title">{{ __('d3_themes.others_heading') }}</h2>
                <ul class="cp-chips" role="list">
                    @foreach ($others as $other)
                        <li><a class="u-chip" href="{{ route('themes.show', ['locale' => $locale, 'theme' => $other['slug']]) }}">{{ $other['title'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </aside>
    </div>
</section>

@include('partials.cpages.cta', [
    'ctaTitle' => __('d3_themes.partner_heading'),
    'ctaText' => '',
    'ctaPrimary' => [__('d3_themes.partner_button'), $partnerUrl],
    'ctaSecondary' => [__('d3_themes.all_themes'), route('themes', ['locale' => $locale])],
])
@endsection
