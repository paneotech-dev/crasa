{{-- Key figures. No count-up on scroll: figures render at their value. --}}
@php
    $heading = $block->translate('heading');
    $items = collect($block->setting('items', []))->filter(fn ($i) => filled($i['value'] ?? null));
@endphp

<section class="b b--tight">
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif

        @if ($items->isEmpty())
            @include('blocks._empty', [
                'title' => __('blocks.figures_empty_title'),
                'body' => __('blocks.figures_empty_body'),
            ])
        @else
            <div class="b-figures__grid">
                @foreach ($items as $item)
                    <div class="b-figure">
                        <div class="b-figure__value">{{ $item['value'] }}</div>
                        <div class="b-figure__label">{{ $item['label'] ?? '' }}</div>
                        @if (filled($item['source'] ?? null))
                            <div class="b-figure__source">{{ $item['source'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
