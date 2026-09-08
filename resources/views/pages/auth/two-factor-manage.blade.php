@extends('layouts.public')
@section('title', __('auth.two_factor'))

@section('content')
    <div class="c-section">
        <div class="c-page c-page--reading c-auth">
            <h1>{{ __('auth.two_factor') }}</h1>

            @if (session('just_enrolled'))
                <div class="c-notice c-notice--success" role="status">
                    <div>
                        <div class="c-notice__title">{{ __('auth.enrolled_title') }}</div>
                        <div class="c-notice__body">{{ __('auth.enrolled_body') }}</div>
                    </div>
                </div>
            @endif

            @if (session('recovery_codes'))
                <div class="c-notice c-notice--pending" role="alert">
                    <div>
                        <div class="c-notice__title">{{ __('auth.codes_once_title') }}</div>
                        <div class="c-notice__body">{{ __('auth.codes_once_body') }}</div>
                    </div>
                </div>

                <ul class="c-codes" role="list">
                    @foreach (session('recovery_codes') as $code)
                        <li><code>{{ $code }}</code></li>
                    @endforeach
                </ul>
            @else
                <p>{{ trans_choice('auth.codes_remaining', $remaining, ['count' => $remaining]) }}</p>
            @endif

            <form method="POST" action="{{ route('two-factor.regenerate', ['locale' => app()->getLocale()]) }}">
                @csrf
                <button type="submit" class="c-btn c-btn--secondary">{{ __('auth.regenerate_codes') }}</button>
            </form>

            @unless (auth()->user()->role->requiresTwoFactor())
                <form method="POST" action="{{ route('two-factor.destroy', ['locale' => app()->getLocale()]) }}" class="c-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="c-btn c-btn--tertiary">{{ __('auth.turn_off') }}</button>
                </form>
            @endunless

            <p><a href="{{ route('account', ['locale' => app()->getLocale()]) }}">{{ __('account.back') }}</a></p>
        </div>
    </div>
@endsection
