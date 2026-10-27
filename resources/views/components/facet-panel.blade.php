{{--
    Screen 07's filter sidebar, line 115.

        aside  flex:1 1 270px; max-width:320px; min-width:0;
               display:flex; flex-direction:column; gap:var(--s-5)

    Two cards. "Active filters" carries what is on and a way to take each
    one off; the facet card carries every option with the number of results
    a click would produce. Both are drawn on white with a --c-line rule and
    --r-md, per lines 116 and 127.

    THE FLOOR IS A FORM. Every checkbox is a real checkbox in a GET form
    with a real submit button, so the sidebar filters and deep-links with no
    script at all. public/js/filters.js applies on change and removes the
    button, and it removes it only after its listeners are attached, so a
    script that throws on the way down leaves a working form behind.

    Props: action, the form target. facets, a list of groups. filters, the
    current selection keyed by field. clear, the URL that removes
    everything. label, the accessible name of the region.
--}}
@props(['action', 'facets', 'filters', 'clear', 'label'])

@php
    $active = collect($facets)
        ->flatMap(function ($facet) use ($filters) {
            $selected = $filters[$facet['field']] ?? [];

            return collect($facet['options'])
                ->filter(fn ($option) => in_array((string) $option['value'], array_map('strval', $selected), true))
                ->map(fn ($option) => [
                    'legend' => $facet['legend'],
                    'label' => $option['label'],
                    'url' => request()->fullUrlWithQuery(
                        \App\Support\Facets::toggled($filters, $facet['field'], (string) $option['value'])
                    ),
                ]);
        })
        ->values();
@endphp

<form class="c-library__side" method="GET" action="{{ $action }}" aria-label="{{ $label }}">
    @if ($active->isNotEmpty())
        <div class="c-panel">
            <div class="c-active__head">
                <h2>{{ __('publications.active_filters') }}</h2>
                <a href="{{ $clear }}">{{ __('publications.clear_all') }}</a>
            </div>

            <div class="c-active__list">
                @foreach ($active as $chip)
                    {{-- A link, not a button in the form: it has to work
                         before the script runs, and removing one filter is
                         a different request rather than a form submission. --}}
                    <a class="c-active__chip" href="{{ $chip['url'] }}">
                        <span>{{ $chip['legend'] }}: {{ $chip['label'] }}</span>
                        <span class="c-active__x" aria-hidden="true">&times;</span>
                        <span class="c-sr">{{ __('publications.remove_filter') }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="c-panel c-facets">
        {{-- Extra controls a page puts inside the same form (the newsroom's
             keyword, year and order), so they travel in the same URL. --}}
        {{ $slot ?? '' }}

        {{-- A facet with no options at all is a heading over nothing. A
             library with no committees does not need a committee heading
             telling the reader so. --}}
        @foreach (collect($facets)->filter(fn ($facet) => count($facet['options']) > 0) as $facet)
            <x-facet-group
                :legend="$facet['legend']"
                :field="$facet['field']"
                :options="$facet['options']"
                :counts="$facet['counts']"
                :selected="$filters[$facet['field']] ?? []"
                :bars="$facet['bars'] ?? false"
            />
        @endforeach

        {{-- Removed by filters.js once its listeners are attached. Until
             then, and for ever if the script never arrives, this is how the
             sidebar is applied. --}}
        <div class="c-facets__apply">
            <button type="submit" class="c-btn c-btn--primary" data-live-submit>{{ __('publications.apply_filters') }}</button>
        </div>
    </div>
</form>
