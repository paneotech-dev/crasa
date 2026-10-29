@extends('layouts.public')
@section('title', __('auth.sign_in'))

@section('content')
    <div class="c-section">
        <div class="c-page c-page--reading c-auth">
            <h1>{{ __('auth.sign_in') }}</h1>
            <p class="c-lead">{{ __('auth.intro') }}</p>

            @if (session('status'))
                <div class="c-notice c-notice--success" role="status">
                    <div><div class="c-notice__body">{{ session('status') }}</div></div>
                </div>
            @endif

            @error('email')
                <div class="c-notice c-notice--error" role="alert">
                    <svg class="c-notice__icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="var(--c-red)" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path>
                    </svg>
                    <div><div class="c-notice__body">{{ $message }}</div></div>
                </div>
            @enderror

            <form method="POST" action="{{ route('login.store', ['locale' => app()->getLocale()]) }}" class="c-form">
                @csrf

                <div class="c-field">
                    <label class="c-label" for="email">{{ __('auth.email') }}</label>
                    <input class="c-input" type="email" id="email" name="email" value="{{ old('email') }}"
                           required autocomplete="username" autofocus>
                </div>

                <div class="c-field">
                    <label class="c-label" for="password">{{ __('auth.password') }}</label>
                    <input class="c-input" type="password" id="password" name="password"
                           required autocomplete="current-password">
                    @error('password')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>

                <div class="c-field c-field--inline">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">{{ __('auth.remember') }}</label>
                </div>

                @include('partials.turnstile')
                <button type="submit" class="c-btn c-btn--primary">{{ __('auth.sign_in') }}</button>

                <p class="c-form__note">
                    <a href="{{ route('password.request', ['locale' => app()->getLocale()]) }}">{{ __('auth.forgot') }}</a>
                </p>
            </form>

            <p class="c-small">{{ __('auth.logged_note') }}</p>
        </div>
    </div>
@endsection
