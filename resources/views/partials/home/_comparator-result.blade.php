{{--
    The home console's result, in two parts so the form between them is
    never re-rendered: 'table' (left) and 'chart' (right). Without $part
    (the fragment endpoint) both parts are rendered, each marked with
    data-cmp-part, and home-compare.js moves each into its slot. The data
    attributes carry the selection the server resolved.
--}}
@php
    $parts = isset($part) ? [$part] : ['table', 'chart'];
    $a = $rows->get(0)['country'] ?? '';
    $b = $rows->get(1)['country'] ?? '';
    $plain = trans()->has('d.plain.'.$indicator->slug) ? __('d.plain.'.$indicator->slug) : null;
    $summary = $indicator->name.': '.$rows->map(fn ($r) => $r['reported'] ? __('d.summary_part', ['state' => $r['country'], 'value' => $r['value'], 'period' => $r['period']]) : __('d.summary_missing', ['state' => $r['country']]))->implode('; ').'.';
    $max = max(1.0, (float) $rows->pluck('numeric')->filter()->max());
    $pill = ['validated' => 'u-pill--ok', 'reported' => 'u-pill--notice'];
@endphp
<div data-cmp-result data-indicator="{{ $selected['indicator'] }}" data-a="{{ $selected['a'] }}" data-b="{{ $selected['b'] }}" data-status="{{ $summary }}">
@foreach ($parts as $p)
    <div data-cmp-part="{{ $p }}">
    @if ($p === 'table')
        <div class="u-table-frame c-hcmp__table">
            <table class="u-table">
                <caption class="c-sr">{{ $summary }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('d.col_state') }}</th>
                        <th scope="col" class="c-cmp__num">{{ __('d.col_value') }}</th>
                        <th scope="col">{{ __('d.col_year') }}</th>
                        <th scope="col">{{ __('d.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td><a class="c-cmp__state-link" href="{{ route('members.show', ['locale' => app()->getLocale(), 'iso' => $row['iso']]) }}"><x-flag :iso="$row['iso']" /> {{ $row['country'] }}</a></td>
                            <td class="c-cmp__num">{{ $row['value'] ?? __('comparator.not_reported') }}</td>
                            <td>{{ $row['period'] }}</td>
                            <td>
                                @if ($row['reported'])
                                    <span class="u-pill {{ $pill[$row['confidence']] ?? 'u-pill--notice' }}">{{ __('comparator.provenance_'.($row['confidence'] === 'validated' ? 'validated' : 'reported')) }}</span>
                                @else
                                    <span class="u-pill u-pill--muted">{{ __('comparator.provenance_not_reported') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="c-hcmp__summary">{{ $summary }}</p>
    @else
        <figure class="c-hcmp__chart u-panel">
            <figcaption class="c-cmp__chart-cap">{{ $plain[0] ?? $indicator->name }}@if ($indicator->unitLabel()), {{ $indicator->unitLabel() }}@endif</figcaption>
            <div class="c-cmp__chart-frame">
                @include('pages.comparator._chart', [
                    'bars' => $rows->map(fn ($r) => ['name' => $r['country'], 'iso' => $r['iso'], 'value' => $r['numeric'], 'display' => $r['value'] ?? __('comparator.not_reported'), 'period' => $r['period']])->values()->all(),
                    'max' => $max,
                    'title' => $indicator->name,
                    'summary' => $summary,
                    'id' => 'hcmp-chart',
                ])
                @include('pages.comparator._chart', [
                    'bars' => $rows->map(fn ($r) => ['name' => $r['country'], 'iso' => $r['iso'], 'value' => $r['numeric'], 'display' => $r['value'] ?? __('comparator.not_reported'), 'period' => $r['period']])->values()->all(),
                    'max' => $max,
                    'title' => $indicator->name,
                    'summary' => $summary,
                    'id' => 'hcmp-chart',
                    'narrow' => true,
                ])
            </div>
            @if ($plain)<p class="c-hcmp__plain">{{ $plain[1] }}</p>@endif
            <x-indicator-note :indicators="$indicator" class="c-hcmp__note" />
            @if ($indicator->source_standard)
                <p class="c-hcmp__source"><strong>{{ __('d.source') }}</strong> {{ $indicator->source_standard }}</p>
            @endif
        </figure>
        <p class="c-hcmp__open"><a class="u-btn u-btn--secondary" href="{{ $comparator_url }}">{{ __('d.open_full') }}</a></p>
    @endif
    </div>
@endforeach
</div>
