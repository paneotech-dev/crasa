@extends('layouts.public')
@section('title', __('auth.two_factor'))

@section('content')
    <div class="c-section">
        <div class="c-page c-page--reading c-auth">
            <h1>{{ __('auth.two_factor') }}</h1>
            <p class="c-lead">{{ __('auth.challenge_intro') }}</p>

            @error('code')
                <div class="c-notice c-notice--error" role="alert">
                    <div><div class="c-notice__body">{{ $message }}</div></div>
                </div>
            @enderror

            <form method="POST" action="{{ route('two-factor.challenge.store', ['locale' => app()->getLocale()]) }}" class="c-form">
                @csrf
                <input type="hidden" name="mode" value="totp">

                <div class="c-field">
                    <label class="c-label" for="code">{{ __('auth.authenticator_code') }}</label>
                    <input class="c-input c-input--code" type="text" id="code" name="code"
                           inputmode="numeric" autocomplete="one-time-code" required autofocus
                           aria-describedby="code-hint">
                    <p class="c-field__hint" id="code-hint">{{ __('auth.code_hint') }}</p>
                </div>

                <button type="submit" class="c-btn c-btn--primary">{{ __('auth.verify') }}</button>
            </form>

            <details class="c-details">
                <summary>{{ __('auth.use_recovery') }}</summary>
                <form method="POST" action="{{ route('two-factor.challenge.store', ['locale' => app()->getLocale()]) }}" class="c-form">
                    @csrf
                    <input type="hidden" name="mode" value="recovery">
                    <div class="c-field">
                        <label class="c-label" for="recovery">{{ __('auth.recovery_code') }}</label>
                        <input class="c-input c-input--code" type="text" id="recovery" name="code" required
                               aria-describedby="recovery-hint">
                        <p class="c-field__hint" id="recovery-hint">{{ __('auth.recovery_hint') }}</p>
                    </div>
                    <button type="submit" class="c-btn c-btn--secondary">{{ __('auth.verify') }}</button>
                </form>
            </details>
        </div>
    </div>
@endsection
