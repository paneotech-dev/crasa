{{--
    The SADC region, drawn from Natural Earth geometry, as a navigation
    surface and as a choropleth.

    Two modes, exactly as RegionMap.dc.html carries them:

        mode: {"editor":"enum","options":["select","choropleth"],"default":"select"}

    In both, every member state is a link to its own entry. The screen's
    choropleth mode disables selection because there the map feeds a
    comparator and a shaded country is a figure rather than a destination.
    Here the map is the membership page's navigation, so the link stays live
    in both modes. That is the one departure from the screen's logic and it
    is a deliberate one: a map that stops being clickable when a reader
    shades it has taught them that shading breaks the page.

    ORDER. The fourteen are reachable by keyboard in alphabetical order by
    country name, not in the order the geometry file happens to list them.
    Alphabetical rather than geographic: it is the order the list below the
    map reads in, the order the comparator's country selector reads in, and
    the order every CRASA document lists member states in, so a reader
    tabbing the map and a reader reading the page are in the same place.
    Geographic order asks a reader to hold a map they may not have, and asks
    a screen reader user to hold one they certainly do not.

    Tab order is DOM order here — no positive tabindex anywhere, because
    focus order and visual order have to agree (SC 2.4.3). That is also why
    the two island chips come after the mainland twelve rather than being
    interleaved at C and M: they sit visually below the map, and focus that
    jumped out of the map and back into it would be worse for a sighted
    keyboard reader than a second alphabetical run. Within their own panel
    they are alphabetical too, and every other behaviour — link target,
    focus ring, hover detail, choropleth fill, accessible name — is the
    identical rule applied to the identical markup. An inset is a rendering
    decision, not a second class of member.

    NO SCRIPT IS REQUIRED. The dataset selector is a GET form, the shading
    is server-rendered fill classes, the detail panel is CSS :has(), and the
    table equivalent is a <details>. The script that ships alongside removes
    one click from the selector and nothing else.
--}}
@props(['countries', 'selection' => null, 'view' => null])
@php
    $map = config('region-map');
    $view = $view ?? \App\Support\MapView::resolve($countries, $selection ?? request()->query('indicator'));
    $locale = app()->getLocale();

    // Alphabetical, per the note above. The geometry is keyed by ISO code,
    // so the order the shapes are written in is entirely ours to choose.
    $ordered = $view->countries->keys()->all();
    $mainland = array_values(array_filter($ordered, fn ($iso) => ($map['members'][$iso]['d'] ?? null) !== null));
    $islands = array_values(array_filter($ordered, fn ($iso) => ($map['members'][$iso]['inset'] ?? null) !== null));
@endphp

