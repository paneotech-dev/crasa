{{-- One project (S8, UI system, 29 October 2026): framed photograph, a
     facts panel, the description, then what else the platform holds in the
     same sector. Fields the record does not hold are left out. --}}
@extends('layouts.public')
@include('partials.cpages.styles')

@php
    $locale = app()->getLocale();
    $title = $project->translate('title');
    $summary = $project->translate('summary');
    $body = $project->translate('body');
    $renderer = app(\App\Services\ContentRenderer::class);
    $current = $project->isCurrent();
    $partners = $project->partner ? array_filter(array_map('trim', explode(',', $project->partner))) : [];
    $countries = $project->countryList();
    $milestones = $project->milestoneList();
    // A body that is only an image credit and empty paragraphs is not a
    // description. Image credits are dropped from the text a reader sees.
    $bodyHtml = $body ? preg_replace('~<p>\s*(Picture|Image) Source:.*?</p>|<p>(\s|&nbsp;|\x{200D})*</p>~iu', '', $body->value) : null;
    $bodyHtml = $bodyHtml !== null && trim(strip_tags($bodyHtml), " \t\n\r\x{200D}") !== '' ? $bodyHtml : null;
    $quote = $summary ? trim($summary->value, " \t\n\"'\u{201C}\u{201D}\u{00AB}\u{00BB}") : null;
    $period = null;
    if ($project->starts_on) {
        $period = $project->starts_on->locale($locale)->isoFormat('MMMM YYYY');
        if ($project->ends_on) { $period = __('cpages_a.period_range', ['start' => $period, 'end' => $project->ends_on->locale($locale)->isoFormat('MMMM YYYY')]); }
    }
@endphp

@section('title', $title?->value ?? __('projects.title'))
@if ($summary)
    @section('description', \Illuminate\Support\Str::limit($quote, 155))
@endif

@section('content')
<section class="u-hero cp-hero">
    <div class="u-container">
        <div class="u-hero__grid">
            <div>
                <p class="u-eyebrow"><a class="cp-eyebrow-link" href="{{ route('projects', ['locale' => $locale]) }}">{{ __('projects.title') }}</a>@if ($project->sector), {{ $project->sector->name }}@endif</p>
                @if ($title)
                    <h1 class="u-h1" @if ($title->isFallback()) lang="{{ $title->locale }}" @endif>{{ $title->value }}</h1>
                @endif
                @if ($quote)
                    <p class="u-lead" @if ($summary->isFallback()) lang="{{ $summary->locale }}" @endif>{{ $quote }}</p>
                @endif
                <div class="u-actions cp-proj-hero__pills">
                    <span class="u-pill {{ $current ? 'u-pill--ok' : 'u-pill--muted' }}">
                        @if ($current)
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="12" cy="12" r="4"/></svg>
                        @else
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>
                        @endif
                        {{ $current ? __('cpages_a.status_current') : __('cpages_a.status_completed') }}
                    </span>
                </div>
                @if ($title?->isFallback())
                    <x-fallback-notice />
                @endif
            </div>
            <figure class="u-hero__photo cp-proj-photo">
                @if ($project->hero_image_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($project->hero_image_path) }}" alt="" loading="eager" fetchpriority="high" decoding="async" width="800" height="600">
                @else
                    <span class="cp-placeholder"><x-sector-icon :sector="$project->sector?->slug" /><span>{{ $project->sector?->name ?? __('projects.title') }}</span></span>
                @endif
            </figure>
        </div>
    </div>
</section>

