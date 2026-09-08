@php
    $title = $item->translate('title');
    $summary = $item->translate('summary');
    $slot = $item->fallbackImageSlot();
@endphp

<article class="b-card">
    <div class="b-card__media">
        @if ($item->hero_image_path)
            <img class="c-img c-img--cover" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->hero_image_path) }}"
                 alt="" loading="lazy" decoding="async">
        @else
            {{-- Differentiated by sector, so a grid of untouched articles
                 does not repeat one placeholder four times in a row. --}}
            <x-responsive-image :name="$slot" :alt="__('images.'.$slot)" sizes="(max-width: 700px) 100vw, 33vw" class="c-img--cover" />
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
