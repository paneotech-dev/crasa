{{--
    Scaffolding, not content. This page exists so the layout, the switcher
    and the fallback rule can be seen working before any real screen is
    built. It is replaced by the homepage.
--}}
@extends('layouts.public')

@section('title', __('foundation.title'))
@section('description', __('foundation.body'))

@section('content')
    <div class="c-section">
        <div class="c-page c-page--reading c-stack">
            <h1>{{ __('foundation.heading') }}</h1>
            <p class="c-lead">{{ __('foundation.body') }}</p>

            <h2>{{ __('foundation.checklist_heading') }}</h2>
            <ul class="c-list">
                <li>{{ __('foundation.check_tokens') }}</li>
                <li>{{ __('foundation.check_chrome') }}</li>
                <li>{{ __('foundation.check_switcher') }}</li>
                <li>{{ __('foundation.check_fallback') }}</li>
            </ul>

            <h2>{{ __('foundation.notice_heading') }}</h2>
            <p class="c-small">{{ __('foundation.notice_absent') }}</p>
            {{-- Renders nothing when the reader is already in English. --}}
            <x-fallback-notice />
        </div>
    </div>
@endsection