<section class="u-section">
    <div class="u-container {{ $bodyHtml ? 'u-main-side' : '' }}">
        @if ($bodyHtml)
        <div>
            @if ($bodyHtml)
                <h2 class="u-h2">{{ __('cpages_a.description') }}</h2>
                <div class="cp-prose cp-proj-body @if (mb_strlen(strip_tags($bodyHtml)) > 1200) cp-proj-body--cols @endif" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                    {{-- Scraped from the legacy site, so sanitised before printing. --}}
                    {!! $renderer->render($bodyHtml, 'project:'.$project->id) !!}
                </div>
            @endif
        </div>
        @endif

        <aside class="{{ $bodyHtml ? 'cp-side-stack' : ($project->documents->isNotEmpty() ? 'u-cols-2 cp-proj-facts-wide' : 'cp-proj-facts-wide') }}" aria-label="{{ __('cpages_a.facts') }}">
            <div class="u-panel u-panel--tint">
                <h2 class="cp-side-title">{{ __('cpages_a.facts') }}</h2>
                <dl class="u-facts">
                    <div><dt>{{ __('projects.sector') }}</dt><dd>{{ $project->sector?->name ?? __('blocks.committee_cross_sector') }}</dd></div>
                    @if ($partners !== [])
                        <div><dt>{{ __('cpages_a.partners') }}</dt><dd><ul class="cp-proj-partners" role="list">@foreach ($partners as $partner)<li>{{ $partner }}</li>@endforeach</ul></dd></div>
                    @endif
                    @if ($project->programme)
                        <div><dt>{{ __('evening.project_programme') }}</dt><dd>{{ $project->programme }}</dd></div>
                    @endif
                    @if ($project->funding_partner)
                        <div><dt>{{ __('evening.project_funding_partner') }}</dt><dd>{{ $project->funding_partner }}</dd></div>
                    @endif
                    @if ($period)
                        <div><dt>{{ __('cpages_a.period') }}</dt><dd>{{ $period }}</dd></div>
                    @endif
                    @if ($countries !== [])
                        <div><dt>{{ __('evening.project_countries') }}</dt><dd>{{ implode(', ', $countries) }}</dd></div>
                    @endif
                    @if ($project->leadCommittee)
                        <div><dt>{{ __('evening.project_lead_committee') }}</dt><dd><a href="{{ route('committees.show', ['locale' => $locale, 'committee' => $project->leadCommittee]) }}">{{ $project->leadCommittee->name }}</a></dd></div>
                    @endif
                    <div><dt>{{ __('projects.title') }}</dt><dd>{{ $current ? __('cpages_a.status_current') : __('cpages_a.status_completed') }}</dd></div>
                </dl>
            </div>
            @if ($project->documents->isNotEmpty())
                <div class="u-panel">
                    <h2 class="cp-side-title">{{ __('projects.documents') }}</h2>
                    <ul class="cp-proj-docs" role="list">
                        @foreach ($project->documents as $document)
                            <li><a href="{{ route('documents.download', ['locale' => $locale, 'document' => $document->id]) }}">{{ $document->original_filename }}</a> <span class="u-muted">{{ $document->readableSize() }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</section>

@if ($milestones !== [])
    <section class="u-section u-section--tint" aria-labelledby="proj-milestones-h">
        <div class="u-container">
            <h2 class="u-h2" id="proj-milestones-h">{{ __('evening.project_milestones') }}</h2>
            <ol class="u-timeline cp-proj-milestones">
                @foreach ($milestones as $step)
                    <li @if ($step['lang']) lang="{{ $step['lang'] }}" @endif>
                        @if ($step['when'])<p class="u-timeline__date">{{ $step['when'] }}</p>@endif
                        <p class="u-timeline__title">{{ $step['label'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif

@if ($publications->isNotEmpty())
    <section class="u-section u-section--blue">
        <div class="u-container">
            <h2 class="u-h2">{{ __('cpages_a.related_publications') }}</h2>
            <p class="u-text u-muted cp-proj-related-note">{{ __('cpages_a.same_sector', ['sector' => $project->sector->name]) }}</p>
            <ul class="u-cols-3 cp-doc-grid" role="list">
                @foreach ($publications as $publication)
                    @php
                        $ptitle = $publication->translate('title');
                        $document = $publication->primaryDocument();
                    @endphp
                    <li class="u-card">
                        <figure class="u-card__media u-card__media--doc">
                            @if ($document?->thumbnail_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->thumbnail_path) }}" alt="" loading="lazy" width="320" height="240" style="object-fit: contain">
                            @else
                                <span class="cp-doc-thumb" aria-hidden="true">{{ $document ? strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION)) : __($publication->publication_type->labelKey()) }}</span>
                            @endif
                        </figure>
                        <div class="u-card__body">
                            <span class="u-card__label">{{ __($publication->publication_type->labelKey()) }}</span>
                            <h3 class="u-card__title"><a href="{{ route('publications.show', ['locale' => $locale, 'slug' => $publication->slug]) }}" @if ($ptitle?->isFallback()) lang="{{ $ptitle->locale }}" @endif>{{ $ptitle?->value ?? $publication->slug }}</a></h3>
                            <div class="u-card__meta">
                                @if ($publication->published_on)<span>{{ $publication->published_on->format('Y') }}</span>@endif
                                @if ($document)<span>{{ $document->readableSize() }}</span>@endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

