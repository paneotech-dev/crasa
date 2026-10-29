@extends('layouts.public')
@section('title', __('auth.reset_title'))
@section('content')
    <x-cp-auth :title="__('auth.reset_title')" :intro="__('auth.reset_intro')" :help="__('cpages_c.auth_reset_help')">
        @if (session('status'))
            <div class="u-success" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email', ['locale' => app()->getLocale()]) }}" class="cp-auth__form">
            @csrf
            <div class="u-field @error('email') u-field--error @enderror">
                <label for="email">{{ __('auth.email') }}</label>
                <input class="u-input" type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="username" @error('email') aria-describedby="email-error" aria-invalid="true" @enderror>
                @error('email')<p class="u-error" id="email-error">{{ $message }}</p>@enderror
            </div>
            @include('partials.turnstile')
            <button type="submit" class="u-btn u-btn--primary cp-auth__submit">{{ __('auth.send_link') }}</button>
            <p class="cp-auth__link"><a href="{{ route('login', ['locale' => app()->getLocale()]) }}">{{ __('auth.sign_in') }}</a></p>
        </form>
    </x-cp-auth>
@endsection
