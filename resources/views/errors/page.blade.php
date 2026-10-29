{{--
    The error pages (403, 404, 419, 429), in the reader's language and inside
    the site's own header and footer, with a way back (UI system, 29 October
    2026): a short hero with the explanation and a search form, the main
    sections as cards, and a call-to-action band.

    The language comes from the address (bootstrap/app.php sets it before
    this renders), because an unmatched address never reaches SetLocale.
--}}
@extends('layouts.public')

@section('title', __('errors.'.$code.'_title'))

@php
    $locale = app()->getLocale();
    $back = url()->previous();
    $back = $back && $back !== url()->current() && str_starts_with($back, url('/')) ? $back : null;
@endphp

@section('content')
    @include('partials.cpages.styles')
    @include('errors._body', ['code' => $code, 'locale' => $locale, 'back' => $back])

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages_c.error_cta_title'),
        'ctaText' => __('cpages_c.error_cta_text'),
        'ctaPrimary' => [__('errors.home'), url('/'.$locale)],
        'ctaSecondary' => [__('cpages_c.search_cta_secondary'), url('/'.$locale.'/contact')],
    ])
@endsection
