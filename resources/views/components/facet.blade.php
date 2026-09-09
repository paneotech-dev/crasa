{{--
    One facet of a filter.

    Shared by the regulatory library, the newsroom and search. It began as a
    partial inside the library and moved here when the newsroom got counts:
    three hand-written copies of the same chip list is how one of them ends
    up with the count and the other two without.

    Each option carries how many publications it would return, computed with
    the other filters applied but not its own, so the number states what a
    click would actually yield.

    An option that would return nothing keeps its place and is shown as
    unavailable rather than removed. A reader who cannot see that Comoros
    has no publications cannot tell whether it has none or whether the
    filter is broken, and the shape of the data is part of the answer. That
    is also why the count is rendered for zero.
--}}
@props(['legend', 'field', 'active', 'options', 'counts' => []])

<div class="c-filters__group">
    <span class="c-filters__legend" id="facet-{{ $field }}">{{ $legend }}</span>
    <div class="c-filters__options" role="list" aria-labelledby="facet-{{ $field }}">
        <a role="listitem" class="c-chip @if (! $active) is-active @endif"
           @if (! $active) aria-current="true" @endif
           href="{{ request()->fullUrlWithQuery([$field => null, 'page' => null]) }}">{{ __('blocks.filter_all') }}</a>

        @foreach ($options as $option)
            @php
                $count = (int) ($counts[(string) $option['value']] ?? 0);
                $isActive = (string) $active === (string) $option['value'];
            @endphp

            @if ($count === 0 && ! $isActive)
                {{-- Not a link, because it goes nowhere useful, and not
                     hidden, because its absence is information the reader
                     needs. A span rather than a disabled anchor: there is no
                     disabled state for a link, and aria-disabled on
                     something still focusable is a promise the browser does
                     not keep. --}}
                <span role="listitem" class="c-chip is-empty">
                    {{ $option['label'] }}
                    <span class="c-chip__count">{{ $count }}</span>
                </span>
            @else
                <a role="listitem" class="c-chip @if ($isActive) is-active @endif"
                   @if ($isActive) aria-current="true" @endif
                   href="{{ request()->fullUrlWithQuery([$field => $option['value'], 'page' => null]) }}">
                    {{ $option['label'] }}
                    <span class="c-chip__count">{{ $count }}</span>
                </a>
            @endif
        @endforeach
    </div>
</div>
