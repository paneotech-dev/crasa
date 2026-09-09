{{--
    One row of the builder.

    The languages column is screen 14's flag: an item with an untranslated
    label shows the English text on the translated site and is marked here,
    rather than disappearing from the navigation.

    The broken-destination warning is the other flag, and the one that
    matters more: a menu item can outlive its destination, and the editor
    should meet that here rather than as a hole in the header.
--}}
@php
    $missing = $item->missingLocales();
    $broken = ! $item->destinationIsResolvable();
@endphp

<li class="a-menu__row" data-depth="{{ $depth }}">
    <span class="a-menu__main">
        <span class="a-menu__label">{{ $item->labelFor() }}</span>
        <span class="a-menu__path">
            @if ($item->hasDestination())
                {{ __('admin.menu_type_'.$item->destination_type) }}: {{ $item->destination }}
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
        @unless ($item->is_visible)
            <span class="a-pill" data-status="archived">{{ __('admin.menu_hidden_badge') }}</span>
        @endunless
    </span>

    <span class="a-menu__actions">
        {{-- Up and down rather than drag: a pair of buttons is keyboard
             operable and announces its result by reloading, which drag has
             to be given separately and usually is not. --}}
        <form method="POST" action="{{ route('admin.menus.move', ['locale' => app()->getLocale(), 'item' => $item->id]) }}">
            @csrf @method('PUT')
            <input type="hidden" name="direction" value="up">
            <button class="a-btn" type="submit" aria-label="{{ __('admin.menu_move_up', ['label' => $item->labelFor()]) }}">&uarr;</button>
        </form>
        <form method="POST" action="{{ route('admin.menus.move', ['locale' => app()->getLocale(), 'item' => $item->id]) }}">
            @csrf @method('PUT')
            <input type="hidden" name="direction" value="down">
            <button class="a-btn" type="submit" aria-label="{{ __('admin.menu_move_down', ['label' => $item->labelFor()]) }}">&darr;</button>
        </form>
        <form method="POST" action="{{ route('admin.menus.visibility', ['locale' => app()->getLocale(), 'item' => $item->id]) }}">
            @csrf @method('PUT')
            <button class="a-btn" type="submit">{{ $item->is_visible ? __('admin.menu_hide') : __('admin.menu_show') }}</button>
        </form>
        <form method="POST" action="{{ route('admin.menus.destroy', ['locale' => app()->getLocale(), 'item' => $item->id]) }}">
            @csrf @method('DELETE')
            <button class="a-btn a-btn--danger" type="submit">{{ __('admin.delete') }}</button>
        </form>
    </span>
</li>
