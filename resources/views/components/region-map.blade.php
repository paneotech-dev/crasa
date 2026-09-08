{{--
    The SADC region, drawn from Natural Earth geometry.

    This is the members page's one memorable moment. Fourteen countries on a
    map is inherently more informative than fourteen cards, and it is true to
    what CRASA actually is: a regional body whose membership is a shape, not
    a list.

    It is not decoration and it is not a picture. Every member state is a
    link to its own entry, labelled with its ISO code, so the map is a
    navigation surface. The list below it is not a fallback for the map —
    it is the same information for a reader who would rather read it, and it
    is where a screen reader is sent, because a screen reader gets nothing
    useful from fourteen SVG paths.
--}}
@props(['countries'])
@php
    $map = config('region-map');
    $byCode = $countries->keyBy('iso_code');
@endphp

<figure class="c-map-region">
    <svg
        class="c-map-region__svg"
        viewBox="0 0 {{ $map['width'] }} {{ $map['height'] }}"
        role="img"
        aria-label="{{ __('members.map_label') }}"
        focusable="false"
    >
        {{-- Neighbouring states, so the region has a context rather than
             floating. Not members, so not interactive and not labelled as
             though they were. --}}
        @foreach ($map['context'] as $context)
            <path class="c-map-region__context" d="{{ $context['d'] }}" aria-hidden="true"></path>
        @endforeach

        @foreach ($map['members'] as $iso => $shape)
            @continue($shape['d'] === null)
            <a
                class="c-map-region__member"
                href="#member-{{ $iso }}"
                data-iso="{{ $iso }}"
                aria-label="{{ $byCode[$iso]?->name ?? strtoupper($iso) }}"
            >
                <path d="{{ $shape['d'] }}"></path>
            </a>
        @endforeach

        {{-- ISO codes over each shape. Two characters is all that fits at
             this scale, and it is what a regulator reads anyway. --}}
        @foreach ($map['members'] as $iso => $shape)
            @continue($shape['label'] === null)
            <text
                class="c-map-region__code"
                x="{{ $shape['label'][0] }}"
                y="{{ $shape['label'][1] }}"
                text-anchor="middle"
                aria-hidden="true"
            >{{ strtoupper($iso) }}</text>
        @endforeach

        @foreach ($map['context'] as $context)
            <text class="c-map-region__context-label" x="{{ $context['lx'] }}" y="{{ $context['ly'] }}"
                  text-anchor="middle" aria-hidden="true">{{ $context['name'] }}</text>
        @endforeach
    </svg>

    {{-- The two island members are far enough offshore that at region scale
         they would be specks. They get their own frame rather than being
         dropped, because a member state omitted from a membership map is a
         worse outcome than an inset. --}}
    <div class="c-map-region__insets">
        @foreach ($map['members'] as $iso => $shape)
            @continue($shape['inset'] === null)
            <a class="c-map-region__inset" href="#member-{{ $iso }}">
                <svg viewBox="0 0 120 100" role="img" aria-label="{{ $byCode[$iso]?->name ?? strtoupper($iso) }}" focusable="false">
                    <path d="{{ $shape['inset'] }}"></path>
                </svg>
                <span class="c-map-region__inset-code">{{ strtoupper($iso) }}</span>
            </a>
        @endforeach
    </div>

    <figcaption class="c-map-region__caption">{{ __('members.map_caption') }}</figcaption>
</figure>
