@php
    $heading = $block->translate('heading');
    $body = $block->translate('body');
    $alignment = $block->setting('alignment', 'start');
    // A section whose text has not been written, or is still the holding
    // line, is not shown to readers (15 October 2026): a heading followed
    // by "This section is being prepared" reads as unfinished. The back
    // office still lists it (needs_content), and editors see it in preview.
    $backOffice = auth()->user()?->can('enter-back-office') ?? false;
    $hidden = $block->isUnwritten() && ! $backOffice;
    // Drafting notes for CRASA ("[CRASA to confirm ...]") stay in the stored
    // text for the back office and are not shown to readers (15 October 2026).
    $bodyText = $body === null ? null : ($backOffice ? $body->value : \App\Support\PublishedText::withoutDraftingNotes($body->value)['text']);
@endphp

@unless ($hidden)

<section class="b b-richtext" @if ($block->anchor) id="{{ $block->anchor }}" @endif @if (! empty($ground)) data-ground="{{ $ground }}" @endif data-alignment="{{ $alignment === 'center' ? 'center' : 'start' }}">
    <div class="b__inner b__inner--reading">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif

        @if ($body)
            <div class="b-richtext__body" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                {!! app(\App\Services\ContentRenderer::class)->render($bodyText, 'block:'.$block->id) !!}
            </div>
        @else
            @include('blocks._empty', [
                'editorOnly' => true,
                'title' => __('blocks.richtext_empty_title'),
                'body' => __('blocks.richtext_empty_body'),
            ])
        @endif
    </div>
</section>
@endunless
