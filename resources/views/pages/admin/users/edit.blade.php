@extends('layouts.admin')
@php
    $locale = app()->getLocale();
    $seats = $account->committees->mapWithKeys(fn ($c) => [$c->id => $c->pivot->role]);
    $currentRole = $account->customRole?->key ?? $account->role->value;
@endphp
@section('title', __('d3_people.edit_title', ['name' => $account->name]))
@section('note', __('d3_people.edit_note'))

@section('crumbs')
    <li><a href="{{ route('admin.users', ['locale' => $locale]) }}">{{ __('admin.users_title') }}</a></li>
    <li><a href="{{ route('admin.users.show', ['locale' => $locale, 'user' => $account->id]) }}">{{ $account->name }}</a></li>
    <li aria-current="page">{{ __('d3_people.edit') }}</li>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/d3-people.css') }}">
@endpush

@section('content')
    <form class="a-form aw-wide d3p-form" method="POST" action="{{ route('admin.users.update', ['locale' => $locale, 'user' => $account->id]) }}" novalidate>
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="a-notice a-notice--error" role="alert">
                <strong>{{ __('fa.form_errors') }}</strong>
                <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('d3_people.section_account') }}</h2></div>
            @foreach (['name' => ['text', __('account.name')], 'email' => ['email', __('auth.email')]] as $field => [$type, $label])
                <div @class(['a-field', 'aw-field--error' => $errors->has($field)])>
                    <label class="a-label" for="{{ $field }}">{{ $label }} <span class="a-required">{{ __('fa.required') }}</span></label>
                    <input class="a-input" type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $account->{$field}) }}" required aria-required="true"
                        @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                    @error($field)<span class="aw-error" id="{{ $field }}-error">{{ $message }}</span>@enderror
                </div>
            @endforeach

            <div @class(['a-field', 'aw-field--error' => $errors->has('role')])>
                <label class="a-label" for="role">{{ __('account.role') }}</label>
                <select class="a-input" id="role" name="role" @disabled(! $mayAssignRole) aria-describedby="role-help @error('role') role-error @enderror">
                    @foreach ($roleOptions as $option)
                        <option value="{{ $option->key }}" @selected(old('role', $currentRole) === $option->key)>{{ $option->label() }}</option>
                    @endforeach
                </select>
                <span class="a-hint" id="role-help">{{ $mayAssignRole ? __('d3_people.role_help') : __('d3_people.role_locked') }}</span>
                @error('role')<span class="aw-error" id="role-error">{{ $message }}</span>@enderror
            </div>
        </section>

        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('d3_people.section_member_state') }}</h2></div>
            <div @class(['a-field', 'aw-field--error' => $errors->has('country_id')])>
                <label class="a-label" for="country_id">{{ __('contact.field_country') }}</label>
                <select class="a-input" id="country_id" name="country_id">
                    <option value="">{{ __('admin.secretariat_no_country') }}</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected((string) old('country_id', $account->country_id) === (string) $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>
            <div @class(['a-field', 'aw-field--error' => $errors->has('authority_id')])>
                <label class="a-label" for="authority_id">{{ __('d3_people.authority') }}</label>
                <select class="a-input" id="authority_id" name="authority_id" aria-describedby="authority_id-help @error('authority_id') authority_id-error @enderror">
                    <option value="">{{ __('d3_people.no_authority') }}</option>
                    @foreach ($authorities as $authority)
                        <option value="{{ $authority->id }}" @selected((string) old('authority_id', $account->authority_id) === (string) $authority->id)>{{ $authority->acronym }}@if ($authority->country), {{ $authority->country->name }}@endif</option>
                    @endforeach
                </select>
                <span class="a-hint" id="authority_id-help">{{ __('d3_people.authority_help') }}</span>
                @error('authority_id')<span class="aw-error" id="authority_id-error">{{ $message }}</span>@enderror
            </div>
        </section>

        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('d3_people.section_committees') }}</h2></div>
            <p class="a-hint">{{ __('d3_people.committees_help') }}</p>
            @if ($committees->isEmpty())
                <p>{{ __('d3_people.no_committees') }}</p>
            @else
                <div class="a-tablewrap">
                    <table class="a-table">
                        <caption class="c-sr">{{ __('d3_people.section_committees') }}</caption>
                        <thead><tr>
                            <th scope="col">{{ __('d3_people.committee') }}</th>
                            <th scope="col">{{ __('d3_people.seat') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($committees as $committee)
                                @php($val = old('committees.'.$committee->id, $seats[$committee->id] ?? ''))
                                <tr>
                                    <th scope="row"><label for="committee-{{ $committee->id }}">{{ $committee->name }}</label></th>
                                    <td>
                                        <select class="a-input" id="committee-{{ $committee->id }}" name="committees[{{ $committee->id }}]">
                                            <option value="">{{ __('d3_people.seat_none') }}</option>
                                            @foreach ($seatRoles as $seat)
                                                <option value="{{ $seat->value }}" @selected($val === $seat->value)>{{ __('extranet.seat_'.$seat->value) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <p class="a-hint">{{ __('d3_people.audit_note') }}</p>
        <div class="a-form__actions">
            <button type="submit" class="a-btn a-btn--primary">{{ __('d3_people.save_user') }}</button>
            <a href="{{ route('admin.users.show', ['locale' => $locale, 'user' => $account->id]) }}">{{ __('admin.cancel') }}</a>
        </div>
    </form>
@endsection
