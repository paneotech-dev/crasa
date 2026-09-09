{{--
    The navigation builder, screen 14.

      <h2>Navigation builder</h2>
      <div>Drag to reorder. Labels are edited per language.</div>
      ... each row: a handle, the label, the path, the languages ...
      "A menu item with an untranslated label shows the English text on the
       translated site and is flagged here, rather than disappearing from
       the navigation."

    Reordering is up and down buttons rather than drag. Drag needs a
    keyboard equivalent to meet SC 2.1.1 and a live region to meet 4.1.3, and
    a pair of buttons is both of those by construction. It also works with no
    script at all, which drag does not.

    The flag the screen asks for is the languages column, and the broken
    destination warning beside it: an editor should meet a dead menu item
    here rather than as a hole in the header.
--}}
@extends('layouts.admin')

@section('title', __('admin.menus'))
@section('note', __('admin.menus_note'))

@section('crumbs')
    <li><a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('admin.dashboard') }}</a></li>
    <li aria-current="page">{{ __('admin.menus') }}</li>
@endsection

@section('content')
    {{-- Two menus, two tabs. Chips are a filter and these are not a
         filter: they are two separate menus, and a tab says so. --}}
    <nav class="a-tabs" aria-label="{{ __('admin.menus') }}">
        @foreach ($menus as $name)
            <a href="{{ route('admin.menus', ['locale' => app()->getLocale(), 'menu' => $name]) }}"
               @if ($menu === $name) aria-current="page" @endif>{{ __('admin.menu_'.$name) }}</a>
        @endforeach
    </nav>

    <section class="a-resource">
        <div class="a-resource__head">
            <h2 class="a-resource__title">{{ __('admin.menu_'.$menu) }}</h2>
            <div class="a-resource__note">{{ __('admin.menu_order_note') }}</div>
        </div>

        @if ($items->isEmpty())
            <div class="a-empty">
                <p class="a-empty__title">{{ __('admin.menu_empty_title') }}</p>
                <p class="a-empty__body">{{ __('admin.menu_empty_body') }}</p>
            </div>
        @else
            <ul class="a-menu" role="list">
                @foreach ($items as $item)
                    @include('pages.admin.menus._row', ['item' => $item, 'depth' => 0])

                    @foreach ($item->children as $child)
                        @include('pages.admin.menus._row', ['item' => $child, 'depth' => 1])
                    @endforeach
                @endforeach
            </ul>
        @endif
    </section>

    {{-- The add form, on the administration's own components.

         It used to mix .c-input, drawn for the public site, with .s-field,
         drawn for the submission e-Form, inside an admin panel, and came
         out looking like a browser default with a full-width green button
         under it. One pattern now: .a-form in a card, labels above their
         controls, actions bottom right. --}}
    <section class="a-card" id="menu-form">
        <div class="a-card__head">
            <h2>{{ $editing ? __('admin.menu_edit') : __('admin.menu_add') }}</h2>
            @if ($editing)
                <a class="a-card__meta" href="{{ route('admin.menus', ['locale' => app()->getLocale(), 'menu' => $menu]) }}">{{ __('admin.menu_cancel_edit') }}</a>
            @endif
        </div>

        <form class="a-form" method="POST" action="{{ $editing
            ? route('admin.menus.update', ['locale' => app()->getLocale(), 'item' => $editing->id])
            : route('admin.menus.store', ['locale' => app()->getLocale()]) }}">
            @csrf
            @if ($editing) @method('PUT') @endif
            <input type="hidden" name="menu" value="{{ $menu }}">

            {{-- One question asked three times, so one heading over the
                 three of them rather than three unrelated fields. --}}
            <fieldset class="a-fieldset">
                <legend>{{ __('admin.menu_labels') }}</legend>

                @foreach (\App\Support\Locales::SUPPORTED as $locale)
                    @php $required = $locale === \App\Support\Locales::FALLBACK; @endphp

                    <div class="a-field">
                        <label class="a-label" for="label-{{ $locale }}">
                            {{ \App\Support\Locales::nameOf($locale) }}
                            @if ($required)
                                <span class="a-required" aria-hidden="true">*</span>
                                <span class="c-sr">{{ __('admin.required') }}</span>
                            @endif
                        </label>
                        <input class="a-input" type="text" id="label-{{ $locale }}" name="label_{{ $locale }}"
                               value="{{ old('label_'.$locale, $editing?->labelFor($locale) ?? '') }}"
                               @required($required) maxlength="120"
                               @error('label_'.$locale) aria-describedby="label-{{ $locale }}-error" @enderror>
                        @if ($required)
                            <p class="a-hint">{{ __('admin.menu_label_required_hint') }}</p>
                        @endif
                        @error('label_'.$locale)<p class="c-field__error" id="label-{{ $locale }}-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </fieldset>

            <div class="a-field">
                <label class="a-label" for="parent">{{ __('admin.menu_parent') }}</label>
                <select class="a-select" id="parent" name="parent_id">
                    <option value="">{{ __('admin.menu_top_level') }}</option>
                    @foreach ($items as $candidate)
                        @continue($editing && $candidate->id === $editing->id)
                        <option value="{{ $candidate->id }}" @selected((int) old('parent_id', $editing?->parent_id) === $candidate->id)>{{ $candidate->labelFor() }}</option>
                    @endforeach
                </select>
                <p class="a-hint">{{ __('admin.menu_parent_hint') }}</p>
            </div>

            {{-- A destination is chosen, not typed. An editor picking from
                 the routes that exist cannot produce a link that 404s;
                 free text is kept for external addresses, which is what
                 free text is for. --}}
            <div class="a-field">
                <label class="a-label" for="destination_type">
                    {{ __('admin.menu_destination') }}
                    <span class="a-required" aria-hidden="true">*</span>
                    <span class="c-sr">{{ __('admin.required') }}</span>
                </label>
                <select class="a-select" id="destination_type" name="destination_type" required>
                    @foreach (\App\Models\MenuItem::TYPES as $type)
                        <option value="{{ $type }}" @selected(old('destination_type', $editing?->destination_type) === $type)>{{ __('admin.menu_type_'.$type) }}</option>
                    @endforeach
                </select>
                @error('destination_type')<p class="c-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="a-field">
                <label class="a-label" for="destination">{{ __('admin.menu_destination_value') }}</label>
                <input class="a-input" type="text" id="destination" name="destination" list="menu-destinations"
                       value="{{ old('destination', $editing?->destination) }}" maxlength="255">
                <datalist id="menu-destinations">
                    @foreach ($routes as $route)
                        <option value="{{ $route }}"></option>
                    @endforeach
                    @foreach ($pages as $page)
                        <option value="{{ $page->slug }}"></option>
                    @endforeach
                </datalist>
                <p class="a-hint">{{ __('admin.menu_destination_hint') }}</p>
                @error('destination')<p class="c-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="a-form__actions">
                @if ($editing)
                    <a class="a-btn a-btn--secondary" href="{{ route('admin.menus', ['locale' => app()->getLocale(), 'menu' => $menu]) }}">{{ __('admin.cancel') }}</a>
                @endif
                <button class="a-btn a-btn--primary" type="submit">{{ $editing ? __('admin.menu_save') : __('admin.menu_add') }}</button>
            </div>
        </form>
    </section>@endsection
