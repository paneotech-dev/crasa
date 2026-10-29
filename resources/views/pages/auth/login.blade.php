@extends('layouts.public')
@section('title', __('auth.sign_in'))

@section('content')
    <x-cp-auth :title="__('auth.sign_in')" :intro="__('auth.intro')" :help="__('auth.logged_note')">
        @if (session('status'))
            <div class="u-success" role="status">{{ session('status') }}</div>
        @endif

        @error('email')
            <div class="cp-auth__alert" role="alert">
                <svg class="cp-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path>
                </svg>
                <span>{{ $message }}</span>
            </div>
        @enderror

        <form method="POST" action="{{ route('login.store', ['locale' => app()->getLocale()]) }}" class="cp-auth__form">
            @csrf

            <div class="u-field">
                <label for="email">{{ __('auth.email') }}</label>
                <input class="u-input" type="email" id="email" name="email" value="{{ old('email') }}"
                       required autocomplete="username" autofocus>
            </div>

            <div class="u-field @error('password') u-field--error @enderror">
                <label for="password">{{ __('auth.password') }}</label>
                <input class="u-input" type="password" id="password" name="password"
                       required autocomplete="current-password" @error('password') aria-describedby="password-error" aria-invalid="true" @enderror>
                @error('password')<p class="u-error" id="password-error">{{ $message }}</p>@enderror
            </div>

            <div class="cp-auth__check">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember">{{ __('auth.remember') }}</label>
            </div>

            @include('partials.turnstile')
            <button type="submit" class="u-btn u-btn--primary cp-auth__submit">{{ __('auth.sign_in') }}</button>

            <p class="cp-auth__link">
                <a href="{{ route('password.request', ['locale' => app()->getLocale()]) }}">{{ __('auth.forgot') }}</a>
            </p>
        </form>
    </x-cp-auth>
@endsection
