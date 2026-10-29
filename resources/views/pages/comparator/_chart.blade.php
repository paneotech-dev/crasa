{{--
    A horizontal bar chart as inline SVG, drawn on the server.

    $bars: list of ['name', 'iso', 'value' (float|null), 'display', 'period']
    $max: the scale. $title and $summary name the chart for assistive
    technology; the table beside or under it is the full equivalent.
    Every bar states its own figure and year, so the chart reads without
    colour, and "Not reported" is a hatched stub with words, never a zero.
    The bars grow once when drawn (CSS, 400ms, off under reduced motion).
--}}
@php
    // Two geometries: 640 wide for desktop, 360 for phones, so the text
    // stays at a readable size instead of shrinking with the viewBox.
    $vw = ($narrow ?? false) ? 360 : 640;
    $rowH = 40;
    $labelW = ($narrow ?? false) ? 104 : 170;
    $barW = ($narrow ?? false) ? 150 : 330;
    $height = max(1, count($bars)) * $rowH + 8;
    $uid = ($id ?? 'cmp-chart-'.substr(md5($title.count($bars)), 0, 6)).(($narrow ?? false) ? '-n' : '');
@endphp
<svg class="c-cmp-chart {{ ($narrow ?? false) ? 'c-cmp-chart--narrow' : 'c-cmp-chart--wide' }}" viewBox="0 0 {{ $vw }} {{ $height }}" width="100%" role="img"
     aria-labelledby="{{ $uid }}-t {{ $uid }}-d" focusable="false" preserveAspectRatio="xMinYMin meet">
    <title id="{{ $uid }}-t">{{ $title }}</title>
    <desc id="{{ $uid }}-d">{{ $summary }}</desc>
    <defs>
        <pattern id="{{ $uid }}-hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <rect width="6" height="6" fill="#EEF2FA"></rect>
            <rect width="3" height="6" fill="#B8C3DE"></rect>
        </pattern>
    </defs>
    <line x1="{{ $labelW }}" y1="0" x2="{{ $labelW }}" y2="{{ $height - 4 }}" stroke="#D9E3EC" stroke-width="1"></line>
    @foreach ($bars as $i => $bar)
        @php
            $y = $i * $rowH + 6;
            $reported = $bar['value'] !== null;
            $w = $reported ? max(3, round(($bar['value'] / max($max, 0.0001)) * $barW, 1)) : 36;
            $w = min($w, $barW);
        @endphp
        <g class="c-cmp-chart__row" style="--i: {{ $i }}">
            <text class="c-cmp-chart__name" x="{{ $labelW - 12 }}" y="{{ $y + 20 }}" text-anchor="end">{{ \Illuminate\Support\Str::limit($bar['name'], ($narrow ?? false) ? 12 : 20) }}</text>
            @if ($reported)
                <rect class="c-cmp-chart__bar" x="{{ $labelW }}" y="{{ $y + 4 }}" width="{{ $w }}" height="22" rx="3" fill="{{ $i === 0 && ($highlightFirst ?? false) ? '#0B5F3E' : '#0F8757' }}"></rect>
                <text class="c-cmp-chart__value" x="{{ $labelW + $w + 8 }}" y="{{ $y + 20 }}">{{ $bar['display'] }}@if ($bar['period'])<tspan class="c-cmp-chart__year" dx="6">{{ $bar['period'] }}</tspan>@endif</text>
            @else
                <rect x="{{ $labelW }}" y="{{ $y + 4 }}" width="{{ $w }}" height="22" rx="3" fill="url(#{{ $uid }}-hatch)" stroke="#7B8794" stroke-width="1" stroke-dasharray="3 2"></rect>
                <text class="c-cmp-chart__missing" x="{{ $labelW + $w + 8 }}" y="{{ $y + 20 }}">{{ $bar['display'] }}</text>
            @endif
        </g>
    @endforeach
</svg>
