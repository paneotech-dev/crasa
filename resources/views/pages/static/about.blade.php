{{--
    About (C16, 29 October 2026). The same block records the composed page
    reads, laid out for the page: who we are beside a key-facts card, focus,
    vision and mission as cards, history as a timeline, governance as four
    cards, the Executive Committee and the Secretariat as people grids. The
    text stays editable in the back office; a block with no text is skipped.
--}}
@extends('layouts.public')

@php
    use App\Support\StructuredText;

    $title = $page->translate('title');
    $description = $page->translate('meta_description');
    $backOffice = auth()->user()?->can('enter-back-office') ?? false;
    $renderer = app(\App\Services\ContentRenderer::class);

    $blocks = $page->visibleBlocks->keyBy(fn ($b) => $b->anchor ?: 'block-'.$b->id);
    $text = function (?string $anchor) use ($blocks, $backOffice) {
        $block = $anchor ? $blocks->get($anchor) : null;
        if ($block === null || ($block->isUnwritten() && ! $backOffice)) {
            return null;
        }
        $body = $block->translate('body');
        $value = $body === null ? null : ($backOffice ? $body->value : \App\Support\PublishedText::withoutDraftingNotes($body->value)['text']);

        return ['block' => $block, 'heading' => $block->translate('heading')?->value, 'body' => $value, 'lang' => $body?->isFallback() ? $body->locale : null];
    };
    $initials = function (string $name): string {
        $words = array_values(array_filter(preg_split('/\s+/', preg_replace('/^(Mr|Mrs|Ms|Dr|Prof|Eng|Hon)\.?\s+/i', '', trim($name)))));
        $first = mb_substr($words[0] ?? '', 0, 1);
        $last = count($words) > 1 ? mb_substr(end($words), 0, 1) : '';

        return mb_strtoupper($first.$last);
    };

    $who = $text('who-we-are');
    $sadc = $text('sadc');
    $vmh = $text('vision-mission-history');
    $governance = $text('governance');
    $executive = $text('executive-committee');
    $secretariat = $text('secretariat');

    $vmhSections = $vmh && $vmh['body'] ? StructuredText::sections($vmh['body'])['sections'] : [];
    // The first three headed sections are focus, vision and mission; what
    // follows is the history, which the timeline below tells in short.
    $pillars = array_slice($vmhSections, 0, 3);
    $governanceParts = $governance && $governance['body'] ? StructuredText::sections($governance['body']) : ['intro' => [], 'sections' => []];
    $executiveParts = $executive && $executive['body'] ? StructuredText::people($executive['body']) : ['intro' => [], 'people' => []];
    $secretariatParts = $secretariat && $secretariat['body'] ? StructuredText::people($secretariat['body']) : ['intro' => [], 'people' => []];

    $counts = \App\Support\SectionCounts::forPage('about');
    $memberCount = $counts[0]['value'] ?? null;
    $cta = $page->visibleBlocks->first(fn ($b) => $b->type() === \App\Enums\BlockType::CallToAction);
    $ctaHeadline = $cta?->translate('headline')?->value;
    $ctaLabel = $cta?->translate('cta_label')?->value;
    $ctaUrl = $cta ? \App\Support\Navigation::resolve($cta->setting('cta_url')) : null;
    $locale = app()->getLocale();

@endphp

@section('title', $title?->value ?? __('nav.about'))
@if ($description)
    @section('description', $description->value)
@endif

@include('partials.cpages.styles')

