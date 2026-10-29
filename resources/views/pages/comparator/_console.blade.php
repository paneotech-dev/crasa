{{--
    The three-step console (D2): indicator, member states, result. Every
    control is a link, so every state has a URL, works with no script and
    prints. comparator.js fetches the linked page and swaps this region.
--}}
@php
    $locale = app()->getLocale();
    $isBrowse = $comparison->mode === \App\Support\Comparison::BROWSE;
    $indicator = $comparison->indicator;
    $codes = $isBrowse ? collect() : $comparison->countries->pluck('iso_code');
    $nav = fn (array $params) => route('comparator', ['locale' => $locale] + $params);
    $sectorOrder = ['telecommunications', 'broadcasting', 'postal'];
    $groups = \App\Models\Indicator::query()->with('sector')->where('is_active', true)->orderBy('display_order')->get()
        ->groupBy(fn ($i) => $i->sector?->slug ?? 'other')
        ->sortBy(fn ($rows, $slug) => array_search($slug, $sectorOrder, true) === false ? 9 : array_search($slug, $sectorOrder, true));
    $states = \App\Models\Country::with('authorities')->orderBy('name')->get();
    $max = \App\Support\Comparison::MAX_COUNTRIES;
    $plain = fn ($slug) => trans()->has('d.plain.'.$slug) ? __('d.plain.'.$slug) : null;
@endphp

<div class="c-cmp__console">
    {{-- Step 1 --}}
    <section class="c-cmp__step c-cmp__step--1" aria-labelledby="cmp-step1">
        <h2 class="c-cmp__step-title" id="cmp-step1"><span class="c-cmp__step-n">{{ __('d.step_n', ['n' => 1]) }}</span> {{ __('d.step1') }}</h2>
        <div class="c-cmp__sectors">
            @foreach ($groups as $sector => $list)
                <div class="c-cmp__sector" data-sector="{{ $sector }}">
                    <h3 class="c-cmp__sector-title">{{ __('d.sector_'.$sector) }}</h3>
                    <ul class="c-cmp__picks">
                        @foreach ($list as $option)
                            @php [$short, $line] = $plain($option->slug) ?? [$option->name, $option->unitLabel()]; @endphp
                            <li>
                                <a class="c-cmp__pick" data-cmp-nav
                                   href="{{ $isBrowse ? $nav(['mode' => 'browse', 'indicator' => $option->slug]) : $nav(['mode' => 'compare', 'indicator' => $option->slug, 'countries' => $codes->implode(',')]) }}"
                                   @if ($indicator?->id === $option->id) aria-current="true" @endif>
                                    <span class="c-cmp__pick-name">{{ $short }}</span>
                                    <span class="c-cmp__pick-line">{{ $line }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Step 2 --}}
    <section class="c-cmp__step c-cmp__step--2" aria-labelledby="cmp-step2">
        <div class="c-cmp__step-head">
            <h2 class="c-cmp__step-title" id="cmp-step2"><span class="c-cmp__step-n">{{ __('d.step_n', ['n' => 2]) }}</span> {{ __('d.step2') }}</h2>
            <p class="c-cmp__step-help">
                {{ $isBrowse ? __('d.step2_help_browse') : __('d.step2_help') }}
                @unless ($isBrowse)
                    <span class="c-cmp__count">{{ __('d.chosen_count', ['n' => $codes->count()]) }}</span>
                @endunless
            </p>
        </div>
        <ul class="c-cmp__chips">
            <li>
                <a class="u-chip c-cmp__chip c-cmp__chip--all" data-cmp-nav
                   href="{{ $nav(['mode' => 'browse', 'indicator' => $indicator?->slug]) }}"
                   aria-label="{{ __('d.all_14_label') }}"
                   @if ($isBrowse) aria-current="true" @endif>{{ __('d.all_14') }}</a>
            </li>
            @foreach ($states as $state)
                @php
                    $selected = $codes->contains($state->iso_code);
                    $next = $selected ? $codes->reject(fn ($c) => $c === $state->iso_code) : $codes->concat([$state->iso_code]);
                    $atLimit = ! $selected && $codes->count() >= $max;
                @endphp
                <li>
                    @if ($atLimit)
                        <span class="u-chip c-cmp__chip" aria-disabled="true" title="{{ __('d.at_limit', ['state' => $state->name]) }}">
                            <x-flag :iso="$state->iso_code" />{{ $state->name }}
                        </span>
                    @else
                        <a class="u-chip c-cmp__chip" data-cmp-nav data-key="chip-{{ $state->iso_code }}"
                           href="{{ $nav(['mode' => 'compare', 'indicator' => $indicator?->slug, 'countries' => $next->implode(',')]) }}"
                           aria-label="{{ $selected ? __('d.remove_state', ['state' => $state->name]) : __('d.add_state', ['state' => $state->name]) }}"
                           @if ($selected) aria-current="true" @endif>
                            <x-flag :iso="$state->iso_code" />{{ $state->name }}
                            @if ($selected)<span class="c-cmp__chip-x" aria-hidden="true">&times;</span>@endif
                        </a>
                    @endif
                </li>
            @endforeach
            @if ($codes->isNotEmpty())
                <li><a class="c-cmp__clear" data-cmp-nav href="{{ $nav(['mode' => 'compare', 'indicator' => $indicator?->slug, 'countries' => '']) }}">{{ __('d.clear') }}</a></li>
            @endif
        </ul>
        <p class="c-cmp__shortcuts">
            <span>{{ __('d.shortcuts') }}</span>
            <a data-cmp-nav href="{{ $nav(['mode' => 'compare', 'indicator' => $indicator?->slug, 'countries' => 'bw,mz']) }}"
               @if (! $isBrowse && $codes->sort()->values()->all() === ['bw', 'mz']) aria-current="true" @endif>{{ __('comparator.preset_pair') }}</a>
            <a data-cmp-nav href="{{ $nav(['mode' => 'browse', 'indicator' => $indicator?->slug]) }}"
               @if ($isBrowse) aria-current="true" @endif>{{ __('comparator.mode_browse') }}</a>
        </p>
    </section>

    {{-- Step 3 --}}
    <section class="c-cmp__step c-cmp__step--3" aria-labelledby="cmp-step3" id="result">
        <h2 class="c-cmp__step-title" id="cmp-step3"><span class="c-cmp__step-n">{{ __('d.step_n', ['n' => 3]) }}</span> {{ __('d.step3') }}</h2>
        @if ($indicator === null || $comparison->countries->isEmpty())
            <div class="c-cmp__empty u-panel">
                <h3 class="u-h3">{{ __('d.empty_title') }}</h3>
                <p>{{ __('d.empty_body') }}</p>
                <p class="u-actions">
                    <a class="u-btn u-btn--primary" data-cmp-nav href="{{ $nav(['mode' => 'compare', 'indicator' => $indicator?->slug, 'countries' => 'bw,mz']) }}">{{ __('comparator.preset_pair') }}</a>
                    <a class="u-btn u-btn--secondary" data-cmp-nav href="{{ $nav(['mode' => 'browse', 'indicator' => $indicator?->slug]) }}">{{ __('d.all_14_label') }}</a>
                </p>
            </div>
        @else
            @include('pages.comparator._result', ['comparison' => $comparison])
        @endif
    </section>
</div>
