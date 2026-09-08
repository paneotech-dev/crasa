{{--
    Pillars. Columns are 2 to 4, validated server-side.

    Card heights follow content: the grid uses align-items:start, so a pillar
    with two sentences is shorter than one with five. Padding both to a
    common height is the tell CLAUDE.md forbids.
--}}
@php
    $heading = $block->translate('heading');
    $intro = $block->translate('intro');
    $columns = (int) $block->setting('columns', 3);
    $items = collect($block->setting('items', []));
@endphp

<section class="b" @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif
        @if ($intro)
            <p class="b__intro">{{ $intro->value }}</p>
        @endif

        @if ($items->isEmpty())
            @include('blocks._empty', [
                'title' => __('blocks.pillars_empty_title'),
                'body' => __('blocks.pillars_empty_body'),
            ])
        @else
            <div class="b-pillars__grid" data-columns="{{ max(2, min(4, $columns)) }}">
                @foreach ($items as $index => $item)
                    @php
                        $pillarHeading = $block->translate('item_'.$index.'_heading');
                        $pillarBody = $block->translate('item_'.$index.'_body');
                    @endphp
                    <article class="b-pillar">
                        @if ($pillarHeading)
                            <h3 class="b-pillar__heading">{{ $pillarHeading->value }}</h3>
                        @endif
                        @if ($pillarBody)
                            <p class="b-pillar__body">{{ $pillarBody->value }}</p>
                        @endif
                        @if (filled($item['link'] ?? null))
                            <a class="b-pillar__link" href="{{ $item['link'] }}">{{ __('blocks.read_more') }}</a>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
