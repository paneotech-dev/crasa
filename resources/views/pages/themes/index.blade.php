{{--
    Thematic areas (30 October 2026): every theme with its icon, title and
    one line, each linking to its own page. Words in lang/xx/d3_themes.php.
--}}
@extends('layouts.public')
@include('partials.cpages.styles')
@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/themes.css') }}">
@endpush

@php $locale = app()->getLocale(); @endphp

@section('title', __('d3_themes.index_title'))
@section('description', __('d3_themes.index_lead'))

@section('breadcrumb')
    <li><a href="{{ route('about', ['locale' => $locale]) }}">{{ __('nav.about') }}</a></li>
    <li aria-current="page">{{ __('d3_themes.index_title') }}</li>
@endsection

@section('content')
<section class="u-hero cp-hero">
    <div class="u-container">
        <p class="u-eyebrow">{{ __('d3_themes.index_eyebrow') }}</p>
        <h1 class="u-h1">{{ __('d3_themes.index_title') }}</h1>
        <p class="u-lead">{{ __('d3_themes.index_lead') }}</p>
    </div>
</section>

<section class="u-section">
    <div class="u-container">
        <ul class="th-grid" role="list">
            @foreach ($themes as $theme)
                <li class="th-item">
                    <span class="th-card__ico">{!! \App\Support\Themes::icon($theme['icon']) !!}</span>
                    <div>
                        <h2><a href="{{ route('themes.show', ['locale' => $locale, 'theme' => $theme['slug']]) }}">{{ $theme['title'] }}</a></h2>
                        <p>{{ $theme['summary'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endsection
