{{--
    Home comparison console (C8): selects and table on the left, an
    animated bar chart on the right, the source and the reading note under
    it, and "Open these in the comparator". Self-contained: it reads its own
    data, pushes its own stylesheet and script, and needs no page block.

    The floor is a GET form: with no script, "Show the comparison" reloads
    the page at #home-compare with the choice applied. With home-compare.js
    every change of any select fetches the result alone
    (home/comparison?view=console) and swaps the table and the chart, and
    the status line announces the new comparison.
--}}
@php
    $cmp = \App\Support\BlockData::comparison(null);
@endphp
@if ($cmp['indicator'] !== null && $cmp['rows']->isNotEmpty())
<section class="c-hcmp" id="home-compare" aria-labelledby="home-compare-title">
    <div class="u-container">
        <div class="c-hcmp__head">
            <p class="u-eyebrow">{{ __('d.eyebrow') }}</p>
            <h2 class="u-h2" id="home-compare-title">{{ __('d.home_heading') }}</h2>
            <p class="c-hcmp__intro">{{ __('d.home_intro') }}</p>
        </div>

        <div class="c-hcmp__console" data-home-compare
             data-fragment="{{ route('home.comparison', ['locale' => app()->getLocale(), 'view' => 'console']) }}">
            <div class="c-hcmp__left">
                <form class="c-hcmp__form" method="get" action="{{ url()->current() }}#home-compare">
                    <div class="u-field c-hcmp__field c-hcmp__field--wide">
                        <label for="hcmp-indicator">{{ __('d.home_indicator') }}</label>
                        <select class="u-select" id="hcmp-indicator" name="cmp_indicator">
                            @foreach ($cmp['choices']->groupBy(fn ($i) => $i->sector?->slug ?? 'other') as $sector => $list)
                                <optgroup label="{{ __('d.sector_'.$sector) }}">
                                    @foreach ($list as $choice)
                                        <option value="{{ $choice->slug }}" @selected($cmp['selected']['indicator'] === $choice->slug)>{{ trans()->has('d.plain.'.$choice->slug) ? __('d.plain.'.$choice->slug)[0] : $choice->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    @foreach (['a', 'b'] as $slot)
                        <div class="u-field c-hcmp__field">
                            <label for="hcmp-{{ $slot }}">{{ __('d.home_state_'.$slot) }}</label>
                            <select class="u-select" id="hcmp-{{ $slot }}" name="cmp_{{ $slot }}">
                                @foreach ($cmp['states'] as $state)
                                    <option value="{{ $state->iso_code }}" @selected($cmp['selected'][$slot] === $state->iso_code)>{{ $state->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <button class="u-btn u-btn--primary c-hcmp__apply" type="submit" data-cmp-submit>{{ __('d.home_apply') }}</button>
                </form>

                <p class="c-sr" role="status" aria-live="polite" data-hcmp-status></p>

                <div data-cmp-slot="table">
                    @include('partials.home._comparator-result', $cmp + ['part' => 'table'])
                </div>
            </div>

            <div class="c-hcmp__right" data-cmp-slot="chart">
                @include('partials.home._comparator-result', $cmp + ['part' => 'chart'])
            </div>
        </div>
    </div>
</section>

@once
    @push('styles')
        <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/comparator.css') }}">
    @endpush
    @push('scripts')
        <script src="{{ \App\Support\Assets::url('js/home-compare.js') }}" defer></script>
    @endpush
@endonce
@endif
