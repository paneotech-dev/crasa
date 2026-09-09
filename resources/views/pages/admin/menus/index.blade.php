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
    <nav class="c-filters" aria-label="{{ __('admin.menus') }}">
        <div class="c-filters__group">
            <div class="c-filters__options" role="list">
                @foreach ($menus as $name)
                    <a role="listitem" class="c-chip @if ($menu === $name) is-active @endif"
                       @if ($menu === $name) aria-current="true" @endif
                       href="{{ route('admin.menus', ['locale' => app()->getLocale(), 'menu' => $name]) }}">{{ __('admin.menu_'.$name) }}</a>
                @endforeach
            </div>
        </div>
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

    <section class="a-resource">
        <div class="a-resource__head">
            <h2 class="a-resource__title">{{ __('admin.menu_add') }}</h2>
        </div>

        <form class="a-form" method="POST" action="{{ route('admin.menus.store', ['locale' => app()->getLocale()]) }}">
            @csrf
            <input type="hidden" name="menu" value="{{ $menu }}">

            @foreach (\App\Support\Locales::SUPPORTED as $locale)
                <div class="s-field">
                    <label class="a-label" for="label-{{ $locale }}">
                        {{ __('admin.menu_label', ['language' => \App\Support\Locales::nameOf($locale)]) }}
                        @if ($locale === \App\Support\Locales::FALLBACK)<span aria-hidden="true">*</span>@endif
                    </label>
                    <input class="c-input" type="text" id="label-{{ $locale }}" name="label_{{ $locale }}"
                           @required($locale === \App\Support\Locales::FALLBACK) maxlength="120">
                    @if ($locale === \App\Support\Locales::FALLBACK)
                        <p class="s-field__hint">{{ __('admin.menu_label_required_hint') }}</p>
                    @endif
                </div>
            @endforeach

            <div class="s-field">
                <label class="a-label" for="parent">{{ __('admin.menu_parent') }}</label>
                <select class="c-select" id="parent" name="parent_id">
                    <option value="">{{ __('admin.menu_top_level') }}</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">{{ $item->labelFor() }}</option>
                    @endforeach
                </select>
                <p class="s-field__hint">{{ __('admin.menu_parent_hint') }}</p>
            </div>

            {{-- A destination is chosen, not typed. An editor picking from
                 the routes that exist cannot produce a link that 404s;
                 free text is kept for external addresses, which is what
                 free text is for. --}}
            <div class="s-field">
                <label class="a-label" for="destination_type">{{ __('admin.menu_destination') }}</label>
                <select class="c-select" id="destination_type" name="destination_type">
                    @foreach (\App\Models\MenuItem::TYPES as $type)
                        <option value="{{ $type }}">{{ __('admin.menu_type_'.$type) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="s-field">
                <label class="a-label" for="destination">{{ __('admin.menu_destination_value') }}</label>
                <input class="c-input" type="text" id="destination" name="destination" list="menu-destinations" maxlength="255">
                <datalist id="menu-destinations">
                    @foreach ($routes as $route)
                        <option value="{{ $route }}"></option>
                    @endforeach
                    @foreach ($pages as $page)
                        <option value="{{ $page->slug }}"></option>
                    @endforeach
                </datalist>
                <p class="s-field__hint">{{ __('admin.menu_destination_hint') }}</p>
            </div>

            <button class="a-btn a-btn--primary" type="submit">{{ __('admin.menu_add') }}</button>
        </form>
    </section>
@endsection
