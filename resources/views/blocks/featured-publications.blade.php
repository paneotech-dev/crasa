{{--
    Auto-populated from publications. The publications table arrives in a
    later stage; until then the block renders its empty state, which is the
    same state a filter with no matches produces.
--}}
@php
    $heading = $block->translate('heading');
    $intro = $block->translate('intro');
    $items = $items ?? collect();
@endphp

<section class="b">
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif
        @if ($intro)
            <p class="b__intro">{{ $intro->value }}</p>
        @endif

        @if ($items->isEmpty())
            @include('blocks._empty', [
                'title' => __('blocks.publications_empty_title'),
                'body' => __('blocks.publications_empty_body'),
                'actionLabel' => __('nav.publications'),
                'actionUrl' => \App\Support\Navigation::url('publications'),
            ])
        @else
            <div class="b-cards">
                @foreach ($items as $item)
                    <article class="b-card">
                        <div class="b-card__body">
                            <div class="b-card__kicker">{{ $item['kicker'] ?? '' }}</div>
                            <h3 class="b-card__title">{{ $item['title'] }}</h3>
                            <div class="b-card__meta">{{ $item['meta'] ?? '' }}</div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
