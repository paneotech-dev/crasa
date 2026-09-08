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
            {{-- Featured-and-rest rather than equal thirds. The newest
                 item is the one a reader wants, and equal cards would be
                 saying all three matter the same, which is not true. --}}
            <div class="b-cards @if ($items->count() >= 3) b-cards--featured @endif">
                @foreach ($items as $item)
                    <article class="b-card">
                        <div class="b-card__media">
                            @if (! empty($item['hero']))
                                <img class="c-img c-img--cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['hero']) }}"
                                     alt="" loading="lazy" decoding="async">
                            @elseif (! empty($item['image_slot']))
                                <x-responsive-image :name="$item['image_slot']" :alt="__('images.'.$item['image_slot'])"
                                                    sizes="(max-width: 760px) 100vw, 40vw" class="c-img--cover" />
                            @else
                                <div class="b-card__typeslot">
                                    <span class="b-card__typeslot-sector">{{ $item['sector'] ?? __('news.title') }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="b-card__body">
                            <div class="b-card__kicker">{{ $item['kicker'] ?? '' }}</div>
                            <h3 class="b-card__title"><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h3>
                            @if (! empty($item['summary']))
                                <p class="b-card__summary">{{ \Illuminate\Support\Str::limit($item['summary'], 130) }}</p>
                            @endif
                            <div class="b-card__meta">{{ $item['meta'] ?? '' }}</div>
                        </div>
                    </article>
                @endforeach
            </div>

        @endif
    </div>
</section>
