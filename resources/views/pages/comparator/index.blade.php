@extends('layouts.public')

@section('title', __('comparator.title'))
@section('description', __('comparator.meta_description'))

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/comparator.css') }}">
@endpush

@section('content')
    @php
        $cells = $comparison->cells();
        $isBrowse = $comparison->mode === \App\Support\Comparison::BROWSE;
        $nav = fn (array $params) => route('comparator', ['locale' => app()->getLocale()] + $params);
    @endphp

    {{-- Screen 03 hero: navy band, h1 at clamp(31px,5.2vw,52px), lead at
         19px in --c-sand. --}}
    <section class="c-cmp__masthead">
        <div class="c-page c-cmp__masthead-inner">
            <div class="c-cmp__intro">
                <h1>{{ __('comparator.heading') }}</h1>
                <p class="c-cmp__lead">{{ __('comparator.lead', [
                    'indicators' => \App\Models\Indicator::where('is_active', true)->count(),
                    'categories' => count($categories),
                ]) }}</p>
            </div>

            {{-- The demonstration notice was here. Screen 03 draws it and
                 says it "remains in place, on screen and in every export,
                 until each value is confirmed by its national regulator".
                 Removed on a ruling of 9 September 2026: sample data is
                 flagged in the database and disclosed verbally by the
                 presenter, not badged on the page. The is_sample flags and
                 the purge command are untouched, and the admin dashboard
                 carries the count. Disclosed variation, OPEN_ITEMS.md.

                 The per-cell provenance label is NOT part of this. A figure
                 in the table still states its own source, because a source
                 column that omits "Demonstration" would misstate where a
                 number came from, which is RP-10 and is worse than a
                 badge. --}}
        </div>
    </section>

    <div class="c-page c-cmp">

        {{-- Mode, help, exports. Links rather than buttons: each mode is a
             different view of the same data and has to be shareable. --}}
        <div class="c-cmp__bar">
            <div class="c-cmp__modes" role="group" aria-label="{{ __('comparator.mode') }}">
                <a href="{{ $nav(['mode' => 'browse', 'indicator' => $comparison->indicator?->slug]) }}"
                   aria-current="{{ $isBrowse ? 'true' : 'false' }}">{{ __('comparator.mode_browse') }}</a>
                <a href="{{ $nav(['mode' => 'compare', 'category' => $comparison->category, 'countries' => $comparison->countries->pluck('iso_code')->implode(',')]) }}"
                   aria-current="{{ $isBrowse ? 'false' : 'true' }}">{{ __('comparator.mode_compare') }}</a>
            </div>

            <p class="c-cmp__help">{{ $isBrowse ? __('comparator.help_browse') : __('comparator.help_compare') }}</p>

            <div class="c-cmp__exports">
                <a class="c-btn c-btn--secondary" href="{{ route('comparator.csv', ['locale' => app()->getLocale()] + $comparison->queryParameters()) }}">{{ __('comparator.export_csv') }}</a>
                <a class="c-btn c-btn--secondary" href="{{ route('comparator.png', ['locale' => app()->getLocale()] + $comparison->queryParameters()) }}">{{ __('comparator.export_png') }}</a>
                <a class="c-btn c-btn--secondary" href="{{ route('comparator.pdf', ['locale' => app()->getLocale()] + $comparison->queryParameters()) }}">{{ __('comparator.export_pdf') }}</a>
            </div>
        </div>

        <div class="c-cmp__layout">

            {{-- Screen 03: aside flex:1 1 280px, max-width 340px, two
                 panels. Every control is a link, so the whole comparator
                 works before JavaScript and every state has a URL. --}}
            <aside class="c-cmp__aside">
                <div class="c-cmp__panel">
                    <div class="c-cmp__panel-head">
                        <h2>{{ __('comparator.member_states') }}</h2>
                        <span class="c-cmp__count" @if ($comparison->countries->count() >= \App\Support\Comparison::MAX_COUNTRIES) data-full @endif>
                            {{ $isBrowse
                                ? __('comparator.all_states', ['n' => $comparison->countries->count()])
                                : __('comparator.count_of', ['n' => $comparison->countries->count(), 'max' => \App\Support\Comparison::MAX_COUNTRIES]) }}
                        </span>
                    </div>
                    <p class="c-cmp__panel-help">{{ $isBrowse ? __('comparator.select_help_browse') : __('comparator.select_help') }}</p>

                    @unless ($isBrowse)
                        <div class="c-cmp__states">
                            @foreach (\App\Models\Country::with('authorities')->orderBy('name')->get() as $country)
                                @php
                                    $selected = $comparison->countries->contains('id', $country->id);
                                    $codes = $comparison->countries->pluck('iso_code');
                                    $next = $selected
                                        ? $codes->reject(fn ($c) => $c === $country->iso_code)
                                        : $codes->push($country->iso_code)->take(\App\Support\Comparison::MAX_COUNTRIES);
                                    $atLimit = ! $selected && $codes->count() >= \App\Support\Comparison::MAX_COUNTRIES;
                                @endphp
                                <a class="c-cmp__state"
                                   href="{{ $nav(['mode' => 'compare', 'category' => $comparison->category, 'countries' => $next->implode(',')]) }}"
                                   aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                   @if ($atLimit) data-at-limit aria-disabled="true" @endif>
                                    <x-flag :iso="$country->iso_code" />
                                    <span class="c-cmp__code">{{ strtoupper($country->iso_code) }}</span>
                                    <span class="c-cmp__state-names">
                                        <span class="c-cmp__state-name">{{ $country->name }}</span>
                                        <span class="c-cmp__state-acr">{{ $country->authorities->first()?->acronym ?? '' }}</span>
                                    </span>
                                    <span class="c-cmp__tick" aria-hidden="true">{{ $selected ? '✓' : '' }}</span>
                                </a>
                            @endforeach
                        </div>

                        @if ($comparison->countries->isNotEmpty())
                            <a class="c-cmp__clear" href="{{ $nav(['mode' => 'compare', 'category' => $comparison->category]) }}">{{ __('comparator.clear') }}</a>
                        @endif
                    @endunless
                </div>

                <div class="c-cmp__panel">
                    <h2>{{ __('comparator.categories') }}</h2>
                    <div class="c-cmp__categories">
                        @foreach ($categories as $category => $indicators)
                            <div>
                                <a class="c-cmp__category"
                                   href="{{ $nav(['mode' => 'compare', 'category' => $category, 'countries' => $comparison->countries->pluck('iso_code')->implode(',')]) }}"
                                   aria-current="{{ ! $isBrowse && $comparison->category === $category ? 'true' : 'false' }}">
                                    {{ __('comparator.category_'.$category) }}
                                </a>
                                <p class="c-cmp__category-blurb">{{ __('comparator.blurb_'.$category) }}</p>
                                <div class="c-cmp__indicators">
                                    @foreach ($indicators as $indicator)
                                        <a class="c-cmp__indicator"
                                           href="{{ $nav(['mode' => 'browse', 'indicator' => $indicator->slug]) }}"
                                           aria-current="{{ $isBrowse && $comparison->indicator?->id === $indicator->id ? 'true' : 'false' }}">
                                            {{ $indicator->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>

            <main class="c-cmp__main" id="comparator" data-comparator
                  data-endpoint="{{ route('comparator.data', ['locale' => app()->getLocale()] + $comparison->queryParameters()) }}">

                @if ($comparison->isEmpty())
                    {{-- Screen 03's empty state, with its two presets. --}}
                    @php $preset = \App\Models\Country::whereIn('iso_code', ['bw', 'mz'])->pluck('iso_code'); @endphp
                    <div class="c-cmp__empty">
                        <h2>{{ __('comparator.empty_title') }}</h2>
                        <p>{{ __('comparator.empty_body', ['category' => __('comparator.category_'.$comparison->category)]) }}</p>
                        <div class="c-cmp__empty-actions">
                            <a class="c-btn c-btn--primary" href="{{ $nav(['mode' => 'compare', 'category' => $comparison->category, 'countries' => $preset->implode(',')]) }}">
                                {{ __('comparator.preset_pair') }}
                            </a>
                            <a class="c-btn c-btn--secondary" href="{{ $nav(['mode' => 'browse', 'indicator' => $comparison->indicators->first()?->slug]) }}">
                                {{ __('comparator.preset_browse') }}
                            </a>
                        </div>
                    </div>

                @elseif ($isBrowse)
                    @include('pages.comparator._browse', ['comparison' => $comparison, 'cells' => $cells])

                @else
                    @include('pages.comparator._compare', ['comparison' => $comparison, 'cells' => $cells])
                @endif
            </main>
        </div>

        {{-- The strings the React layer needs, read from the document so
             there is one set of translations rather than two. --}}
        <div hidden>
            <span data-cmp-string="col_indicator">{{ __('comparator.col_indicator') }}</span>
            @foreach (['validated', 'reported', 'sample', 'not_reported'] as $provenance)
                <span data-cmp-string="provenance_{{ $provenance }}">{{ __('comparator.provenance_'.$provenance) }}</span>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
    {{-- React 18, vendored and served from this platform. The comparator is
         the one React surface named in the stack; everything around it is
         Blade, and the markup above is what a reader without JavaScript
         sees and what the printer prints. --}}
    <script src="{{ \App\Support\Assets::url('js/vendor/react.production.min.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/vendor/react-dom.production.min.js') }}" defer></script>
    <script src="{{ \App\Support\Assets::url('js/comparator.js') }}" defer></script>
@endpush
