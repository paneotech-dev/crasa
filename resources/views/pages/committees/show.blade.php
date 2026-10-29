{{--
    One committee (C17, UI system, 29 October 2026): its mandate, the
    documents it has published in the library and its next meeting.
--}}
@extends('layouts.public')
@include('partials.cpages.styles')

@php
    $locale = app()->getLocale();
    $sectorName = $committee->sector?->name ?: __('blocks.committee_cross_sector');
    $mandate = $committee->translate('mandate') ? $committee->translated('mandate') : null;
    $seatsKnown = \App\Models\Committee::seatsRecorded();
    $seats = $seatsKnown ? $committee->countries()->count() : 0;
    $figures = array_values(array_filter([
        $publications->count() > 0 ? ['value' => $publications->count(), 'label' => trans_choice('blocks.committee_documents', $publications->count())] : null,
        $past->count() + ($next ? 1 : 0) > 0 ? ['value' => $committee->meetings()->public()->count(), 'label' => trans_choice('blocks.committee_meetings', 2)] : null,
    ]));
    $place = fn ($m) => collect([$m->venue, $m->location])->filter()->unique()->implode(', ');
@endphp

@section('title', $committee->name)
@if ($mandate)
    @section('description', \Illuminate\Support\Str::limit($mandate, 155))
@endif

@section('content')
<section class="u-hero cp-hero">
    <div class="u-container">
        <div class="u-hero__grid">
            <div>
                <p class="u-eyebrow">{{ __('cpages_a.committee_eyebrow') }}, {{ $sectorName }}</p>
                <h1 class="u-h1">{{ $committee->name }}</h1>
                @if ($mandate)<p class="u-lead">{{ $mandate }}</p>@endif
                @if ($figures !== [])
                    <dl class="u-figures">
                        @foreach ($figures as $figure)
                            <div class="u-figure"><dd>{{ $figure['value'] }}</dd><dt>{{ $figure['label'] }}</dt></div>
                        @endforeach
                    </dl>
                @endif
            </div>
            <div class="u-panel">
                <h2 class="cp-side-title">{{ __('cpages_a.next_meeting') }}</h2>
                @if ($next)
                    <div class="cp-meeting">
                        <span class="u-date"><span class="u-date__day">{{ $next->starts_on->format('j') }}</span><span class="u-date__month">{{ $next->starts_on->locale($locale)->isoFormat('MMM') }}</span></span>
                        <div>
                            <p class="cp-meeting__title"><a href="{{ route('events.show', ['locale' => $locale, 'meeting' => $next->slug]) }}">{{ $next->title }}</a></p>
                            <p class="cp-meeting__meta">{{ $next->starts_on->locale($locale)->isoFormat('D MMMM YYYY') }}@if ($place($next)), {{ $place($next) }}@endif</p>
                        </div>
                    </div>
                @else
                    <p class="u-text u-muted">{{ __('cpages_a.no_next_meeting') }}</p>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="u-section">
    <div class="u-container {{ $publications->isEmpty() ? 'cp-com-layout--empty' : 'u-main-side' }}">
        <div>
            <h2 class="u-h2">{{ __('cpages_a.documents') }}</h2>
            @if ($publications->isEmpty())
                <div class="u-panel u-panel--tint cp-com-empty">
                    <p class="u-text">{{ __('cpages_a.no_documents') }}</p>
                    <a class="u-btn u-btn--secondary" href="{{ route('publications', ['locale' => $locale]) }}">{{ __('cpages_a.browse_library') }}</a>
                </div>
            @else
                <ul class="u-cols-2 cp-doc-grid" role="list">
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
                                    <span class="cp-doc-thumb" aria-hidden="true">{{ $document ? strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION)) : $publication->typeLabel() }}</span>
                                @endif
                            </figure>
                            <div class="u-card__body">
                                <span class="u-card__label">{{ $publication->typeLabel() }}</span>
                                <h3 class="u-card__title"><a href="{{ route('publications.show', ['locale' => $locale, 'slug' => $publication->slug]) }}" @if ($ptitle?->isFallback()) lang="{{ $ptitle->locale }}" @endif>{{ $ptitle?->value ?? $publication->slug }}</a></h3>
                                <div class="u-card__meta">
                                    @if ($publication->published_on)<span>{{ $publication->published_on->format('Y') }}</span>@endif
                                    @if ($document)<span>{{ $document->readableSize() }}</span><span>{{ strtoupper($document->locale) }}</span>@endif
                                </div>
                            </div>
                            @if ($document)
                                <div class="u-card__actions">
                                    <a class="u-btn u-btn--secondary" href="{{ route('documents.download', ['locale' => $locale, 'document' => $document->id]) }}">{{ __('cpages_a.download') }}</a>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <aside class="cp-side-stack" aria-label="{{ __('cpages_a.about_committee') }}">
            <div class="u-panel u-panel--tint">
                <h2 class="cp-side-title">{{ __('cpages_a.about_committee') }}</h2>
                <dl class="u-facts">
                    <div><dt>{{ __('cpages_a.sector') }}</dt><dd>{{ $sectorName }}</dd></div>
                    @if ($committee->chairAuthority)
                        <div><dt>{{ __('cpages_a.chair') }}</dt><dd>{{ $committee->chairAuthority->name }}</dd></div>
                    @endif
                    @if ($seatsKnown && $seats > 0)
                        <div><dt>{{ __('cpages_a.seats') }}</dt><dd>{{ $seats }}</dd></div>
                    @endif
                </dl>
            </div>
            @if ($past->isNotEmpty())
                <div class="u-panel">
                    <h2 class="cp-side-title">{{ __('cpages_a.past_meetings') }}</h2>
                    @foreach ($past as $meeting)
                        <div class="cp-meeting">
                            @if ($meeting->starts_on && ! $meeting->date_unknown)
                                <span class="u-date"><span class="u-date__day">{{ $meeting->starts_on->format('j') }}</span><span class="u-date__month">{{ $meeting->starts_on->locale($locale)->isoFormat('MMM') }}</span></span>
                            @endif
                            <div>
                                <p class="cp-meeting__title"><a href="{{ route('events.show', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ $meeting->title }}</a></p>
                                <p class="cp-meeting__meta">@if ($meeting->starts_on && ! $meeting->date_unknown){{ $meeting->starts_on->format('Y') }}@endif @if ($place($meeting)){{ $place($meeting) }}@endif</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if ($others->isNotEmpty())
                <nav class="u-panel" aria-label="{{ __('cpages_a.other_committees') }}">
                    <h2 class="cp-side-title">{{ __('cpages_a.other_committees') }}</h2>
                    <ul class="cp-chips" role="list">
                        @foreach ($others as $other)
                            <li><a class="u-chip" href="{{ route('committees.show', ['locale' => $locale, 'committee' => $other->slug]) }}">{{ $other->name }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </aside>
    </div>
</section>

@include('partials.cpages.cta', [
    'ctaTitle' => __('cpages_a.committee_cta_title', ['name' => $committee->name]),
    'ctaText' => __('cpages_a.committee_cta_text'),
    'ctaPrimary' => [__('cpages_a.open_in_library'), route('publications', ['locale' => $locale, 'committee' => $committee->slug])],
    'ctaSecondary' => [__('cpages_a.see_events'), route('events', ['locale' => $locale])],
])
@endsection
