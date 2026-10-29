{{--
    Privacy notice and terms of use (UI system, 29 October 2026): a short
    hero, a sticky table of contents beside the text, and a call-to-action
    band. The text is the page's own rich-text blocks. Each line the
    Secretariat types as "Heading. Body" becomes a numbered section in the
    contents; a line that is only a heading opens a group. Drafting notes
    in square brackets are left out for readers, as in the rich-text block,
    and a section left with only its heading is not shown.
--}}
@extends('layouts.public')

@php
    $locale = app()->getLocale();
    $title = $page->translate('title')?->value ?? '';
    $description = $page->translate('meta_description')?->value;
    $backOffice = auth()->user()?->can('enter-back-office') ?? false;
    $renderer = app(\App\Services\ContentRenderer::class);
    // Bold typed as **text** in the plain-text body.
    $render = fn (string $text, int $block) => preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $renderer->render($text, 'block:'.$block));

    $sections = [];
    $prose = [];
    foreach ($page->visibleBlocks as $block) {
        if ($block->type() !== \App\Enums\BlockType::RichText) {
            continue;
        }
        if ($block->isUnwritten() && ! $backOffice) {
            continue;
        }
        $body = $block->translate('body');
        if ($body === null) {
            continue;
        }
        $blockHeading = $block->translate('heading')?->value;
        $lines = preg_split('/\R+/u', trim($body->value));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (! $backOffice) {
                $line = trim(\App\Support\PublishedText::withoutDraftingNotes($line)['text']);
                if ($line === '') {
                    continue;
                }
            }
            // "Heading. Body": a short opening sentence without markup.
            if (! str_contains($line, '<') && preg_match('/^([^.<]{2,70})\.(?:\s+(.+))?$/su', $line, $m)) {
                $heading = trim($m[1]);
                $text = trim($m[2] ?? '');
                $sections[] = [
                    'id' => \Illuminate\Support\Str::slug($heading) ?: 'section-'.count($sections),
                    'heading' => $heading,
                    'text' => $text,
                    'lang' => $body->isFallback() ? $body->locale : null,
                    'block' => $block->id,
                ];
            } else {
                $prose[] = ['text' => $line, 'block' => $block->id, 'lang' => $body->isFallback() ? $body->locale : null];
            }
        }
    }
    // Unique anchors.
    $seen = [];
    foreach ($sections as &$section) {
        $id = $section['id'];
        $n = 2;
        while (isset($seen[$section['id']])) {
            $section['id'] = $id.'-'.$n++;
        }
        $seen[$section['id']] = true;
    }
    unset($section);
@endphp

@section('title', $title)
@if ($description)
    @section('description', $description)
@endif

@include('partials.cpages.styles')

@section('content')
    @php
        $aside = '<div class="u-panel cp-legal__meta"><dl class="u-facts">'
            .'<div><dt>'.e(__('cpages_c.legal_updated')).'</dt><dd>'.e($page->updated_at?->locale($locale)->isoFormat('D MMMM YYYY')).'</dd></div>'
            .'<div><dt>'.e(__('cpages_c.legal_sections')).'</dt><dd>'.count($sections).'</dd></div>'
            .'<div><dt>'.e(__('cpages_c.legal_questions')).'</dt><dd><a href="'.e(route('contact', ['locale' => $locale])).'">'.e(__('cpages_c.search_cta_secondary')).'</a></dd></div>'
            .'</dl></div>';
    @endphp
    @include('partials.cpages.hero', [
        'eyebrow' => __('cpages_c.legal_eyebrow'),
        'title' => $title,
        'lead' => __('cpages_c.legal_lead_'.$page->slug),
        'heroAside' => $aside,
    ])

    <section class="u-section cp-legal">
        <div class="u-container cp-legal__grid">
            @if (count($sections) > 1)
                <nav class="cp-legal__toc" aria-labelledby="cp-toc-title">
                    <h2 class="cp-legal__toc-title" id="cp-toc-title">{{ __('cpages_c.legal_contents') }}</h2>
                    <ol class="cp-legal__toc-list">
                        @foreach ($sections as $section)
                            <li><a href="#{{ $section['id'] }}">{{ $section['heading'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            <div class="cp-legal__body">
                @foreach ($prose as $paragraph)
                    @php($rendered = $render($paragraph['text'], $paragraph['block']))
                    <p class="cp-legal__intro" @if ($paragraph['lang']) lang="{{ $paragraph['lang'] }}" @endif>{!! $rendered !!}</p>
                @endforeach
                @foreach ($sections as $section)
                    <section class="cp-legal__section {{ $section['text'] === '' ? 'cp-legal__section--group' : '' }}" id="{{ $section['id'] }}" aria-labelledby="{{ $section['id'] }}-h" @if ($section['lang']) lang="{{ $section['lang'] }}" @endif>
                        <h2 class="cp-legal__heading" id="{{ $section['id'] }}-h">{{ $section['heading'] }}</h2>
                        @if ($section['text'] !== '')
                            @php($rendered = $render($section['text'], $section['block']))
                            <div class="cp-prose">{!! $rendered !!}</div>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>
    </section>

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages_c.legal_cta_title'),
        'ctaText' => __('cpages_c.legal_cta_text'),
        'ctaPrimary' => [__('cpages_c.search_cta_secondary'), route('contact', ['locale' => $locale])],
        'ctaSecondary' => $page->slug === 'privacy'
            ? [__('cpages_c.legal_other_terms'), route('terms', ['locale' => $locale])]
            : [__('cpages_c.legal_other_privacy'), route('privacy', ['locale' => $locale])],
    ])
@endsection
