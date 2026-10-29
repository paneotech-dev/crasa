@extends('layouts.admin')
@php
    $locale = app()->getLocale();
    $isNew = ! $role->exists;
@endphp
@section('title', $isNew ? __('fa.roles_new') : $role->label())
@section('note', $isNew ? ($source ? __('fa.roles_copy_note', ['name' => $source->label()]) : __('fa.roles_new_note')) : trans_choice('fa.roles_used_by', $count ?? 0, ['count' => $count ?? 0]))

@section('crumbs')
    <li><a href="{{ route('admin.users', ['locale' => $locale]) }}">{{ __('admin.users_title') }}</a></li>
    <li><a href="{{ route('admin.roles', ['locale' => $locale]) }}">{{ __('fa.roles_title') }}</a></li>
    <li aria-current="page">{{ $isNew ? __('fa.roles_new') : $role->label() }}</li>
@endsection

@section('content')
    <form class="a-form aw-wide" method="POST" action="{{ $isNew ? route('admin.roles.store', ['locale' => $locale]) : route('admin.roles.update', ['locale' => $locale, 'role' => $role->key]) }}" novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        @if ($errors->any())
            <div class="a-notice a-notice--error" role="alert">
                <strong>{{ __('fa.form_errors') }}</strong>
                <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('fa.roles_names') }}</h2></div>
            @foreach (['en' => 'English', 'fr' => 'Français', 'pt' => 'Português'] as $code => $lang)
                <div @class(['a-field', 'aw-field--error' => $errors->has('name_'.$code)])>
                    <label class="a-label" for="name_{{ $code }}">{{ __('fa.roles_name_in', ['language' => $lang]) }}
                        @if ($code === 'en')<span class="a-required">{{ __('fa.required') }}</span>@endif
                    </label>
                    <input class="a-input" id="name_{{ $code }}" name="name_{{ $code }}" value="{{ old('name_'.$code, $role->{'name_'.$code}) }}" @if ($code === 'en') required aria-required="true" @endif
                        @error('name_'.$code) aria-invalid="true" aria-describedby="name_{{ $code }}-error" @enderror>
                    @error('name_'.$code)<span class="aw-error" id="name_{{ $code }}-error">{{ $message }}</span>@enderror
                </div>
            @endforeach

            @if ($isNew || ! $role->is_built_in)
                <div class="a-field">
                    <label class="a-label" for="base_role">{{ __('fa.roles_base') }} <span class="a-required">{{ __('fa.required') }}</span></label>
                    <select class="a-input" id="base_role" name="base_role" aria-describedby="base_role-help">
                        @foreach ($baseRoles as $base)
                            <option value="{{ $base->value }}" @selected(old('base_role', $role->base_role) === $base->value)>{{ $base->label() }}</option>
                        @endforeach
                    </select>
                    <span class="a-hint" id="base_role-help">{{ __('fa.roles_base_help') }}</span>
                </div>
            @endif
        </section>

        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('fa.roles_grid') }}</h2></div>
            <p class="a-hint">{{ __('fa.roles_grid_help') }}</p>
            <div class="a-tablewrap">
                <table class="a-table aw-table aw-grid">
                    <caption class="c-sr">{{ __('fa.roles_grid') }}</caption>
                    <thead><tr>
                        <th scope="col">{{ __('fa.roles_area') }}</th>
                        @foreach (\App\Support\RoleAreas::COLUMNS as $col)
                            <th scope="col">{{ __('fa.roles_col_'.$col) }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                    @foreach ($areas as $area => $cells)
                        <tr>
                            <th scope="row">{{ __('fa.roles_area_'.$area) }}</th>
                            @foreach (\App\Support\RoleAreas::COLUMNS as $col)
                                <td>
                                    @foreach ($cells[$col] ?? [] as $permission)
                                        @php $id = 'g-'.str_replace('.', '-', $permission->value); $val = old('grant.'.$permission->value, $grid[$permission->value] ?? 'denied'); @endphp
                                        <div class="aw-cell">
                                            <label for="{{ $id }}">{{ $permission->label() }}</label>
                                            <select class="a-input" id="{{ $id }}" name="grant[{{ $permission->value }}]">
                                                @foreach (['granted', 'conditional', 'denied'] as $g)
                                                    <option value="{{ $g }}" @selected($val === $g)>{{ __('fa.grant_'.$g) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="a-form__actions">
            <button type="submit" class="a-btn a-btn--primary">{{ $isNew ? __('fa.roles_create') : __('fa.roles_save') }}</button>
            <a href="{{ route('admin.roles', ['locale' => $locale]) }}">{{ __('admin.cancel') }}</a>
        </div>
    </form>
@endsection
