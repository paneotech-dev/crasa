{{--
    One member state's record, for a delegate to correct and confirm.

    Every field the platform holds about that member state's regulator is on
    this one screen and editable in place. A delegate should not have to be
    told where else to look.

    The confirmation names a person. The signed-in account is the Secretariat
    operator driving the session; the person vouching is the delegate beside
    them, and a confirmation recording the operator confirms nothing.
--}}
@extends('layouts.admin')

@section('title', $country->name)
@section('note', __('admin.confirm_edit_note'))

@section('crumbs')
    <li><a href="{{ route('admin.confirmations', ['locale' => app()->getLocale()]) }}">{{ __('admin.confirmations') }}</a></li>
    <li aria-current="page">{{ $country->name }}</li>
@endsection

@section('content')
    @if ($authority?->isConfirmed())
        <div class="a-notice a-notice--success" role="status">
            <strong>{{ __('admin.confirm_confirmed') }}</strong>
            {{ __('admin.confirm_by', [
                'name' => $authority->confirmed_by_name,
                'date' => $authority->confirmed_at->isoFormat('D MMMM YYYY'),
            ]) }}
        </div>
    @endif

    <form class="a-form" method="POST"
          action="{{ route('admin.confirmations.update', ['locale' => app()->getLocale(), 'iso' => $country->iso_code]) }}">
        @csrf @method('PUT')

        <div class="s-field">
            <label class="a-label" for="name">{{ __('admin.confirm_authority_name') }}</label>
            <input class="c-input" type="text" id="name" name="name" required maxlength="200"
                   value="{{ old('name', $authority?->name) }}">
        </div>

        <div class="s-field">
            <label class="a-label" for="acronym">{{ __('members.detail_acronym') }}</label>
            <input class="c-input" type="text" id="acronym" name="acronym" required maxlength="30"
                   value="{{ old('acronym', $authority?->acronym) }}">
        </div>

        <div class="s-field">
            <label class="a-label" for="website">{{ __('admin.confirm_website') }}</label>
            <input class="c-input" type="url" id="website" name="website" maxlength="255"
                   value="{{ old('website', $authority?->website) }}">
            <p class="s-field__hint">{{ __('admin.confirm_website_hint') }}</p>
        </div>

        <div class="s-field">
            <label class="a-label" for="established_year">{{ __('admin.confirm_established') }}</label>
            <input class="c-input" type="number" id="established_year" name="established_year"
                   min="1900" max="{{ now()->year }}"
                   value="{{ old('established_year', $authority?->established_year) }}">
        </div>

        <fieldset class="c-fieldset">
            <legend class="a-label">{{ __('admin.confirm_sectors') }}</legend>
            <p class="s-field__hint">{{ __('admin.confirm_sectors_hint') }}</p>
            @foreach ($sectors as $sector)
                <div class="c-field c-field--inline">
                    <input type="checkbox" id="sector-{{ $sector->id }}" name="sectors[]" value="{{ $sector->id }}"
                           @checked(in_array($sector->id, old('sectors', $authority?->sectors->pluck('id')->all() ?? []), false))>
                    <label for="sector-{{ $sector->id }}">{{ $sector->name }}</label>
                </div>
            @endforeach
        </fieldset>

        <fieldset class="c-fieldset">
            <legend class="a-label">{{ __('admin.confirm_seats') }}</legend>
            <p class="s-field__hint">{{ __('admin.confirm_seats_hint') }}</p>
            @foreach ($committees as $committee)
                <div class="c-field c-field--inline">
                    <input type="checkbox" id="committee-{{ $committee->id }}" name="committees[]" value="{{ $committee->id }}"
                           @checked(in_array($committee->id, old('committees', $seated), false))>
                    <label for="committee-{{ $committee->id }}">{{ $committee->name }}</label>
                </div>
            @endforeach
        </fieldset>

        <div class="s-field">
            <label class="a-label" for="confirmed_by_name">{{ __('admin.confirm_who') }}</label>
            <input class="c-input" type="text" id="confirmed_by_name" name="confirmed_by_name" maxlength="160"
                   value="{{ old('confirmed_by_name', $authority?->confirmed_by_name) }}">
            {{-- The account signed in is the operator running the session.
                 The person vouching is the delegate beside them. --}}
            <p class="s-field__hint">{{ __('admin.confirm_who_hint') }}</p>
        </div>

        <div class="a-form__actions">
            <button class="a-btn" type="submit" name="confirm" value="0">{{ __('admin.confirm_save_only') }}</button>
            <button class="a-btn a-btn--primary" type="submit" name="confirm" value="1">{{ __('admin.confirm_action') }}</button>
        </div>
    </form>
@endsection
