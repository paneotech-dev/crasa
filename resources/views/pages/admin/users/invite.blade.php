@extends('layouts.admin')
@section('title', __('admin.invite'))
{{-- The heading is the layout's. This page carried a second h1 of its
     own, which is two h1s in one document and a structure failure under
     WCAG 2.1 AA. --}}
@section('note', __('admin.invite_intro'))

@section('content')
    <div class="a-card">

    <form method="POST" action="{{ route('admin.users.invite', ['locale' => app()->getLocale()]) }}" class="a-form">
        @csrf
        <div class="a-field">
            <label class="a-label" for="name">{{ __('account.name') }}</label>
            <input class="a-input" type="text" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<p class="c-field__error">{{ $message }}</p>@enderror
        </div>
        <div class="a-field">
            <label class="a-label" for="email">{{ __('auth.email') }}</label>
            <input class="a-input" type="email" id="email" name="email" value="{{ old('email') }}" required>
            @error('email')<p class="c-field__error">{{ $message }}</p>@enderror
        </div>
        <div class="a-field">
            <label class="a-label" for="role">{{ __('account.role') }}</label>
            <select class="a-input" id="role" name="role" required>
        @foreach ($roles as $role)
            <option value="{{ $role->value }}">{{ $role->label() }}</option>
        @endforeach
            </select>
        </div>
        <div class="a-field">
            <label class="a-label" for="country_id">{{ __('contact.field_country') }}</label>
            <select class="a-input" id="country_id" name="country_id">
        <option value="">{{ __('admin.secretariat_no_country') }}</option>
        @foreach ($countries as $country)
            <option value="{{ $country->id }}">{{ $country->displayName() }}</option>
        @endforeach
            </select>
        </div>
        <div class="a-field">
            <label class="a-label" for="preferred_locale">{{ __('account.language') }}</label>
            <select class="a-input" id="preferred_locale" name="preferred_locale">
        @foreach (\App\Support\Locales::all() as $code => $language)
            <option value="{{ $code }}">{{ $language['native'] }}</option>
        @endforeach
            </select>
        </div>
        <p class="c-form__note">{{ __('admin.invite_note') }}</p>
        <button type="submit" class="a-btn a-btn--primary">{{ __('admin.send_invite') }}</button>
    </form>
    </div>
@endsection