@if ($news->isNotEmpty())
    <section class="u-section">
        <div class="u-container">
            <h2 class="u-h2">{{ __('cpages_a.related_news') }}</h2>
            <p class="u-text u-muted cp-proj-related-note">{{ __('cpages_a.same_sector', ['sector' => $project->sector->name]) }}</p>
            <ul class="u-cols-3 cp-doc-grid" role="list">
                @foreach ($news as $item)
                    @php
                        $ntitle = $item->translate('title');
                        $photo = $item->hero_image_path ? \App\Support\ResizedImage::attributes($item->hero_image_path, 'card') : null;
                    @endphp
                    <li class="u-card">
                        <figure class="u-card__media">
                            @if ($photo)
                                <img src="{{ $photo['src'] }}" @if (! empty($photo['srcset'])) srcset="{{ $photo['srcset'] }}" sizes="(max-width: 640px) 100vw, 380px" @endif alt="" loading="lazy" decoding="async" @if (! empty($photo['width'])) width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" @endif>
                            @else
                                <span class="cp-placeholder"><x-sector-icon :sector="$project->sector?->slug" /><span>{{ __('nav.newsroom') }}</span></span>
                            @endif
                        </figure>
                        <div class="u-card__body">
                            <h3 class="u-card__title"><a href="{{ route('newsroom.show', ['locale' => $locale, 'slug' => $item->slug]) }}" @if ($ntitle?->isFallback()) lang="{{ $ntitle->locale }}" @endif>{{ $ntitle?->value ?? $item->slug }}</a></h3>
                            @if ($item->published_at && ! $item->date_unknown)
                                <div class="u-card__meta"><span>{{ $item->published_at->locale($locale)->isoFormat('D MMMM YYYY') }}</span></div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

@if ($related->isNotEmpty())
    <section class="u-section {{ $news->isNotEmpty() ? 'u-section--pale' : '' }}">
        <div class="u-container">
            <div class="cp-section-head">
                <h2 class="u-h2">{{ __('projects.other_projects') }}</h2>
                <a class="u-btn u-btn--ghost" href="{{ route('projects', ['locale' => $locale]) }}">{{ __('cpages_a.all_projects') }}</a>
            </div>
            @php $seen = [$project->hero_image_path]; @endphp
            <ul class="u-cols-3 cp-proj-grid" role="list">
                @foreach ($related as $other)
                    @php
                        $image = $other->hero_image_path && ! in_array($other->hero_image_path, $seen, true) ? $other->hero_image_path : null;
                        if ($image) { $seen[] = $image; }
                    @endphp
                    @include('pages.projects._card', ['project' => $other, 'image' => $image])
                @endforeach
            </ul>
        </div>
    </section>
@endif

@include('partials.cpages.cta', [
    'ctaTitle' => __('cpages_a.project_cta_title'),
    'ctaText' => __('cpages_a.project_cta_text'),
    'ctaPrimary' => [__('cpages_a.all_projects'), route('projects', ['locale' => $locale])],
    'ctaSecondary' => [__('cpages_a.browse_library'), route('publications', ['locale' => $locale])],
])
@endsection
