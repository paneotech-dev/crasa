@extends('layouts.public')
@section('title', __('auth.choose_password'))
@section('content')
    <x-cp-auth :title="__('auth.choose_password')" :intro="__('auth.password_rule')" :side="false">
        <form method="POST" action="{{ route('password.update', ['locale' => app()->getLocale()]) }}" class="cp-auth__form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="u-field @error('email') u-field--error @enderror">
                <label for="email">{{ __('auth.email') }}</label>
                <input class="u-input" type="email" id="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" @error('email') aria-describedby="email-error" aria-invalid="true" @enderror>
                @error('email')<p class="u-error" id="email-error">{{ $message }}</p>@enderror
            </div>

            <div class="u-field @error('password') u-field--error @enderror">
                <label for="password">{{ __('auth.new_password') }}</label>
                <input class="u-input" type="password" id="password" name="password" required autocomplete="new-password" @error('password') aria-describedby="password-error" aria-invalid="true" @enderror>
                @error('password')<p class="u-error" id="password-error">{{ $message }}</p>@enderror
            </div>

            <div class="u-field">
                <label for="password_confirmation">{{ __('auth.confirm_password') }}</label>
                <input class="u-input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            </div>

            @include('partials.turnstile')
            <button type="submit" class="u-btn u-btn--primary cp-auth__submit">{{ __('auth.set_password') }}</button>
        </form>
    </x-cp-auth>
@endsection
