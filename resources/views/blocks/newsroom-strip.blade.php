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
            {{-- Three equal cards. Screen 02 line 401 draws
                 "repeat(auto-fit,minmax(min(100%,240px),1fr));gap:var(--s-5)"
                 with an image on every one; the 1+2 arrangement it replaced
                 gave the first card a 3/2 photograph and hid the other two
                 images outright. --}}
            @php
                /*
                 * A photograph appears once per page. Two news items in the
                 * same sector both fall back to that sector's image, and
                 * the strip then showed the same picture twice side by
                 * side, which reads as a rendering fault rather than as two
                 * stories.
                 *
                 * The first card in a sector gets the photograph; a second
                 * gets the designed sector panel. CLAUDE.md carries the
                 * rule and a test walks every public page for it.
                 */
                $usedImages = [];
            @endphp

            <div class="b-cards">
                @foreach ($items as $item)
                    <article class="b-card">
                        <div class="b-card__media">
                            @if (! empty($item['hero']))
                                <img class="c-img c-img--cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['hero']) }}"
                                     alt="" loading="lazy" decoding="async">
                            @elseif (! empty($item['image_slot']) && ! in_array($item['image_slot'], $usedImages, true))
                                @php $usedImages[] = $item['image_slot']; @endphp
                                <x-responsive-image :name="$item['image_slot']" :alt="__('images.'.$item['image_slot'])"
                                                    sizes="(max-width: 760px) 100vw, 40vw" class="c-img--cover" />
                            @else
                                {{-- No photograph for this sector in the
                                     library. A sector-coloured panel with
                                     the sector's own icon, not a word on
                                     navy: a card with a word where its
                                     neighbours have photographs reads as
                                     the one that failed to load. --}}
                                <div class="b-card__typeslot" data-sector="{{ $item['sector_slug'] ?? 'default' }}">
                                    <x-sector-icon :sector="$item['sector_slug'] ?? null" />
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
