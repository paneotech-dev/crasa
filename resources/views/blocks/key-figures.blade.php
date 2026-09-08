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
                @foreach ($items as $index => $item)
                    @php
                        $label = $block->translate('item_'.$index.'_label');
                        $source = $block->translate('item_'.$index.'_source');
                    @endphp
                    <div class="b-figure">
                        {{-- The figure is not per-language. Its label is. --}}
                        <div class="b-figure__value">{{ $item['value'] }}</div>
                        @if ($label)
                            <div class="b-figure__label">{{ $label->value }}</div>
                        @endif
                        @if ($source)
                            <div class="b-figure__source">{{ $source->value }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
