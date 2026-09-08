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

<section class="b b--medium" @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
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
            {{-- Featured-and-rest rather than equal thirds. The newest
                 item is the one a reader wants, and equal cards would be
                 saying all three matter the same, which is not true. --}}
            <div class="b-cards @if ($items->count() >= 3) b-cards--featured @endif">
                @foreach ($items as $item)
                    <article class="b-card">
                        <div class="b-card__body">
                            <div class="b-card__kicker">{{ $item['kicker'] ?? '' }}</div>
                            <h3 class="b-card__title">
                                @if (! empty($item['url']))
                                    <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                                @else
                                    {{ $item['title'] }}
                                @endif
                            </h3>
                            <div class="b-card__meta">{{ $item['meta'] ?? '' }}</div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
