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

    /*
     * Layout (29 October 2026, to cut the About page's scrolling). "columns"
     * sets headed sections side by side; "people" sets "Name, title" lines
     * as a grid of people. Both read the plain text the Secretariat types,
     * see App\Support\StructuredText, and fall back to prose when the text
     * has no such structure, so nothing is ever lost to a layout.
     */
    $layout = $block->setting('layout', 'prose');
    $structured = null;
    if ($bodyText !== null && $layout === 'columns') {
        $structured = \App\Support\StructuredText::sections($bodyText);
        $layout = $structured['sections'] === [] ? 'prose' : 'columns';
    } elseif ($bodyText !== null && $layout === 'people') {
        $structured = \App\Support\StructuredText::people($bodyText);
        $layout = $structured['people'] === [] && ! $backOffice ? 'prose' : 'people';
    } else {
        $layout = 'prose';
    }
    $alsoAnchor = $block->setting('also_anchor');
    $renderer = app(\App\Services\ContentRenderer::class);
@endphp

@unless ($hidden)

<section class="b b-richtext" @if ($block->anchor) id="{{ $block->anchor }}" @endif @if (! empty($ground)) data-ground="{{ $ground }}" @endif data-alignment="{{ $alignment === 'center' ? 'center' : 'start' }}" data-layout="{{ $layout }}">
    @if ($alsoAnchor && $alsoAnchor !== $block->anchor)
        {{-- A second name for this section, for links that use it. --}}
        <span id="{{ $alsoAnchor }}" class="b__also-anchor"></span>
    @endif
    <div class="b__inner {{ $layout === 'prose' ? 'b__inner--reading' : '' }}">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif

        @if ($body && $layout === 'columns')
            <div class="b-richtext__body" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                @foreach ($structured['intro'] as $paragraph)
                    <p class="b-richtext__intro">{!! $renderer->render($paragraph, 'block:'.$block->id) !!}</p>
                @endforeach
                <div class="b-sections">
                    @foreach ($structured['sections'] as $section)
                        <div class="b-sections__item">
                            <h3 class="b-sections__heading">{{ $section['heading'] }}</h3>
                            @foreach ($section['paragraphs'] as $paragraph)
                                <p>{!! $renderer->render($paragraph, 'block:'.$block->id) !!}</p>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif ($body && $layout === 'people')
            <div class="b-richtext__body" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                @if ($structured['intro'] !== [])
                    <div class="b-people__intro">
                        @foreach ($structured['intro'] as $paragraph)
                            <p>{!! $renderer->render($paragraph, 'block:'.$block->id) !!}</p>
                        @endforeach
                    </div>
                @endif
                @if ($structured['people'] === [])
                    {{-- Back office only: readers fall back to prose above. --}}
                    @include('blocks._empty', [
                        'editorOnly' => true,
                        'title' => __('blocks.people_empty_title'),
                        'body' => __('blocks.people_empty_body'),
                    ])
                @else
                    <ul class="b-people" role="list">
                        @foreach ($structured['people'] as $person)
                            <li class="b-person">
                                {{-- The photograph follows when CRASA sends it. --}}
                                <span class="b-person__photo" aria-hidden="true">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                                </span>
                                <span class="b-person__name">{{ $person['name'] }}</span>
                                <span class="b-person__title">{{ $person['title'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @elseif ($body)
            <div class="b-richtext__body" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                {!! $renderer->render($bodyText, 'block:'.$block->id) !!}
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
