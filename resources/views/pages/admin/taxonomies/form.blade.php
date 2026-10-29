@extends('layouts.admin')

@php
    $isNew = ! $term->exists;
    $heading = $isNew ? __('d3_taxo.new_'.$kind) : \App\Support\Taxonomies::label($kind, $term);
@endphp

@section('title', $heading)
@section('note', __('d3_taxo.kind_'.$kind))

@section('crumbs')
    <li><a href="{{ route('admin.taxonomies', ['kind' => $kind]) }}">{{ __('d3_taxo.title') }}</a></li>
    <li aria-current="page">{{ $heading }}</li>
@endsection

@section('content')
    @include('pages.admin.taxonomies._styles')

    <form class="a-form a-form--card" method="POST"
          action="{{ $isNew ? route('admin.taxonomies.store', ['kind' => $kind]) : route('admin.taxonomies.update', ['kind' => $kind, 'id' => $term->getKey()]) }}">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <fieldset class="a-fieldset">
            <legend class="a-label">{{ __('d3_taxo.field_names') }}</legend>
            @foreach (\App\Support\Locales::SUPPORTED as $locale)
                <div @class(['a-field', 'a-field--error' => $errors->has('name.'.$locale)])>
                    <label class="a-label" for="name-{{ $locale }}">{{ __('d3_taxo.field_name_in', ['language' => \App\Support\Locales::nameOf($locale)]) }} <span class="a-required">{{ __('admin.required') }}</span></label>
                    <input class="a-input" type="text" id="name-{{ $locale }}" name="name[{{ $locale }}]" lang="{{ $locale }}" maxlength="80" required
                           value="{{ old('name.'.$locale, $names[$locale] ?? '') }}"
                           @error('name.'.$locale) aria-invalid="true" aria-describedby="name-{{ $locale }}-error" @enderror>
                    @error('name.'.$locale)<p class="c-field__error" id="name-{{ $locale }}-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </fieldset>

        <div @class(['a-field', 'a-field--error' => $errors->has('slug')])>
            <label class="a-label" for="slug">{{ __('d3_taxo.field_slug') }} @unless ($slugLocked)<span class="a-required">{{ __('admin.required') }}</span>@endunless</label>
            @if ($slugLocked)
                <p class="a-readonly" id="slug"><code>{{ $term->slug }}</code></p>
                <p class="a-hint">{{ __('d3_taxo.slug_locked') }}</p>
            @else
                <input class="a-input" type="text" id="slug" name="slug" required maxlength="{{ \App\Support\Taxonomies::KINDS[$kind]['slug_max'] }}"
                       pattern="[a-z0-9]+([_\-][a-z0-9]+)*" autocomplete="off" spellcheck="false"
                       aria-describedby="slug-help @error('slug') slug-error @enderror"
                       value="{{ old('slug', $term->slug) }}">
                <p class="a-hint" id="slug-help">{{ $isNew ? __('d3_taxo.slug_help') : __('d3_taxo.slug_help_existing') }}</p>
                @error('slug')<p class="c-field__error" id="slug-error">{{ $message }}</p>@enderror
            @endif
        </div>

        <div @class(['a-field', 'a-field--error' => $errors->has('sort_order')])>
            <label class="a-label" for="sort_order">{{ __('d3_taxo.field_order') }} <span class="a-required">{{ __('admin.required') }}</span></label>
            <input class="a-input" type="number" id="sort_order" name="sort_order" min="0" max="999" required inputmode="numeric"
                   aria-describedby="order-help" value="{{ old('sort_order', $term->sort_order ?? 0) }}">
            <p class="a-hint" id="order-help">{{ __('d3_taxo.order_help') }}</p>
            @error('sort_order')<p class="c-field__error">{{ $message }}</p>@enderror
        </div>

        @if ($colours !== [])
            <fieldset @class(['a-field', 'tx-colours', 'a-field--error' => $errors->has('colour')]) aria-describedby="colour-help">
                <legend class="a-label">{{ __('d3_taxo.field_colour') }} <span class="a-required">{{ __('admin.required') }}</span></legend>
                <p class="a-hint" id="colour-help">{{ __('d3_taxo.colour_help') }}</p>
                @foreach ($colours as $colour)
                    <label for="colour-{{ $colour }}">
                        <input type="radio" id="colour-{{ $colour }}" name="colour" value="{{ $colour }}" required
                               @checked(old('colour', $term->colour ?? 'navy') === $colour)>
                        <span class="tx-badge" style="{{ \App\Support\BadgeColours::style($colour) }}">{{ $names['en'] ?: __('d3_taxo.colour_'.$colour) }}</span>
                        <span>{{ __('d3_taxo.colour_'.$colour) }}, {{ __('d3_taxo.contrast', ['ratio' => number_format(\App\Support\BadgeColours::contrast($colour), 1)]) }}</span>
                    </label>
                @endforeach
                @error('colour')<p class="c-field__error">{{ $message }}</p>@enderror
            </fieldset>
        @endif

        <div class="a-form__actions">
            <button type="submit" class="a-btn a-btn--primary">{{ $isNew ? __('d3_taxo.create') : __('d3_taxo.save') }}</button>
            <a href="{{ route('admin.taxonomies', ['kind' => $kind]) }}">{{ __('admin.cancel') }}</a>
        </div>
    </form>

    @unless ($isNew)
        <section class="a-card" id="delete">
            <div class="a-card__head"><h2>{{ __('d3_taxo.delete_title') }}</h2></div>
            @if ($usage > 0)
                {{-- Said before the button is pressed, not only after. --}}
                <p class="a-hint">{{ trans_choice('d3_taxo.in_use', $usage, ['name' => \App\Support\Taxonomies::label($kind, $term), 'count' => $usage]) }}</p>
            @else
                <form method="POST" action="{{ route('admin.taxonomies.destroy', ['kind' => $kind, 'id' => $term->getKey()]) }}"
                      data-confirm="{{ __('d3_taxo.delete_confirm', ['name' => \App\Support\Taxonomies::label($kind, $term)]) }}"
                      data-confirm-title="{{ __('fa.confirm_title') }}" data-confirm-action="{{ __('d3_taxo.delete') }}">
                    @csrf
                    @method('DELETE')
                    <p class="a-hint">{{ __('d3_taxo.delete_unused') }}</p>
                    <div class="a-form__actions">
                        <button type="submit" class="a-btn a-btn--danger">{{ __('d3_taxo.delete') }}</button>
                    </div>
                </form>
            @endif
        </section>
    @endunless
@endsection
