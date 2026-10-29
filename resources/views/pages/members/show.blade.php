{{--
    One member state.

    Where the map's fourteen shapes and two island chips arrive. Everything
    here is a record the platform already holds: the regulator in place, the
    sectors it is mandated over, the committees the state sits on, the
    figures it has filed and the documents it has published. No prose is
    written for this page, because invented narrative about a national
    regulator is the first thing a regulator would catch.

    Sections that have nothing to show are absent rather than empty. A
    member state with no committee seats should not carry a heading saying
    so, and a zero on a page about a regulator reads as a finding.
--}}
@extends('layouts.public')

@section('title', $country->name)
@include('partials.cpages.styles')
@section('description', $authority?->name ?: __('members.detail_fallback_description', ['country' => $country->name]))

@section('content')
    @php
        $locale = app()->getLocale();
        $tally = \App\Support\SectionCounts::forCountry($country);
        $tallyFields = [
            'indicators' => 'members.tally_indicators',
            'instruments' => 'members.tally_instruments',
            'publications' => 'members.tally_publications',
            'committees' => 'members.count_committees',
        ];
    @endphp

    {{-- Short hero (UI system): flag and name, the regulator, the four
         counts; the regulator's mark framed on the right. --}}
    <section class="u-hero cp-hero cp-memberpage-hero">
        <div class="u-container">
            <div class="u-hero__grid">
                <div class="cp-hero__text">
                    <p class="u-eyebrow"><a class="cp-memberpage__back" href="{{ route('members', ['locale' => $locale]) }}">{{ __('members.index_title') }}</a></p>
                    <h1 class="u-h1 c-member__heading cp-memberpage__title">
                        <x-flag :iso="$country->iso_code" size="lg" />
                        {{ $country->name }}
                    </h1>
                    @if ($authority)
                        <p class="u-lead">{{ $authority->name }}</p>
                    @endif
                    <dl class="u-figures">
                        @foreach ($tallyFields as $field => $key)
                            <div class="u-figure"><dd>{{ $tally[$field] }}</dd><dt>{{ trans_choice($key, $tally[$field], ['count' => $tally[$field]]) }}</dt></div>
                        @endforeach
                    </dl>
                </div>
                <div class="cp-memberpage__brand">
                    {{-- alt is empty on purpose: the authority's name is the
                         line beside it. --}}
                    @if ($authority?->logo_path)
                        <p class="c-member__logo">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($authority->logo_path) }}" alt="" loading="lazy" decoding="async">
                        </p>
                    @else
                        <span class="cp-memberpage__flag"><x-flag :iso="$country->iso_code" size="lg" /></span>
                    @endif
                    @if ($authority?->acronym && ! $authority->logo_path)
                        <p class="cp-memberpage__acronym">{{ $authority->acronym }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="u-section">
        <div class="u-container u-main-side">
            <div class="cp-memberpage__main">
                @if ($committees->isNotEmpty())
                    <h2 class="u-h2">{{ __('members.detail_committees') }}</h2>
                    <ul class="u-cols-2 cp-memberpage__committees" role="list">
                        @foreach ($committees as $committee)
                            <li class="u-panel cp-memberpage__committee">
                                <span class="cp-icon-badge" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M15 14.5c3 0 6 1.9 6 5.5"/></svg></span>
                                <span>
                                    <span class="cp-memberpage__committee-name">{{ $committee->name }}</span>
                                    @if ($committee->pivot?->role)
                                        <span class="u-pill u-pill--ok">{{ __('members.detail_role_'.$committee->pivot->role) }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($figures->isNotEmpty())
                    {{-- Only figures this state has filed. --}}
                    <h2 class="u-h2 cp-memberpage__h2">{{ __('members.detail_figures') }}</h2>
                    <div class="u-table-frame cp-cardtable">
                        <table class="u-table">
                            <caption class="c-sr">{{ __('members.detail_figures_caption', ['country' => $country->name]) }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('members.detail_indicator') }}</th>
                                    <th scope="col">{{ __('members.detail_period') }}</th>
                                    <th scope="col">{{ __('members.detail_value') }}</th>
                                    <th scope="col">{{ __('members.detail_source') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($figures as $figure)
                                    @php $cell = new \App\Support\ComparisonCell($figure->indicator, $country, $figure); @endphp
                                    <tr>
                                        <th scope="row">
                                            {{ $figure->indicator->name }}
                                            @if ($figure->indicator->unitLabel())
                                                <span class="c-table__sub">{{ $figure->indicator->unitLabel() }}</span>
                                            @endif
                                        </th>
                                        <td data-label="{{ __('members.detail_period') }}">{{ $figure->period }}</td>
                                        <td data-label="{{ __('members.detail_value') }}">{{ $cell->display() }}</td>
                                        <td data-label="{{ __('members.detail_source') }}">{{ $cell->source() ?: __('members.detail_source_none') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-indicator-note :indicators="$figures->pluck('indicator')" />
                @endif

                @if ($publications->isNotEmpty())
                    <h2 class="u-h2 cp-memberpage__h2">{{ __('members.detail_publications') }}</h2>
                    <ul class="u-cols-2 cp-memberpage__pubs" role="list">
                        @foreach ($publications as $publication)
                            <li class="u-card">
                                <div class="u-card__body">
                                    <h3 class="u-card__title"><a href="{{ route('publications.show', ['locale' => $locale, 'slug' => $publication->slug]) }}">{{ $publication->translate('title')?->value ?: $publication->slug }}</a></h3>
                                    @if ($publication->published_on)
                                        <div class="u-card__meta"><span>{{ $publication->published_on->isoFormat('MMMM YYYY') }}</span></div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($committees->isEmpty() && $figures->isEmpty() && $publications->isEmpty())
                    <div class="u-panel u-panel--tint">
                        <p class="u-text">{{ __('cpages.member_nothing_yet', ['country' => $country->name]) }}</p>
                        <a class="u-btn u-btn--secondary" href="{{ route('contact', ['locale' => $locale]) }}">{{ __('cpages.about_cta_contact') }}</a>
                    </div>
                @endif
            </div>

            <aside class="u-panel u-panel--tint cp-memberpage__facts" aria-labelledby="member-facts-title">
                <h2 class="u-h3" id="member-facts-title">{{ __('cpages.member_facts') }}</h2>
                <dl class="u-facts">
                    <div><dt>{{ __('members.detail_iso') }}</dt><dd>{{ strtoupper($country->iso_code) }}</dd></div>
                    @if ($authority?->acronym)
                        <div><dt>{{ __('members.detail_acronym') }}</dt><dd>{{ $authority->acronym }}</dd></div>
                    @endif
                    @if ($authority?->sectors->isNotEmpty())
                        <div><dt>{{ __('members.detail_sectors') }}</dt><dd>{{ $authority->sectors->pluck('name')->implode(', ') }}</dd></div>
                    @endif
                    @if ($country->joined_at)
                        <div><dt>{{ __('members.detail_joined') }}</dt><dd>{{ $country->joined_at->isoFormat('D MMMM YYYY') }}</dd></div>
                    @endif
                    @if ($country->locales() !== [])
                        <div><dt>{{ __('members.detail_language') }}</dt><dd>{{ collect($country->locales())->map(fn ($l) => \App\Support\Locales::nameOf($l))->implode(', ') }}</dd></div>
                    @endif
                    @if ($authority?->established_year)
                        <div><dt>{{ __('members.detail_established') }}</dt><dd>{{ $authority->established_year }}</dd></div>
                    @endif
                    @if ($authority?->website)
                        <div><dt>{{ __('members.detail_website') }}</dt><dd><a href="{{ $authority->website }}" rel="noopener">{{ parse_url($authority->website, PHP_URL_HOST) }}</a></dd></div>
                    @endif
                </dl>
                <a class="u-btn u-btn--primary cp-memberpage__compare" href="{{ route('comparator', ['locale' => $locale, 'countries' => $country->iso_code]) }}">{{ __('members.detail_compare', ['country' => $country->name]) }}</a>
            </aside>
        </div>
    </section>

    @include('partials.cpages.cta', [
        'ctaTitle' => __('cpages.members_cta_title'),
        'ctaText' => __('cpages.members_cta_text'),
        'ctaPrimary' => [__('cpages.members_cta_compare'), route('comparator', ['locale' => $locale, 'countries' => $country->iso_code])],
        'ctaSecondary' => [__('members.index_title'), route('members', ['locale' => $locale])],
    ])
@endsection
