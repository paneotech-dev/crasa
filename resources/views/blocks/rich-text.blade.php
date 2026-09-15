@php
    $heading = $block->translate('heading');
    $body = $block->translate('body');
    $alignment = $block->setting('alignment', 'start');
@endphp

<section class="b b-richtext" @if ($block->anchor) id="{{ $block->anchor }}" @endif @if (! empty($ground)) data-ground="{{ $ground }}" @endif data-alignment="{{ $alignment === 'center' ? 'center' : 'start' }}">
    <div class="b__inner b__inner--reading">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif

        @if ($body)
            <div class="b-richtext__body" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                {!! app(\App\Services\ContentRenderer::class)->render($body->value, 'block:'.$block->id) !!}
            </div>
        @else
            @include('blocks._empty', [
                'title' => __('blocks.richtext_empty_title'),
                'body' => __('blocks.richtext_empty_body'),
            ])
        @endif
    </div>
</section>
