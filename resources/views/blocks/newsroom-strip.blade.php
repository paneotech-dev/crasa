@php
    $heading = $block->translate('heading');
    $items = $items ?? collect();
@endphp

<section class="b b--medium" @if ($block->anchor) id="{{ $block->anchor }}" @endif @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif

        @if ($items->isEmpty())
            @include('blocks._empty', [
                'title' => __('blocks.news_empty_title'),
                'body' => __('blocks.news_empty_body'),
                'actionLabel' => __('nav.newsroom'),
                'actionUrl' => \App\Support\Navigation::url('newsroom'),
            ])
        @else
            {{-- 29 October 2026: the newsroom's own card, so the three
                 latest items look exactly like the newsroom they open: the
                 same 16:10 frame, equal heights, the type badge and the
                 date. An item without its own photograph gets its type's
                 placeholder, never another item's picture. --}}
            <div class="n-grid">
                @foreach ($items as $item)
                    @include('pages.newsroom._card', ['item' => $item['model'], 'featured' => false, 'headingLevel' => 3])
                @endforeach
            </div>
        @endif
    </div>
</section>
