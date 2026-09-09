{{--
    The statement band, screen 02 line 376.

      <section style="position:relative;overflow:hidden;background:var(--c-navy)">
        <div style="position:absolute;inset:0;background:
          repeating-linear-gradient(122deg,rgba(232,244,236,.05) 0 1px,transparent 1px 16px),
          repeating-linear-gradient(58deg,rgba(26,140,90,.10) 0 2px,transparent 2px 26px)"></div>

    One claim on the left, the figures that support it on the right. It is
    the only place on the homepage where CRASA says something in its own
    voice rather than listing what it holds, which is why the screen gives
    it a whole navy band between the publications and the newsroom.

    Every cell in the grid is a query. Nothing here is written down.
--}}
@php
    $eyebrow = $block->translate('eyebrow');
    $statement = $block->translate('statement');
    $ctaLabel = $block->translate('cta_label');
    $ctaUrl = \App\Support\Navigation::resolve($block->setting('cta_url'));
    $gridLabel = $block->translate('grid_label');
    $gridNote = $block->translate('grid_note');

    $cells = \App\Support\StatementGrid::build();
@endphp

<section class="b b-statement" @if ($block->anchor) id="{{ $block->anchor }}" @endif
         @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    {{-- The two crossed weaves the screen lays over the navy. Decorative,
         so aria-hidden and behind everything. --}}
    <div class="b-statement__weave" aria-hidden="true"></div>

    <div class="b__inner b-statement__inner">
        @if ($statement === null && $cells === [])
            @include('blocks._empty', [
                'title' => __('blocks.statement_empty_title'),
                'body' => __('blocks.statement_empty_body'),
            ])
        @else
            <div class="b-statement__copy">
                @if ($eyebrow)
                    <p class="b-statement__eyebrow" @if ($eyebrow->isFallback()) lang="{{ $eyebrow->locale }}" @endif>{{ $eyebrow->value }}</p>
                @endif

                @if ($statement)
                    <p class="b-statement__quote" @if ($statement->isFallback()) lang="{{ $statement->locale }}" @endif>{{ $statement->value }}</p>
                @endif

                @if ($ctaLabel && $ctaUrl)
                    <a class="b-statement__action" href="{{ $ctaUrl }}">{{ $ctaLabel->value }}</a>
                @endif
            </div>

            @if ($cells !== [])
                <div class="b-statement__figures">
                    @if ($gridLabel)
                        <p class="b-statement__grid-label">{{ $gridLabel->value }}</p>
                    @endif

                    {{-- A definition list, not a table: these are labelled
                         values, not rows and columns. The 1px gap over a
                         --c-navy-line ground is what draws the rules
                         between cells, exactly as the screen does it. --}}
                    <dl class="b-statement__grid">
                        @foreach ($cells as $cell)
                            <div class="b-statement__cell">
                                <dd class="b-statement__value">{{ $cell['value'] }}</dd>
                                <dt class="b-statement__label">{{ $cell['label'] }}</dt>
                            </div>
                        @endforeach
                    </dl>

                    @if ($gridNote)
                        <p class="b-statement__note">{{ $gridNote->value }}</p>
                    @endif
                </div>
            @endif
        @endif
    </div>
</section>
