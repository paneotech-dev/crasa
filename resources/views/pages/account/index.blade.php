@extends('layouts.public')
@section('title', __('account.title'))

@section('content')
    <div class="c-section">
        <div class="c-page c-page--reading">
            <h1>{{ __('account.title') }}</h1>

            @if (session('status'))
                <div class="c-notice c-notice--success" role="status">
                    <div><div class="c-notice__body">{{ session('status') }}</div></div>
                </div>
            @endif

            <dl class="c-meta">
                <div><dt>{{ __('account.role') }}</dt><dd>{{ $user->role->label() }}</dd></div>
                <div><dt>{{ __('account.status') }}</dt><dd>{{ $user->status->label() }}</dd></div>
                @if ($user->country)<div><dt>{{ __('contact.field_country') }}</dt><dd>{{ $user->country->name }}</dd></div>@endif
                @if ($user->last_login_at)<div><dt>{{ __('account.last_login') }}</dt><dd>{{ $user->last_login_at->translatedFormat('j F Y, H:i') }}</dd></div>@endif
            </dl>

            <h2>{{ __('account.details') }}</h2>
            <form method="POST" action="{{ route('account.update', ['locale' => app()->getLocale()]) }}" class="c-form">
                @csrf
                @method('PUT')

                <div class="c-field">
                    <label class="c-label" for="name">{{ __('account.name') }}</label>
                    <input class="c-input" type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    @error('name')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>

                <div class="c-field">
                    <label class="c-label" for="email">{{ __('auth.email') }}</label>
                    <input class="c-input" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
                    @error('email')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>

                <div class="c-field">
                    <label class="c-label" for="preferred_locale">{{ __('account.language') }}</label>
                    <select class="c-input" id="preferred_locale" name="preferred_locale">
                        @foreach (\App\Support\Locales::all() as $code => $language)
                            <option value="{{ $code }}" @selected(old('preferred_locale', $user->preferred_locale) === $code)>{{ $language['native'] }}</option>
                        @endforeach
                    </select>
                </div>

                <fieldset class="c-fieldset">
                    <legend class="c-label">{{ __('account.notifications') }}</legend>
                    <p class="c-field__hint">{{ __('account.notifications_hint') }}</p>
                    @foreach ($notifications as $key)
                        <div class="c-field c-field--inline">
                            <input type="checkbox" id="notify-{{ $key }}" name="notifications[]" value="{{ $key }}"
                                   @checked($user->wantsNotification($key))>
                            <label for="notify-{{ $key }}">{{ __('account.notify_'.$key) }}</label>
                        </div>
                    @endforeach
                </fieldset>

                <button type="submit" class="c-btn c-btn--primary">{{ __('account.save') }}</button>
            </form>

            <h2>{{ __('auth.two_factor') }}</h2>
            @if ($user->hasConfirmedTwoFactor())
                <p>{{ trans_choice('auth.codes_remaining', $remainingRecoveryCodes, ['count' => $remainingRecoveryCodes]) }}</p>
            @else
                <p>{{ __('auth.not_enrolled') }}</p>
            @endif
            <p><a class="c-btn c-btn--secondary" href="{{ route('two-factor.enrol', ['locale' => app()->getLocale()]) }}">{{ __('auth.manage_two_factor') }}</a></p>

            <h2>{{ __('account.change_password') }}</h2>
            <form method="POST" action="{{ route('account.password', ['locale' => app()->getLocale()]) }}" class="c-form">
                @csrf
                @method('PUT')

                <div class="c-field">
                    <label class="c-label" for="current_password">{{ __('account.current_password') }}</label>
                    <input class="c-input" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    @error('current_password')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>

                <div class="c-field">
                    <label class="c-label" for="new_password">{{ __('auth.new_password') }}</label>
                    <input class="c-input" type="password" id="new_password" name="password" required autocomplete="new-password">
                    @error('password')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>

                <div class="c-field">
                    <label class="c-label" for="new_password_confirmation">{{ __('auth.confirm_password') }}</label>
                    <input class="c-input" type="password" id="new_password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>

                <button type="submit" class="c-btn c-btn--primary">{{ __('account.change_password') }}</button>
            </form>

            <form method="POST" action="{{ route('logout', ['locale' => app()->getLocale()]) }}">
                @csrf
                <button type="submit" class="c-btn c-btn--tertiary">{{ __('auth.sign_out') }}</button>
            </form>
        </div>
    </div>
@endsection
