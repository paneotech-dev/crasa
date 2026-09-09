{{--
    Upcoming public meetings, WS-07. The platform displays events; it does
    not manage them. Event creation, scheduling and delegate logistics are
    reserved commercial scope and are not built here.
--}}
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
                'title' => __('blocks.events_empty_title'),
                'body' => __('blocks.events_empty_body'),
            ])
        @else
            <div class="b-cards">
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
