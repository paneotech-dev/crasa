@extends('layouts.admin')

@section('title', $report->title)
@section('note', $compiled['subtitle'])

@section('crumbs')
    <li><a href="{{ route('admin.reports', ['locale' => app()->getLocale()]) }}">{{ __('reports.title') }}</a></li>
    <li aria-current="page">{{ $report->title }}</li>
@endsection

@section('actions')
    <a class="a-btn a-btn--primary" href="{{ route('admin.reports.pdf', ['locale' => app()->getLocale(), 'report' => $report->id]) }}">{{ __('reports.download_pdf') }}</a>
@endsection

@section('content')
    @if ($compiled['sample'])
        <div class="a-band">
            <strong>{{ __('comparator.sample_title') }}</strong>
            {{ __('comparator.sample_body') }}
        </div>
    @endif

    {{-- The brief, on screen. The print stylesheet turns this into the
         same document the PDF carries, so a Secretariat officer can print
         from the browser without exporting first. --}}
    <article class="r-brief">
        <header class="r-brief__head">
            <p class="r-brief__kicker">{{ __('reports.cover_kicker') }}</p>
            <h2>{{ $report->title }}</h2>
            <p class="r-brief__subtitle">{{ $compiled['subtitle'] }}</p>
            <p class="r-brief__meta">
                {{ __('reports.cover_generated', ['date' => now()->isoFormat('D MMMM YYYY')]) }} —
                {{ __('reports.cover_reported', ['reported' => $compiled['reported'], 'total' => $compiled['total']]) }}
            </p>
        </header>

        @if ($compiled['rows']->isEmpty())
            <div class="a-empty">
                <div class="a-empty__title">{{ __('reports.empty_subject_title') }}</div>
                <div class="a-empty__body">{{ __('reports.empty_subject_body') }}</div>
            </div>
        @else
            <div class="a-tablewrap">
                <table class="a-table r-brief__table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('reports.col_subject') }}</th>
                            <th scope="col" data-numeric>{{ __('comparator.col_value') }}</th>
                            <th scope="col">{{ __('reports.col_chart') }}</th>
                            <th scope="col">{{ __('comparator.col_provenance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($compiled['rows'] as $row)
                            <tr>
                                <th scope="row">{{ $row['label'] }}</th>
                                <td data-numeric @unless ($row['reported']) data-missing @endunless>{{ $row['display'] }}</td>
                                <td>
                                    {{-- Every bar states its figure beside it,
                                         so the chart reads without colour and
                                         needs no table equivalent: it is one. --}}
                                    <span class="r-bar">
                                        @if ($row['numeric'] !== null && $range['max'] > $range['min'])
                                            <span class="r-bar__fill" style="width:{{ max(2, round(($row['numeric'] - $range['min']) / ($range['max'] - $range['min']) * 100)) }}%"></span>
                                        @endif
                                    </span>
                                </td>
                                <td data-quiet>
                                    {{ $row['source'] ?? '' }}{{ $row['period'] ? ', '.$row['period'] : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <footer class="r-brief__foot">
            <p>{{ __('reports.foot_note', ['date' => now()->isoFormat('D MMMM YYYY')]) }}</p>
            <p class="r-brief__permalink">{{ route('admin.reports.show', ['locale' => $report->locale, 'report' => $report->id]) }}</p>
        </footer>
    </article>

    <form method="POST" action="{{ route('admin.reports.destroy', ['locale' => app()->getLocale(), 'report' => $report->id]) }}"
          onsubmit="return confirm('{{ __('reports.delete_confirm') }}')" class="a-form">
        @csrf
        @method('DELETE')
        <div class="a-form__actions">
            <button type="submit" class="a-btn a-btn--danger">{{ __('reports.delete') }}</button>
        </div>
    </form>
@endsection
