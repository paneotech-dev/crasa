@extends('layouts.admin')
@php $locale = app()->getLocale(); $names = ['en' => 'English', 'fr' => 'Français', 'pt' => 'Português']; @endphp
@section('title', __('fa.page_new'))
@section('note', __('fa.page_new_note'))

@section('crumbs')
    <li><a href="{{ route('admin.pages', ['locale' => $locale]) }}">{{ __('admin.pages_title') }}</a></li>
    <li aria-current="page">{{ __('fa.page_new') }}</li>
@endsection

@section('content')
    <form class="a-form" method="POST" action="{{ route('admin.pages.store', ['locale' => $locale]) }}" novalidate data-new-page>
        @csrf
        @if ($errors->any())
            <div class="a-notice a-notice--error" role="alert">
                <strong>{{ __('fa.form_errors') }}</strong>
                <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('fa.page_template') }}</h2></div>
            <p><strong>{{ __('fa.page_template_standard') }}</strong>. {{ __('fa.page_template_help') }}</p>
        </section>

        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('fa.page_titles') }}</h2></div>
            @foreach ($locales as $code)
                @php $key = $code.'.title'; @endphp
                <div @class(['a-field', 'aw-field--error' => $errors->has($key)])>
                    <label class="a-label" for="title-{{ $code }}">{{ __('fa.page_title_in', ['language' => $names[$code] ?? $code]) }}
                        @if ($code === 'en')<span class="a-required">{{ __('fa.required') }}</span>@endif
                    </label>
                    <input class="a-input" id="title-{{ $code }}" name="{{ $code }}[title]" value="{{ old($key) }}" maxlength="255"
                        @if ($code === 'en') required aria-required="true" data-title-source @endif
                        @error($key) aria-invalid="true" aria-describedby="title-{{ $code }}-error" @enderror>
                    @error($key)<span class="aw-error" id="title-{{ $code }}-error">{{ $message }}</span>@enderror
                </div>
            @endforeach
        </section>

        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('fa.page_address') }}</h2></div>
            <div @class(['a-field', 'aw-field--error' => $errors->has('slug')])>
                <label class="a-label" for="slug">{{ __('fa.page_address_label') }}</label>
                <div class="aw-slug"><span aria-hidden="true">{{ url($locale) }}/</span><input class="a-input" id="slug" name="slug" value="{{ old('slug') }}" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" aria-describedby="slug-help @error('slug') slug-error @enderror" data-slug @error('slug') aria-invalid="true" @enderror></div>
                <span class="a-hint" id="slug-help">{{ __('fa.page_address_help') }}</span>
                @error('slug')<span class="aw-error" id="slug-error">{{ $message }}</span>@enderror
            </div>
        </section>

        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('fa.page_menu') }}</h2></div>
            <fieldset class="a-fieldset">
                <legend class="a-label">{{ __('fa.page_menu_label') }}</legend>
                @foreach (array_merge([''], $menus) as $menu)
                    <label class="a-check">
                        <input type="radio" name="menu" value="{{ $menu }}" @checked(old('menu', '') === $menu)>
                        <span class="a-check__label">{{ __('fa.page_menu_'.($menu === '' ? 'none' : $menu)) }}</span>
                    </label>
                @endforeach
                <span class="a-hint">{{ __('fa.page_menu_help') }}</span>
            </fieldset>
        </section>

        <div class="a-form__actions">
            <button type="submit" class="a-btn a-btn--primary">{{ __('fa.page_create') }}</button>
            <a href="{{ route('admin.pages', ['locale' => $locale]) }}">{{ __('admin.cancel') }}</a>
        </div>
    </form>
@endsection
