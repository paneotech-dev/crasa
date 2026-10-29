@extends('layouts.public')
@section('title', __('auth.choose_password'))
@section('content')
    <div class="c-section">
        <div class="c-page c-page--reading c-auth">
            <h1>{{ __('auth.choose_password') }}</h1>
            <p class="c-lead">{{ __('auth.password_rule') }}</p>

            <form method="POST" action="{{ route('password.update', ['locale' => app()->getLocale()]) }}" class="c-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="c-field">
                    <label class="c-label" for="email">{{ __('auth.email') }}</label>
                    <input class="c-input" type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username">
                    @error('email')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>

                <div class="c-field">
                    <label class="c-label" for="password">{{ __('auth.new_password') }}</label>
                    <input class="c-input" type="password" id="password" name="password" required autocomplete="new-password">
                    @error('password')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>

                <div class="c-field">
                    <label class="c-label" for="password_confirmation">{{ __('auth.confirm_password') }}</label>
                    <input class="c-input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>

                @include('partials.turnstile')
                <button type="submit" class="c-btn c-btn--primary">{{ __('auth.set_password') }}</button>
            </form>
        </div>
    </div>
@endsection
