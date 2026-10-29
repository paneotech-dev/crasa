{{--
    One row of the builder.

    Screen 14 draws a handle, the label, the path and the languages. The
    handle is here and is decoration: reordering is the two buttons beside
    it, because drag needs a keyboard equivalent to meet SC 2.1.1 and a live
    region to meet 4.1.3, and a pair of buttons is both by construction. It
    also works with no script, which drag does not.

    A nested row is indented and names its parent, because indentation alone
    is a visual relationship and a reader using a screen reader gets none of
    it.

    The languages column is screen 14's flag: an item with an untranslated
    label shows the English on the translated site and is marked here rather
    than disappearing from the navigation. The broken-destination warning is
    the other, and the one that matters more: a menu item can outlive its
    destination, and the editor should meet that here rather than as a hole
    in the header.
--}}
@php
    $missing = $item->missingLocales();
    $broken = ! $item->destinationIsResolvable();
    $locale = app()->getLocale();
@endphp

<li class="a-menu__row" data-depth="{{ $depth }}">
    <span class="a-menu__handle" aria-hidden="true">
        <x-shell-icon name="navigation" :size="16" />
    </span>

    <span class="a-menu__main">
        <span class="a-menu__label">{{ $item->labelFor() }}</span>
        <span class="a-menu__path">
            @if ($depth > 0 && $item->parent)
                {{ __('admin.menu_under', ['parent' => $item->parent->labelFor()]) }},
            @endif
            @if ($item->hasDestination())
                {{ __('d3_i18n.label_value', ['label' => __('admin.menu_type_'.$item->destination_type), 'value' => $item->destination]) }}
            @else
                {{ __('admin.menu_heading_only') }}
            @endif
        </span>
    </span>

    <span class="a-menu__flags">
        @if ($broken)
            <span class="a-pill" data-status="rejected">{{ __('admin.menu_broken') }}</span>
        @endif
        @if ($missing !== [])
            <span class="a-pill" data-status="draft">{{ __('admin.menu_missing_languages', [
                'languages' => implode(', ', array_map(fn ($l) => strtoupper($l), $missing)),
            ]) }}</span>
        @endif
        <span class="a-pill" data-status="{{ $item->is_visible ? 'published' : 'archived' }}">
            {{ $item->is_visible ? __('admin.menu_visible_badge') : __('admin.menu_hidden_badge') }}
        </span>
    </span>

    <span class="a-menu__actions">
        {{-- Up and down rather than drag. Icon buttons, each with the item's
             own name in its accessible name: "Move Publications up" tells a
             reader which row they are on, which an arrow alone does not. --}}
        <form method="POST" action="{{ route('admin.menus.move', ['locale' => $locale, 'item' => $item->id]) }}">
            @csrf @method('PUT')
            <input type="hidden" name="direction" value="up">
            <button class="a-iconbtn" type="submit" aria-label="{{ __('admin.menu_move_up', ['label' => $item->labelFor()]) }}">
                <x-shell-icon name="arrow-up" :size="16" />
            </button>
        </form>
        <form method="POST" action="{{ route('admin.menus.move', ['locale' => $locale, 'item' => $item->id]) }}">
            @csrf @method('PUT')
            <input type="hidden" name="direction" value="down">
            <button class="a-iconbtn" type="submit" aria-label="{{ __('admin.menu_move_down', ['label' => $item->labelFor()]) }}">
                <x-shell-icon name="arrow-down" :size="16" />
            </button>
        </form>

        <a class="a-btn a-btn--secondary a-btn--small"
           href="{{ route('admin.menus', ['locale' => $locale, 'menu' => $item->menu, 'edit' => $item->id]) }}#menu-form">{{ __('admin.edit') }}</a>

        <form method="POST" action="{{ route('admin.menus.visibility', ['locale' => $locale, 'item' => $item->id]) }}">
            @csrf @method('PUT')
            <button class="a-btn a-btn--secondary a-btn--small" type="submit">{{ $item->is_visible ? __('admin.menu_hide') : __('admin.menu_show') }}</button>
        </form>

        {{-- Confirmed, and it names what it is about to remove. --}}
        <form method="POST" action="{{ route('admin.menus.destroy', ['locale' => $locale, 'item' => $item->id]) }}"
              data-confirm="{{ __('admin.menu_delete_confirm', ['label' => $item->labelFor()]) }}" data-confirm-title="{{ __('fa.confirm_title') }}">
            @csrf @method('DELETE')
            <button class="a-btn a-btn--danger a-btn--small" type="submit">{{ __('admin.delete') }}</button>
        </form>
    </span>
</li>
