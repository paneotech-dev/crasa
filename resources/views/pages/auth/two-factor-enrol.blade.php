@extends('layouts.public')
@section('title', __('auth.enrol_title'))

@section('content')
    <div class="c-section">
        <div class="c-page c-page--reading c-auth">
            <h1>{{ __('auth.enrol_title') }}</h1>
            <p class="c-lead">{{ __('auth.enrol_intro') }}</p>

            @if (auth()->user()->role->requiresTwoFactor())
                <div class="c-notice c-notice--pending" role="status">
                    <div>
                        <div class="c-notice__title">{{ __('auth.mandatory_title') }}</div>
                        <div class="c-notice__body">{{ __('auth.mandatory_body') }}</div>
                    </div>
                </div>
            @endif

            <div class="c-enrol">
                <div class="c-enrol__qr">{!! $qr !!}</div>
                <div class="c-enrol__manual">
                    <h2>{{ __('auth.cannot_scan') }}</h2>
                    <p class="c-small">{{ __('auth.manual_intro') }}</p>
                    <p class="c-secret"><code>{{ $formattedSecret }}</code></p>
                </div>
            </div>

            @error('code')
                <div class="c-notice c-notice--error" role="alert">
                    <div><div class="c-notice__body">{{ $message }}</div></div>
                </div>
            @enderror

            <form method="POST" action="{{ route('two-factor.confirm', ['locale' => app()->getLocale()]) }}" class="c-form">
                @csrf
                <div class="c-field">
                    <label class="c-label" for="code">{{ __('auth.confirm_code') }}</label>
                    <input class="c-input c-input--code" type="text" id="code" name="code"
                           inputmode="numeric" autocomplete="one-time-code" required>
                </div>
                <button type="submit" class="c-btn c-btn--primary">{{ __('auth.confirm_enable') }}</button>
            </form>
        </div>
    </div>
@endsection