<figure class="c-map-region {{ $view->isShaded() ? 'c-map-region--shaded' : 'c-map-region--plain' }}">
    <form class="c-map-region__control" method="get" action="{{ url()->current() }}" data-map-control>
        <label class="c-map-region__control-label" for="map-indicator">{{ __('members.map_shade_by') }}</label>
        <select class="c-map-region__select" id="map-indicator" name="indicator">
            <option value="">{{ __('members.map_option_none') }}</option>
            @foreach ($view->groups as $group)
                <optgroup label="{{ $group['label'] }}">
                    @foreach ($group['options'] as $option)
                        <option value="{{ $option['value'] }}" @selected($view->selection === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        {{-- Removed by public/js/filters.js once it has taken over the
             change event. Present and working for every reader who does
             not run it. --}}
        <button class="c-btn c-btn--secondary" type="submit" data-live-submit>{{ __('members.map_apply') }}</button>
    </form>

    <div class="c-map-region__stage">
        <svg
            class="c-map-region__svg"
            viewBox="0 0 {{ $map['width'] }} {{ $map['height'] }}"
            role="img"
            aria-label="{{ $view->isShaded() ? __('members.map_label_shaded', ['dataset' => $view->title()]) : __('members.map_label') }}"
            focusable="false"
        >
            {{-- "Not reported" as a fill. The screen states it as
                 MISSING_FILL = "repeating-linear-gradient(135deg,#E5E0D5 0 4px,#CFC8B8 4px 8px)"
                 which SVG cannot take, so it is the same two tones at the
                 same angle and the same 4px step as a pattern. It has to be
                 a texture and not a paler green: "not reported" and "lowest
                 value" are different claims about a member state. --}}
            <defs>
                <pattern id="c-map-hatch" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(135)">
                    <rect class="c-map-region__hatch-ground" width="8" height="8"></rect>
                    <rect class="c-map-region__hatch-rule" width="4" height="8"></rect>
                </pattern>
            </defs>

            {{-- Neighbouring states, so the region has a context rather than
                 floating. Not members, so not interactive and not labelled as
                 though they were. --}}
            @foreach ($map['context'] as $context)
                <path class="c-map-region__context" d="{{ $context['d'] }}" aria-hidden="true"></path>
            @endforeach

            @foreach ($mainland as $iso)
                @php
                    $entry = $view->entry($iso);
                    $country = $entry->country;
                    $shape = $map['members'][$iso];
                @endphp
                <a
                    class="c-map-region__member"
                    href="{{ route('members.show', ['locale' => $locale, 'iso' => $iso]) }}"
                    data-iso="{{ $iso }}"
                    data-band="{{ $entry->bandAttribute() }}"
                    aria-label="{{ $view->isShaded()
                        ? __('members.map_region_shaded', ['country' => $country->name, 'value' => $entry->display])
                        : __('members.map_region', ['country' => $country->name, 'authority' => $country->authority?->name ?: $country->name]) }}"
                >
                    <path d="{{ $shape['d'] }}"></path>
                </a>
            @endforeach

            {{-- The ISO code, and under it the figure. The screen puts the
                 value on the map itself — sub: choropleth ? v.display :
                 m.acr — so a reader quoting a member state into a minute
                 reads the number off the shape rather than counting shades
                 against a legend. Aria-hidden because the link above already
                 carries both in its accessible name; a screen reader that
                 read the shape and then the text would say it twice. --}}
            @foreach ($mainland as $iso)
                @php $shape = $map['members'][$iso]; @endphp
                @continue($shape['label'] === null)
                <text
                    class="c-map-region__code"
                    data-band="{{ $view->entry($iso)->bandAttribute() }}"
                    x="{{ $shape['label'][0] }}"
                    y="{{ $shape['label'][1] }}"
                    text-anchor="middle"
                    aria-hidden="true"
                >{{ strtoupper($iso) }}</text>
                <text
                    class="c-map-region__value"
                    data-band="{{ $view->entry($iso)->bandAttribute() }}"
                    x="{{ $shape['label'][0] }}"
                    y="{{ $shape['label'][1] + 14 }}"
                    text-anchor="middle"
                    aria-hidden="true"
                >{{ $view->entry($iso)->display }}</text>
            @endforeach

            @foreach ($map['context'] as $context)
                <text class="c-map-region__context-label" x="{{ $context['lx'] }}" y="{{ $context['ly'] }}"
                      text-anchor="middle" aria-hidden="true">{{ $context['name'] }}</text>
            @endforeach
        </svg>

        {{-- The detail panel. Hovering or focusing a member state reveals
             its regulator, acronym and mandated sectors here, in CSS, with
             no script: the stage matches :has([data-iso="bw"]:is(:hover,
             :focus-visible)) and shows the one card. The same selector
             matches an island chip, which is how the chips behave
             identically without a second implementation.

             Hidden from assistive technology on purpose. Every fact in it
             is already in the link's accessible name and in the list below,
             and fourteen stacked cards read aloud in sequence would be
             fourteen repetitions of the page. --}}
        <div class="c-map-region__detail" aria-hidden="true">
            <p class="c-map-region__prompt">{{ __('members.map_prompt') }}</p>
            @foreach ($ordered as $iso)
                @php $entry = $view->entry($iso); $country = $entry->country; @endphp
                <div class="c-map-region__card" data-for="{{ $iso }}">
                    <span class="c-map-region__card-country">{{ $country->name }}</span>
                    @if ($country->authority)
                        <span class="c-map-region__card-authority">{{ $country->authority->name }}</span>
                        @if ($country->authority->acronym)
                            <span class="c-map-region__card-acronym">{{ $country->authority->acronym }}</span>
                        @endif
                        @if ($country->authority->sectors->isNotEmpty())
                            <span class="c-map-region__card-sectors">{{ $country->authority->sectors->pluck('name')->implode(', ') }}</span>
                        @endif
                    @endif
                    @if ($view->isShaded())
                        <span class="c-map-region__card-value">{{ $view->title() }}: <strong>{{ $entry->display }}</strong></span>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- The two island members are far enough offshore that at region
             scale they would be specks. They get their own frame rather than
             being dropped, because a member state omitted from a membership
             map is a worse outcome than an inset. --}}
        <div class="c-map-region__insets">
            <h3 class="c-map-region__insets-heading">{{ __('members.map_islands') }}</h3>
            @foreach ($islands as $iso)
                @php $entry = $view->entry($iso); $country = $entry->country; @endphp
                <a
                    class="c-map-region__inset"
                    href="{{ route('members.show', ['locale' => $locale, 'iso' => $iso]) }}"
                    data-iso="{{ $iso }}"
                    data-band="{{ $entry->bandAttribute() }}"
                    aria-label="{{ $view->isShaded()
                        ? __('members.map_region_inset_shaded', ['country' => $country->name, 'value' => $entry->display])
                        : __('members.map_region_inset', ['country' => $country->name, 'authority' => $country->authority?->name ?: $country->name]) }}"
                >
                    <svg viewBox="0 0 120 100" aria-hidden="true" focusable="false">
                        <path d="{{ $map['members'][$iso]['inset'] }}"></path>
                    </svg>
                    <span class="c-map-region__inset-text">
                        <span class="c-map-region__inset-code">{{ strtoupper($iso) }}</span>
                        <span class="c-map-region__inset-detail">{{ $view->isShaded() ? $entry->display : $country->name }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- The legend, and only when there is something to key. Screen 03's
         choropleth legend is Low, four swatches, High, then a hatch swatch
         labelled "Not reported". --}}
    @if ($view->isShaded())
        <div class="c-map-region__legend">
            <p class="c-map-region__legend-title">
                {{ $view->title() }}@if ($view->unit()) <span class="c-map-region__legend-unit">{{ $view->unit() }}</span>@endif
            </p>
            <div class="c-map-region__scale">
                <span>{{ __('members.map_low') }}</span>
                <span class="c-map-region__swatch" data-band="0"></span>
                <span class="c-map-region__swatch" data-band="1"></span>
                <span class="c-map-region__swatch" data-band="2"></span>
                <span class="c-map-region__swatch" data-band="3"></span>
                <span>{{ __('members.map_high') }}</span>
                @if ($view->missingCount() > 0)
                    <span class="c-map-region__swatch c-map-region__swatch--missing" data-band="none"></span>
                    <span>{{ __('comparator.not_reported') }}</span>
                @endif
            </div>
            <p class="c-map-region__legend-note">
                @if ($view->isCompleteness())
                    {{ __('members.map_note_completeness') }}
                @else
                    {{ trans_choice('members.map_note_indicator', $view->missingCount(), [
                        'count' => $view->missingCount(),
                        'period' => $view->period ?: __('members.map_period_unknown'),
                    ]) }}
                @endif
            </p>
        </div>
    @endif

    {{-- The table equivalent. A choropleth read by a reader who cannot
         separate four greens, or who has printed the page, or who is
         looking at a washed-out projector, is a picture of nothing. This is
         the same fourteen figures in the same alphabetical order, one
         keystroke away and in the document whether it is open or not. --}}
    @if ($view->isShaded())
        <details class="c-map-region__table">
            <summary>{{ __('members.map_table_toggle') }}</summary>
            <div class="c-scroll">
                <table class="c-table">
                    <caption class="c-sr">{{ __('members.map_table_caption', ['dataset' => $view->title()]) }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('blocks.member_state') }}</th>
                            <th scope="col">{{ __('members.detail_acronym') }}</th>
                            <th scope="col">{{ $view->unit() ? $view->title().' ('.$view->unit().')' : $view->title() }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordered as $iso)
                            @php $entry = $view->entry($iso); @endphp
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('members.show', ['locale' => $locale, 'iso' => $iso]) }}">{{ $entry->country->name }}</a>
                                </th>
                                <td>{{ $entry->country->authority?->acronym }}</td>
                                <td @class(['c-map-region__cell--missing' => ! $entry->reported])>{{ $entry->display }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    <figcaption class="c-map-region__caption">{{ __('members.map_caption') }}</figcaption>
</figure>

@once
    @push('scripts')
        <script src="{{ asset('js/filters.js') }}" defer></script>
    @endpush
@endonce
