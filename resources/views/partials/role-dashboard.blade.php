{{--
    The blocks at the top of a dashboard, per role (F4). Built by
    App\Support\RoleDashboard; every empty block says why and what to do.
--}}
@php $dash = \App\Support\RoleDashboard::for(auth()->user()); @endphp

<section class="rd" aria-labelledby="rd-heading" data-role="{{ $dash['role'] }}">
    <div class="rd__head">
        <div>
            <h2 class="rd__title" id="rd-heading">{{ $dash['heading'] }}</h2>
            <p class="rd__intro">{{ $dash['intro'] }}</p>
        </div>
        @if ($dash['shortcuts'] !== [])
            <nav class="rd__shortcuts" aria-label="{{ __('fb.dash_shortcuts') }}">
                @foreach ($dash['shortcuts'] as $i => $shortcut)
                    <a class="a-btn {{ $i < 2 ? 'a-btn--primary' : 'a-btn--secondary' }}" href="{{ $shortcut['url'] }}">{{ $shortcut['label'] }}</a>
                @endforeach
            </nav>
        @endif
    </div>

    @if ($dash['figures'] !== [])
        <ul class="rd__figures" role="list" aria-label="{{ __('fb.dash_figures') }}">
            @foreach ($dash['figures'] as $figure)
                @php
                    $max = max(1, max($figure['series']));
                    $points = collect($figure['series'])->map(fn ($v, $i) => round($i * (120 / 7), 1).','.round(30 - ($v / $max) * 26, 1))->implode(' ');
                @endphp
                <li class="rd__figure">
                    @if ($figure['url'])<a class="rd__figlink" href="{{ $figure['url'] }}">@endif
                        <span class="rd__n">{{ number_format($figure['n']) }}</span>
                        <span class="rd__flabel">{{ $figure['label'] }}</span>
                    @if ($figure['url'])</a>@endif
                    <svg class="rd__spark" viewBox="-2 0 124 32" width="124" height="32" role="img"
                         aria-label="{{ __('fb.dash_sparkline', ['label' => $figure['label'], 'values' => implode(', ', $figure['series'])]) }}">
                        <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                    </svg>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="rd__grid">
        @foreach ($dash['blocks'] as $block)
            <section @class(['a-card', 'rd__block', 'rd__block--wide' => $block['wide'] ?? false]) aria-labelledby="rd-{{ $block['key'] }}" data-block="{{ $block['key'] }}">
                <div class="rd__bhead">
                    <h3 class="rd__btitle" id="rd-{{ $block['key'] }}">{{ $block['title'] }}</h3>
                    @isset($block['count'])
                        @if ($block['count'] !== null)
                            <span class="rd__badge" data-zero="{{ $block['count'] === 0 ? 'true' : 'false' }}">{{ trans_choice('fb.dash_waiting', $block['count'], ['count' => $block['count']]) }}</span>
                        @endif
                    @endisset
                </div>

                @isset($block['status'])
                    <p class="rd__status" data-tone="{{ $block['status']['tone'] }}">{{ $block['status']['label'] }}</p>
                @endisset

                @if ($block['rows'] !== [])
                    <ul class="rd__rows" role="list">
                        @foreach ($block['rows'] as $row)
                            <li class="rd__row">
                                @if ($row['url'])
                                    <a class="rd__label" href="{{ $row['url'] }}">{{ $row['label'] }}</a>
                                @else
                                    <span class="rd__label">{{ $row['label'] }}</span>
                                @endif
                                @if (! empty($row['meta']))<span class="rd__meta">{{ $row['meta'] }}</span>@endif
                                @if (! empty($row['comment']))<span class="rd__comment">{{ $row['comment'] }}</span>@endif
                                @if (! empty($row['ics']))
                                    <a class="rd__ics" href="{{ $row['ics'] }}">{{ __('fb.dash_add_calendar') }}<span class="rd-sr">: {{ $row['label'] }}</span></a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p @class(['rd__empty', 'rd__empty--plain' => $block['plain'] ?? false])>{{ $block['empty'] }}</p>
                @endif

                @isset($block['next'])
                    <p class="rd__next">{{ $block['next'] }}</p>
                @endisset

                @if ($block['actions'] !== [])
                    <p class="rd__actions">
                        @foreach ($block['actions'] as $action)
                            <a href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                        @endforeach
                    </p>
                @endif
            </section>
        @endforeach
    </div>
</section>
