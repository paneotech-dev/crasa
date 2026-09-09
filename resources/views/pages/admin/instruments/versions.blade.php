@extends('layouts.admin')

{{--
    An instrument's history.

    RP-07 has written a version on every edit since the schema was created
    and nothing read it, so an editor could see that an instrument had nine
    versions and not what any of them said. A history nobody can open is a
    count.
--}}

@section('title', __('admin.versions_title'))
@section('note', $instrument->translated('title', $instrument->slug))

@section('crumbs')
    <li><a href="{{ route('admin.resources', ['locale' => app()->getLocale(), 'resource' => 'instruments']) }}">{{ __('admin.nav_instruments') }}</a></li>
    <li aria-current="page">{{ __('admin.versions_title') }}</li>
@endsection

@section('actions')
    @if ($versions->count() >= 2)
        <a class="a-btn a-btn--primary" href="{{ route('admin.instruments.diff', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug]) }}">{{ __('admin.versions_compare_latest') }}</a>
    @endif
@endsection

@section('content')
    @if ($versions->isEmpty())
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('admin.versions_empty_title') }}</div>
                <div class="a-empty__body">{{ __('admin.versions_empty_body') }}</div>
            </div>
        </div>
    @else
        <section class="a-card a-card--flush">
            <div class="a-card__head">
                <h2>{{ __('admin.versions_held') }}</h2>
                <span class="a-card__meta">{{ trans_choice('admin.versions_count', $versions->count(), ['count' => $versions->count()]) }}</span>
            </div>

            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.versions_col_version') }}</th>
                            <th scope="col">{{ __('admin.versions_col_summary') }}</th>
                            <th scope="col">{{ __('admin.versions_col_author') }}</th>
                            <th scope="col">{{ __('admin.versions_col_when') }}</th>
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($versions as $version)
                            <tr>
                                <th scope="row">{{ __('admin.page_history_version', ['n' => $version->version_number]) }}</th>
                                <td>{{ $version->change_summary }}</td>
                                <td>{{ $version->author?->name ?? __('admin.system') }}</td>
                                <td>{{ $version->created_at?->isoFormat('D MMM YYYY, HH:mm') }}</td>
                                <td>
                                    {{-- Against the one before it, which is
                                         what somebody opening a history
                                         wants nine times out of ten. --}}
                                    @if (! $loop->last)
                                        <a class="a-btn a-btn--secondary a-btn--small"
                                           href="{{ route('admin.instruments.diff', [
                                               'locale' => app()->getLocale(),
                                               'instrument' => $instrument->slug,
                                               'to' => $version->id,
                                               'from' => $versions[$loop->index + 1]->id,
                                           ]) }}">{{ __('admin.versions_compare') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
