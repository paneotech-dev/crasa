{{--
    One group of facet options. Screen 07 lines 128 to 178.

        heading  font-size:12px; font-weight:700; color:var(--c-navy);
                 margin:0 0 var(--s-3)
        options  display:flex; flex-direction:column; gap:11px
        row      display:flex; align-items:center; gap:10px; font-size:15.5px
        box      20px, --r-xs, 1.5px border, --c-green-dark when checked
        count    var(--f-admin); 13.5px; var(--c-slate)

    TWO DEPARTURES FROM THE DRAWING, BOTH DELIBERATE.

    The heading is sentence case. Screen 07 draws it tracked and uppercase;
    Question 6 was ruled on 9 September 2026 and the footer column headings
    are the only carve-out on the platform. Size, weight, colour and spacing
    are the screen's.

    The box border is --c-border rather than the screen's #B9B2A2, which is
    2.1:1 on white and fails SC 1.4.11 for the boundary of a component. WCAG
    2.1 AA is a contractual acceptance criterion, so the standard wins.

    An option that would return nothing keeps its place and is disabled
    rather than removed. A reader who cannot see that Comoros has no
    publications cannot tell whether it has none or whether the filter is
    broken, and the shape of the data is part of the answer. That is also
    why the count is rendered for zero.
--}}
@props(['legend', 'field', 'options', 'counts' => [], 'selected' => [], 'bars' => false])

@php
    $selected = array_map('strval', $selected);
    $peak = max(1, ...array_map('intval', array_values($counts) ?: [1]));
@endphp

<fieldset class="c-facets__group">
    <legend class="c-facets__legend">{{ $legend }}</legend>

    <div @class(['c-facets__options', 'c-facets__options--bars' => $bars])>
        @foreach ($options as $option)
            @php
                $value = (string) $option['value'];
                $count = (int) ($counts[$value] ?? 0);
                $isOn = in_array($value, $selected, true);
            @endphp

            {{-- The label wraps the input, which associates them on its
                 own, and it also carries for/id. Belt and braces on
                 purpose: the platform's accessibility guard reads the
                 markup rather than building an accessibility tree, and a
                 rule that only implicit labelling satisfies is a rule the
                 next person removes by accident. --}}
            <label @class(['c-facet', 'is-empty' => $count === 0 && ! $isOn])
                   for="facet-{{ $field }}-{{ \Illuminate\Support\Str::slug($value) }}">
                <input type="checkbox"
                       id="facet-{{ $field }}-{{ \Illuminate\Support\Str::slug($value) }}"
                       name="{{ $field }}[]"
                       value="{{ $value }}"
                       @checked($isOn)
                       @disabled($count === 0 && ! $isOn)>
                <span class="c-facet__name">{{ $option['label'] }}</span>

                @if ($bars)
                    {{-- Screen 07 line 152: a --c-sand track with the
                         proportion filled, so the shape of the archive is
                         readable without reading every number. --}}
                    <span class="c-facet__track" aria-hidden="true">
                        <span class="c-facet__bar" style="width:{{ max(2, (int) round($count / $peak * 100)) }}%"></span>
                    </span>
                @endif

                <span class="c-facet__count">{{ $count }}</span>
            </label>
        @endforeach
    </div>
</fieldset>
