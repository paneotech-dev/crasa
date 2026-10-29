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
    $hasStatements = $items->keys()->contains(fn ($index) => $block->translate('item_'.$index.'_heading') !== null);
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
            {{-- 29 October 2026: a figure on its own said nothing about
                 what CRASA does. Where an item carries a statement (a line
                 of work, in the pillars' own wording) the statement leads
                 and the figure supports it. An item without one renders as
                 the bare figure it always was. --}}
            <div @class(['b-figures__grid', 'b-figures__grid--statements' => $hasStatements])>
                @foreach ($items as $index => $item)
                    @php
                        $label = $block->translate('item_'.$index.'_label');
                        $source = $block->translate('item_'.$index.'_source');
                        $statement = $block->translate('item_'.$index.'_heading');
                        $statementBody = $block->translate('item_'.$index.'_body');
                        $href = \App\Support\BlockLink::resolve($item['link'] ?? null);
                    @endphp

                    @if ($statement)
                        <div class="b-figure b-figure--statement">
                            <h3 class="b-figure__statement" @if ($statement->isFallback()) lang="{{ $statement->locale }}" @endif>{{ $statement->value }}</h3>
                            @if ($statementBody)
                                <p class="b-figure__body" @if ($statementBody->isFallback()) lang="{{ $statementBody->locale }}" @endif>{{ $statementBody->value }}</p>
                            @endif
                            <{{ $href ? 'a' : 'p' }} @class(['b-figure__support', 'b-figure__support--link' => $href]) @if ($href) href="{{ $href }}" @endif>
                                <span class="b-figure__value">{{ $item['value'] }}</span>
                                @if ($label)
                                    <span class="b-figure__label">{{ $label->value }}</span>
                                @endif
                            </{{ $href ? 'a' : 'p' }}>
                        </div>
                    @else
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
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</section>
