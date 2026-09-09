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
@section('description', $authority?->name ?: __('members.detail_fallback_description', ['country' => $country->name]))

@section('content')
    <div class="c-section">
        <div class="c-page">
            <nav class="c-breadcrumb" aria-label="{{ __('members.breadcrumb') }}">
                <a href="{{ route('members', ['locale' => app()->getLocale()]) }}">{{ __('members.index_title') }}</a>
                <span aria-hidden="true">/</span>
                <span>{{ $country->name }}</span>
            </nav>

            <h1>{{ $country->name }}</h1>

            @if ($authority)
                <p class="c-lead">{{ $authority->name }}</p>
            @endif

            <dl class="c-meta">
                <div>
                    <dt>{{ __('members.detail_iso') }}</dt>
                    <dd>{{ strtoupper($country->iso_code) }}</dd>
                </div>
                @if ($authority?->acronym)
                    <div>
                        <dt>{{ __('members.detail_acronym') }}</dt>
                        <dd>{{ $authority->acronym }}</dd>
                    </div>
                @endif
                @if ($authority?->sectors->isNotEmpty())
                    <div>
                        <dt>{{ __('members.detail_sectors') }}</dt>
                        <dd>{{ $authority->sectors->pluck('name')->implode(', ') }}</dd>
                    </div>
                @endif
                @if ($country->joined_at)
                    <div>
                        <dt>{{ __('members.detail_joined') }}</dt>
                        <dd>{{ $country->joined_at->isoFormat('D MMMM YYYY') }}</dd>
                    </div>
                @endif
            </dl>

            @if ($committees->isNotEmpty())
                <h2>{{ __('members.detail_committees') }}</h2>
                <ul class="c-list-plain" role="list">
                    @foreach ($committees as $committee)
                        <li>
                            {{ $committee->name }}
                            @if ($committee->pivot?->role)
                                <span class="c-list-plain__note">{{ __('members.detail_role_'.$committee->pivot->role) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($figures->isNotEmpty())
                {{-- Only figures this state has filed. A row per indicator it
                     has not filed would turn the register's gaps into this
                     state's gaps, which is the completeness view's job and
                     not this page's. --}}
                <h2>{{ __('members.detail_figures') }}</h2>
                <div class="c-scroll">
                    <table class="c-table">
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
                                    <td>{{ $figure->period }}</td>
                                    <td>{{ $cell->display() }}</td>
                                    <td>{{ $cell->source() ?: __('members.detail_source_none') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="c-note">
                    <a href="{{ route('comparator', ['locale' => app()->getLocale(), 'countries' => $country->iso_code]) }}">{{ __('members.detail_compare', ['country' => $country->name]) }}</a>
                </p>
            @endif

            @if ($publications->isNotEmpty())
                <h2>{{ __('members.detail_publications') }}</h2>
                <ul class="c-list-plain" role="list">
                    @foreach ($publications as $publication)
                        <li>
                            <a href="{{ route('publications.show', ['locale' => app()->getLocale(), 'slug' => $publication->slug]) }}">{{ $publication->translate('title')?->value ?: $publication->slug }}</a>
                            <span class="c-list-plain__note">{{ $publication->published_on?->isoFormat('MMMM YYYY') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
