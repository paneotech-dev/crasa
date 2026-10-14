{{--
    The error pages (403, 404, 419), in the reader's language and inside the
    site's own header and footer, with a way back. Until 14 October 2026
    these were Laravel's defaults: English only, no menu, no link home, and
    the member account met one in front of the regulators.

    The language comes from the address (bootstrap/app.php sets it before
    this renders), because an unmatched address never reaches SetLocale.
--}}
@extends('layouts.public')

@section('title', __('errors.'.$code.'_title'))

@section('content')
    <x-section-band
        :eyebrow="__('errors.eyebrow', ['code' => $code])"
        :title="__('errors.'.$code.'_title')"
        :lead="__('errors.'.$code.'_body')"
    />

    <div class="c-section">
        <div class="c-page c-page--reading">
            <p>
                <a class="c-btn c-btn--primary" href="{{ url('/'.app()->getLocale()) }}">{{ __('errors.home') }}</a>
                @if (($back = url()->previous()) && $back !== url()->current() && str_starts_with($back, url('/')))
                    <a class="c-btn c-btn--secondary" href="{{ $back }}">{{ __('errors.back') }}</a>
                @endif
            </p>
        </div>
    </div>
@endsection
