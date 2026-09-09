@extends('layouts.admin')

@section('title', __('admin.samples_title'))
@section('note', __('admin.samples_note'))

@section('crumbs')
    <li aria-current="page">{{ __('admin.samples_title') }}</li>
@endsection

@section('content')
    @if ($counts === [])
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('admin.samples_clean_title') }}</div>
                <div class="a-empty__body">{{ __('admin.samples_clean_body') }}</div>
            </div>
        </div>
    @else
        <div class="a-alert a-alert--pending" role="status">
            <strong>{{ trans_choice('admin.sample_present', $total, ['count' => number_format($total)]) }}</strong>
            {{ __('admin.sample_body') }}
        </div>

        <section class="a-card a-card--flush">
            {{-- Wide tables scroll inside the card rather than being
                 clipped by it. --}}
            <div class="a-tablewrap">
            <table class="a-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.samples_col_type') }}</th>
                        <th scope="col" class="a-table__num">{{ __('admin.samples_col_rows') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($counts as $type => $rows)
                        <tr>
                            <th scope="row">{{ $type }}</th>
                            <td class="a-table__num">{{ number_format($rows) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </section>
    @endif

    {{-- No purge button, deliberately. The strip removes every flagged row
         in one transaction and cannot be undone. --}}
    <section class="a-card">
        <div class="a-card__head"><h2>{{ __('admin.samples_how_title') }}</h2></div>
        <p class="a-hint">{{ __('admin.samples_how_body') }}</p>
        <p class="a-sample__how"><code>php artisan content:strip-samples</code></p>
    </section>
@endsection
