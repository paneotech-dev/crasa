{{--
    Compare mode, screen 03: up to four member states down one category.

    TWO RENDERINGS OF THE SAME FIGURES, and both are always in the markup.
    Screen 03: "A four-column comparison cannot be a table on a phone. Below
    720px the comparison transposes: one card per indicator, member states
    stacked inside it as labelled rows. Every value keeps its provenance
    marker and its source line. Nothing is hidden behind a horizontal
    scroll, and nothing requires a hover."

    So the table is hidden below 720 and the cards above it, by media query
    alone. Duplicating the figures in the document rather than swapping them
    with JavaScript is what makes the page print correctly at any width and
    work with no JavaScript at all. The hidden one is hidden from assistive
    technology too, so nothing is announced twice.
--}}
<section class="c-cmp__result">
    <div class="c-cmp__result-head">
        <p class="c-cmp__eyebrow">{{ __('comparator.mode_compare') }}</p>
        <h2>{{ __('comparator.category_'.$comparison->category) }}</h2>
        <p class="c-cmp__result-meta">{{ __('comparator.blurb_'.$comparison->category) }}</p>
        <p class="c-cmp__result-meta">{{ __('comparator.reported_count', [
            'reported' => $comparison->reportedCount(),
            'total' => $comparison->indicators->count() * $comparison->countries->count(),
        ]) }}</p>
    </div>

    {{-- The wide table, 720px and up. --}}
    <div class="c-cmp__tablewrap c-cmp__wide">
        <table class="c-cmp__table c-cmp__table--matrix">
            <caption class="c-sr">{{ __('comparator.compare_caption', [
                'category' => __('comparator.category_'.$comparison->category),
                'states' => $comparison->countries->pluck('name')->implode(', '),
            ]) }}</caption>
            <thead>
                <tr>
                    <th scope="col" class="c-cmp__sticky">{{ __('comparator.col_indicator') }}</th>
                    @foreach ($comparison->countries as $country)
                        <th scope="col">
                            <span class="c-cmp__state-cell">
                                <x-flag :iso="$country->iso_code" />
                                <span class="c-cmp__code c-cmp__code--on-navy">{{ strtoupper($country->iso_code) }}</span>
                                <span class="c-cmp__state-names">
                                    <span class="c-cmp__state-name">{{ $country->name }}</span>
                                    <span class="c-cmp__state-acr">{{ $country->authorities->first()?->acronym ?? '' }}</span>
                                </span>
                                <a class="c-cmp__remove"
                                   href="{{ route('comparator', ['locale' => app()->getLocale(), 'mode' => 'compare', 'category' => $comparison->category, 'countries' => $comparison->countries->reject(fn ($c) => $c->id === $country->id)->pluck('iso_code')->implode(',')]) }}"
                                   aria-label="{{ __('comparator.remove', ['state' => $country->name]) }}">&times;</a>
                            </span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($comparison->indicators as $indicator)
                    <tr>
                        <th scope="row" class="c-cmp__sticky">
                            <span class="c-cmp__indicator-name">{{ $indicator->name }}</span>
                            <span class="c-cmp__indicator-unit">{{ $indicator->unitLabel() }}</span>
                        </th>
                        @foreach ($comparison->countries as $country)
                            @php $cell = $cells[$indicator->id][$country->id] ?? null; @endphp
                            <td>
                                @if ($cell)
                                    <span class="c-cmp__value" @unless ($cell->isReported()) data-missing @endunless>{{ $cell->display() }}</span>
                                    <span class="c-cmp__pill" data-provenance="{{ $cell->provenance() }}">{{ __('comparator.provenance_'.$cell->provenance()) }}</span>
                                    @if ($cell->source())
                                        <span class="c-cmp__source">{{ $cell->source() }}{{ $cell->period() ? ', '.$cell->period() : '' }}</span>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- The same figures as cards, below 720px. --}}
    <div class="c-cmp__narrow">
        @foreach ($comparison->indicators as $indicator)
            <article class="c-cmp__card">
                <h3>{{ $indicator->name }}</h3>
                <p class="c-cmp__indicator-unit">{{ $indicator->unitLabel() }}</p>
                @foreach ($comparison->countries as $country)
                    @php $cell = $cells[$indicator->id][$country->id] ?? null; @endphp
                    @continue($cell === null)
                    <div class="c-cmp__card-row">
                        <span class="c-cmp__card-state">{{ $country->name }}</span>
                        <span class="c-cmp__card-value" @unless ($cell->isReported()) data-missing @endunless>{{ $cell->display() }}</span>
                    </div>
                    @if ($cell->source())
                        <p class="c-cmp__source c-cmp__card-source">{{ $cell->source() }}{{ $cell->period() ? ', '.$cell->period() : '' }}</p>
                    @endif
                @endforeach
            </article>
        @endforeach
    </div>

    <div class="c-cmp__foot">
        <span><strong>{{ __('comparator.foot_source') }}</strong> {{ __('comparator.foot_source_mixed') }}</span>
        @if ($comparison->latestUpdate())
            <span><strong>{{ __('comparator.foot_updated') }}</strong> {{ $comparison->latestUpdate()->isoFormat('D MMMM YYYY') }}</span>
        @endif
        <span class="c-cmp__permalink">{{ __('comparator.foot_permalink') }} <code>{{ $comparison->permalink() }}</code></span>
    </div>
</section>

{{-- Screen 03 draws this panel and names the live example. --}}
<aside class="c-cmp__explainer">
    <strong>{{ __('comparator.missing_title') }}</strong>
    {{ __('comparator.missing_body') }}
</aside>
