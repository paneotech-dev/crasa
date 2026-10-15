{{--
    Key figures. No count-up on scroll: figures render at their value.

    A figure that stands for something a reader can go and look at is a link
    to it. Fourteen member states, three sectors, 1997 — each of those is a
    door on this platform, and printing the number without the door makes
    the front page a poster about a website rather than the front of one.

    The whole tile is the link, not a "read more" underneath it. A reader
    who wants the member states aims at the 14.

    Where a figure has no destination it renders as plain text, and it does
    not gain a hover state, a cursor or a border that suggests otherwise.
--}}
@php
    $heading = $block->translate('heading');
    $items = collect($block->setting('items', []))->filter(fn ($i) => filled($i['value'] ?? null));
@endphp

<section class="b b--tight" @if ($block->anchor) id="{{ $block->anchor }}" @endif @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif

        @if ($items->isEmpty())
            @include('blocks._empty', [
                'editorOnly' => true,
                'title' => __('blocks.figures_empty_title'),
                'body' => __('blocks.figures_empty_body'),
            ])
        @else
            <div class="b-figures__grid">
                @foreach ($items as $index => $item)
                    @php
                        $label = $block->translate('item_'.$index.'_label');
                        $source = $block->translate('item_'.$index.'_source');
                        $href = \App\Support\BlockLink::resolve($item['link'] ?? null);
                    @endphp

                    {{-- One element, two tags. The content is identical
                         either way; only whether it is reachable differs. --}}
                    <{{ $href ? 'a' : 'div' }} @class(['b-figure', 'b-figure--link' => $href]) @if ($href) href="{{ $href }}" @endif>
                        {{-- The figure is not per-language. Its label is. --}}
                        <div class="b-figure__value">{{ $item['value'] }}</div>
                        @if ($label)
                            <div class="b-figure__label">{{ $label->value }}</div>
                        @endif
                        @if ($source)
                            <div class="b-figure__source">{{ $source->value }}</div>
                        @endif
                    </{{ $href ? 'a' : 'div' }}>
                @endforeach
            </div>
        @endif
    </div>
</section>
