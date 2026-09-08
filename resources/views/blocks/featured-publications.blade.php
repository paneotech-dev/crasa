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

<section class="b b--medium" @if ($block->anchor) id="{{ $block->anchor }}" @endif @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
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
            <div class="b-rows">
                @foreach ($items as $item)
                    <div class="b-row">
                        <div class="b-row__thumb">
                            @if (! empty($item['thumbnail']))
                                <img class="c-img" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['thumbnail']) }}"
                                     alt="" loading="lazy" decoding="async" width="64">
                            @elseif (! empty($item['filetype']))
                                <span class="c-filetype" aria-hidden="true">{{ $item['filetype'] }}</span>
                            @endif
                        </div>

                        <div class="b-row__main">
                            <div class="b-row__title">
                                <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                            </div>
                            @if (! empty($item['summary']))
                                <p class="b-row__summary">{{ \Illuminate\Support\Str::limit($item['summary'], 160) }}</p>
                            @endif
                            <div class="b-row__meta">
                                <span>{{ $item['kicker'] }}</span>
                                <span>{{ $item['meta'] }}</span>
                                @if (! empty($item['size']))<span>{{ $item['filetype'] }}, {{ $item['size'] }}</span>@endif
                                @if (! empty($item['locale']))<span class="c-lang-badge">{{ strtoupper($item['locale']) }}</span>@endif
                            </div>
                        </div>

                        @if (! empty($item['superseded']))
                            <div class="b-row__actions">
                                <span class="c-pill c-pill--archived">{{ __('publications.superseded') }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

        @endif
    </div>
</section>