@section('content')
    @include('partials.cpages.hero', [
        'eyebrow' => $title?->value,
        'title' => $page->translate('heading')?->value ?: ($title?->value ?? ''),
        'lead' => $page->translate('standfirst')?->value,
        'figures' => $counts,
        'photoName' => 'institutional',
        'photoAlt' => '',
    ])

    @if ($who)
        <section class="u-section cp-about-who" id="who-we-are" aria-labelledby="who-we-are-title">
            <div class="u-container u-main-side">
                <div class="cp-about-who__text">
                    <h2 class="u-h2" id="who-we-are-title">{{ $who['heading'] }}</h2>
                    <div class="cp-prose cp-prose--lead" @if ($who['lang']) lang="{{ $who['lang'] }}" @endif>
                        {!! $renderer->render($who['body'] ?? '', 'block:'.$who['block']->id) !!}
                    </div>
                    @if ($sadc)
                        <div class="cp-about-sadc" id="sadc">
                            <h3 class="u-h3">{{ $sadc['heading'] }}</h3>
                            <div class="cp-prose" @if ($sadc['lang']) lang="{{ $sadc['lang'] }}" @endif>
                                {!! $renderer->render($sadc['body'] ?? '', 'block:'.$sadc['block']->id) !!}
                            </div>
                        </div>
                    @endif
                </div>
                <aside class="u-panel u-panel--tint cp-about-facts" aria-labelledby="about-facts-title">
                    <h2 class="u-h3" id="about-facts-title">{{ __('cpages.about_facts') }}</h2>
                    <dl class="u-facts">
                        <div><dt>{{ __('cpages.about_fact_status') }}</dt><dd>{{ __('cpages.about_fact_status_value') }}</dd></div>
                        <div><dt>{{ __('cpages.about_fact_since') }}</dt><dd>1997</dd></div>
                        @if ($memberCount)
                            <div><dt>{{ __('cpages.about_fact_members') }}</dt><dd>{{ $memberCount }}</dd></div>
                        @endif
                        <div><dt>{{ __('cpages.about_fact_sectors') }}</dt><dd>{{ __('cpages.about_fact_sectors_value') }}</dd></div>
                        <div><dt>{{ __('cpages.about_fact_seat') }}</dt><dd>{{ __('cpages.about_fact_seat_value') }}</dd></div>
                    </dl>
                </aside>
            </div>
        </section>
    @endif

    @if ($pillars !== [])
        <section class="u-section u-section--tint" id="vision-mission-history" aria-labelledby="pillars-title">
            <div class="u-container">
                <h2 class="u-h2" id="pillars-title">{{ __('cpages.about_pillars') }}</h2>
                <div class="u-cols-3 cp-about-pillars" @if ($vmh['lang']) lang="{{ $vmh['lang'] }}" @endif>
                    @foreach ($pillars as $i => $pillar)
                        <article class="u-card cp-about-pillar">
                            <div class="u-card__body">
                                <span class="cp-icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">@include('partials.cpages.icon', ['name' => ['target', 'eye', 'flag'][$i] ?? 'target'])</svg></span>
                                <h3 class="u-card__title">{{ $pillar['heading'] }}</h3>
                                @foreach ($pillar['paragraphs'] as $paragraph)
                                    <p class="u-card__text">{!! $renderer->render($paragraph, 'block:'.$vmh['block']->id) !!}</p>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="u-section" id="history" aria-labelledby="history-title">
        <div class="u-container cp-about-history">
            <div>
                <h2 class="u-h2" id="history-title">{{ __('cpages.about_history') }}</h2>
                <p class="u-lead">{{ __('cpages.about_history_lead') }}</p>
                @if ($sadc === null)
                    <p><a class="u-btn u-btn--secondary" href="https://www.sadc.int">SADC</a></p>
                @endif
            </div>
            <ol class="u-timeline cp-about-timeline">
                @foreach ([1, 2, 3, 4] as $n)
                    <li>
                        <span class="u-timeline__date">{{ __('cpages.about_h'.$n.'_date') }}</span>
                        <h3 class="u-timeline__title">{{ __('cpages.about_h'.$n.'_title') }}</h3>
                        <p class="u-text u-muted">{{ __('cpages.about_h'.$n.'_text') }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    @if ($governance)
        <section class="u-section u-section--blue" id="governance" aria-labelledby="governance-title">
            <span id="structure" class="cp-anchor"></span>
            <div class="u-container">
                <div class="cp-section-head">
                    <h2 class="u-h2" id="governance-title">{{ $governance['heading'] }}</h2>
                </div>
                <div @if ($governance['lang']) lang="{{ $governance['lang'] }}" @endif>
                    @foreach ($governanceParts['intro'] as $paragraph)
                        <p class="u-lead">{!! $renderer->render($paragraph, 'block:'.$governance['block']->id) !!}</p>
                    @endforeach
                    @if ($governanceParts['sections'] !== [])
                        <div class="u-cols-4 cp-about-gov">
                            @foreach ($governanceParts['sections'] as $i => $section)
                                <article class="u-card">
                                    <div class="u-card__body">
                                        <span class="cp-icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">@include('partials.cpages.icon', ['name' => ['building', 'people', 'grid', 'check'][$i % 4]])</svg></span>
                                        <h3 class="u-card__title cp-gov__title">{{ $section['heading'] }}</h3>
                                        @foreach ($section['paragraphs'] as $paragraph)
                                            <p class="u-card__text">{!! $renderer->render($paragraph, 'block:'.$governance['block']->id) !!}</p>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @elseif ($governance['body'])
                        <div class="cp-prose">{!! $renderer->render($governance['body'], 'block:'.$governance['block']->id) !!}</div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @foreach ([['executive-committee', $executive, $executiveParts, 'about_people_exec'], ['secretariat', $secretariat, $secretariatParts, 'about_people_sec']] as [$anchor, $part, $parts, $peopleLabel])
        @continue($part === null)
        <section class="u-section {{ $anchor === 'secretariat' ? 'u-section--pale' : '' }}" id="{{ $anchor }}" aria-labelledby="{{ $anchor }}-title">
            <div class="u-container cp-about-people" @if ($part['lang']) lang="{{ $part['lang'] }}" @endif>
                <div class="cp-about-people__text">
                    <h2 class="u-h2" id="{{ $anchor }}-title">{{ $part['heading'] }}</h2>
                    @if ($parts['people'] === [] && $part['body'])
                        <div class="cp-prose">{!! $renderer->render($part['body'], 'block:'.$part['block']->id) !!}</div>
                    @else
                        @foreach (array_slice($parts['intro'], 0, 1) as $paragraph)
                            <p class="u-text">{!! $renderer->render($paragraph, 'block:'.$part['block']->id) !!}</p>
                        @endforeach
                        @if (count($parts['intro']) > 1)
                            <details class="u-acc cp-about-more">
                                <summary>{{ __('cpages.about_more_exec') }}</summary>
                                <div class="u-acc__body cp-prose">
                                    @foreach (array_slice($parts['intro'], 1) as $paragraph)
                                        <p>{!! $renderer->render($paragraph, 'block:'.$part['block']->id) !!}</p>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    @endif
                </div>
                @if ($parts['people'] !== [])
                    <ul class="u-people cp-about-people__grid" role="list" aria-label="{{ __('cpages.'.$peopleLabel) }}">
                        @foreach ($parts['people'] as $person)
                            <li class="u-person">
                                <span class="u-avatar" aria-hidden="true">{{ $initials($person['name']) }}</span>
                                <span>
                                    <span class="u-person__name">{{ $person['name'] }}</span>
                                    <span class="u-person__role">{{ $person['title'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endforeach

    @foreach (['partnerships', 'strategic-priorities'] as $anchor)
        @php $extra = $text($anchor); @endphp
        @if ($extra && $extra['body'])
            <section class="u-section" id="{{ $anchor }}">
                <div class="u-container u-cols-2">
                    <h2 class="u-h2">{{ $extra['heading'] }}</h2>
                    <div class="cp-prose" @if ($extra['lang']) lang="{{ $extra['lang'] }}" @endif>{!! $renderer->render($extra['body'], 'block:'.$extra['block']->id) !!}</div>
                </div>
            </section>
        @endif
    @endforeach

    @include('partials.cpages.cta', [
        'ctaTitle' => $ctaHeadline ?: __('cpages.about_fact_members'),
        'ctaText' => $cta?->translate('body')?->value,
        'ctaPrimary' => $ctaLabel && $ctaUrl ? [$ctaLabel, $ctaUrl] : [__('nav.members'), route('members', ['locale' => $locale])],
        'ctaSecondary' => [__('cpages.about_cta_contact'), route('contact', ['locale' => $locale])],
    ])
@endsection
