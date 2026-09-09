@php
    $title = $item->translate('title');
    $summary = $item->translate('summary');
    $slot = $item->fallbackImageSlot();

    /*
     * A photograph appears once per page. The comment below says this grid
     * "does not repeat one placeholder four times in a row", and that was
     * only true across sectors: two articles in the same sector shared one
     * fallback and the grid showed the same picture twice.
     *
     * $usedImageSlots is passed down from the listing and accumulates as
     * the cards render. The first article in a sector gets the photograph;
     * a later one gets the designed sector panel.
     */
    $usedImageSlots ??= collect();

    if ($slot && $usedImageSlots->contains($slot)) {
        $slot = null;
    } elseif ($slot) {
        $usedImageSlots->push($slot);
    }
@endphp

<article class="b-card">
    <div class="b-card__media">
        @if ($item->hero_image_path)
            <img class="c-img c-img--cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->hero_image_path) }}"
                 alt="" loading="lazy" decoding="async">
        @elseif ($slot)
            {{-- Differentiated by sector, so a grid of untouched articles
                 does not repeat one placeholder four times in a row. --}}
            <x-responsive-image :name="$slot" :alt="__('images.'.$slot)" sizes="(max-width: 700px) 100vw, 33vw" class="c-img--cover" />
        @else
            {{-- Broadcasting has no supplied image, and a sector whose
                 photograph is already on this page does not get it twice.
                 Well-set type on the sector's own ground beats a grey box,
                 and beats borrowing a photograph from a sector this article
                 is not about. --}}
            <div class="b-card__typeslot" data-sector="{{ $item->sector?->slug ?? 'default' }}">
                <x-sector-icon :sector="$item->sector?->slug" />
                <span class="b-card__typeslot-sector">{{ $item->sector?->name ?? __('news.title') }}</span>
            </div>
        @endif
    </div>
    <div class="b-card__body">
        <div class="b-card__kicker">{{ __($item->category->labelKey()) }}</div>
        @if ($title)
            <h2 class="b-card__title" @if ($title->isFallback()) lang="{{ $title->locale }}" @endif>
                <a href="{{ route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $item->slug]) }}">{{ $title->value }}</a>
            </h2>
        @endif
        @if ($summary)
            <p class="b-card__summary">{{ \Illuminate\Support\Str::limit($summary->value, 140) }}</p>
        @endif
        <div class="b-card__meta">
            <span>{{ $item->published_at?->translatedFormat('j F Y') }}</span>
            @if ($item->sector)<span>{{ $item->sector->name }}</span>@endif
        </div>
    </div>
</article>
