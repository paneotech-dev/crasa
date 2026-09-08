@extends('layouts.public')
@section('title', __('auth.reset_title'))
@section('content')
    <div class="c-section">
        <div class="c-page c-page--reading c-auth">
            <h1>{{ __('auth.reset_title') }}</h1>
            <p class="c-lead">{{ __('auth.reset_intro') }}</p>

            @if (session('status'))
                <div class="c-notice c-notice--success" role="status">
                    <div><div class="c-notice__body">{{ session('status') }}</div></div>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email', ['locale' => app()->getLocale()]) }}" class="c-form">
                @csrf
                <div class="c-field">
                    <label class="c-label" for="email">{{ __('auth.email') }}</label>
                    <input class="c-input" type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="username">
                    @error('email')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="c-btn c-btn--primary">{{ __('auth.send_link') }}</button>
            </form>
        </div>
    </div>
@endsection
