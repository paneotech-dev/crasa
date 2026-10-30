@extends('layouts.admin')

@section('title', __('admin.section_settings'))
@section('note', __('admin.settings_note'))

@section('crumbs')
    <li aria-current="page">{{ __('admin.section_settings') }}</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.settings.update', ['locale' => app()->getLocale()]) }}">
        @csrf
        @method('PUT')

        @foreach ($groups as $group => $fields)
            {{-- The registrations group keeps its strings in its own file. --}}
            @php($p = $group === 'registrations' ? 'm3_deleg.' : 'admin.')
            <section class="a-panel">
                <div class="a-panel__head">
                    <h2>{{ __($p.'settings_group_'.$group) }}</h2>
                    <span class="a-panel__meta">{{ __($p.'settings_group_'.$group.'_note') }}</span>
                </div>

                <div class="a-form">
                    @foreach ($fields as $key => $rules)
                        <div class="a-field">
                            <label class="a-label" for="{{ $key }}">{{ __($p.'setting_'.$key) }}</label>
                            <input
                                class="a-input"
                                type="{{ in_array('email', $rules, true) ? 'email' : (in_array('url', $rules, true) ? 'url' : 'text') }}"
                                id="{{ $key }}"
                                name="{{ $key }}"
                                value="{{ old($key, $values[$key] ?? '') }}"
                                @error($key) aria-invalid="true" aria-describedby="{{ $key }}-error" @enderror
                            >
                            <span class="a-hint">{{ __($p.'setting_'.$key.'_hint') }}</span>
                            @error($key)
                                <p class="c-field__error" id="{{ $key }}-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="a-form__actions">
            <button type="submit" class="a-btn a-btn--primary">{{ __('admin.settings_save') }}</button>
        </div>
    </form>
@endsection
