@extends('layouts.admin')

{{--
    Content > Taxonomies. Four lists behind four tabs: news categories,
    publication types, sectors and tags. Each list is in its public order,
    with the number of records using each term, because that number is
    what decides whether a term can be deleted.
--}}

@section('title', __('d3_taxo.title'))
@section('note', __('d3_taxo.note'))

@section('crumbs')
    <li><a href="{{ route('admin.dashboard') }}">{{ __('admin.dashboard') }}</a></li>
    <li aria-current="page">{{ __('d3_taxo.title') }}</li>
@endsection

@section('actions')
    <a class="a-btn a-btn--primary" href="{{ route('admin.taxonomies.create', ['kind' => $kind]) }}">{{ __('d3_taxo.new_'.$kind) }}</a>
@endsection

@section('content')
    <nav class="a-tabs" aria-label="{{ __('d3_taxo.title') }}">
        @foreach ($kinds as $name)
            <a href="{{ route('admin.taxonomies', ['kind' => $name]) }}" @if ($kind === $name) aria-current="page" @endif>{{ __('d3_taxo.kind_'.$name) }}</a>
        @endforeach
    </nav>

    <section class="a-resource">
        <div class="a-resource__head">
            <h2 class="a-resource__title">{{ __('d3_taxo.kind_'.$kind) }}</h2>
            <div class="a-resource__note">{{ __('d3_taxo.used_by_'.$kind) }}</div>
        </div>

        @if ($terms->isEmpty())
            <div class="a-card">
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('d3_taxo.empty_title') }}</div>
                    <div class="a-empty__body">{{ __('d3_taxo.empty_body') }}</div>
                </div>
            </div>
        @else
            <div class="a-card a-card--flush">
                <div class="a-tablewrap">
                    <table class="a-table">
                        <caption class="c-sr">{{ __('d3_taxo.kind_'.$kind) }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('d3_taxo.col_order') }}</th>
                                <th scope="col">{{ __('d3_taxo.col_name') }}</th>
                                <th scope="col">{{ __('d3_taxo.col_slug') }}</th>
                                @if (\App\Support\Taxonomies::hasColour($kind))
                                    <th scope="col">{{ __('d3_taxo.col_badge') }}</th>
                                @endif
                                <th scope="col">{{ __('d3_taxo.col_used') }}</th>
                                <th scope="col">{{ __('admin.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($terms as $term)
                                @php $label = \App\Support\Taxonomies::label($kind, $term); @endphp
                                <tr>
                                    <td>{{ $term->sort_order }}</td>
                                    <th scope="row"><a href="{{ route('admin.taxonomies.edit', ['kind' => $kind, 'id' => $term->getKey()]) }}">{{ $label }}</a></th>
                                    <td><code>{{ $term->slug }}</code></td>
                                    @if (\App\Support\Taxonomies::hasColour($kind))
                                        <td><span class="tx-badge" style="{{ \App\Support\BadgeColours::style($term->colour) }}">{{ $label }}</span></td>
                                    @endif
                                    <td>{{ $usage[$term->getKey()] ?? 0 }}</td>
                                    <td class="a-table__actions">
                                        <a href="{{ route('admin.taxonomies.edit', ['kind' => $kind, 'id' => $term->getKey()]) }}">{{ __('admin.edit') }}<span class="c-sr">: {{ $label }}</span></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>
    @include('pages.admin.taxonomies._styles')
@endsection
