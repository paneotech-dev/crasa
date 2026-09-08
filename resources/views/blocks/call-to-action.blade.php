{{-- Tone from the defined palette only. Three tones, validated server-side. --}}
@php
    $headline = $block->translate('headline');
    $body = $block->translate('body');
    $ctaLabel = $block->translate('cta_label');
    $ctaUrl = \App\Support\Navigation::resolve($block->setting('cta_url'));
    $tone = $block->setting('tone', 'navy');
@endphp

<section class="b b--medium" @if ($block->anchor) id="{{ $block->anchor }}" @endif @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    <div class="b__inner">
        <div class="b-cta__panel" data-tone="{{ in_array($tone, ['navy', 'green', 'cream'], true) ? $tone : 'navy' }}">
            @if ($headline)
                <h2 class="b-cta__headline">{{ $headline->value }}</h2>
            @endif
            @if ($body)
                <p class="b-cta__body">{{ $body->value }}</p>
            @endif
            @if ($ctaLabel && $ctaUrl)
                <a class="c-btn {{ $tone === 'navy' ? 'c-btn--on-navy' : 'c-btn--primary' }}" href="{{ $ctaUrl }}">{{ $ctaLabel->value }}</a>
            @endif
            @if (! $headline)
                @include('blocks._empty', [
                    'title' => __('blocks.cta_empty_title'),
                    'body' => __('blocks.cta_empty_body'),
                ])
            @endif
        </div>
    </div>
</section>
