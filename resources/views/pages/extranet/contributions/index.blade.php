@extends('layouts.extranet')

{{--
    What this member state has contributed to the register. RP-03.

    This is how the register fills: from fourteen members, not from a
    Secretariat of five typing in fourteen countries' legislation.
--}}

@section('title', __('contributions.title'))
@section('note', __('contributions.note'))

@section('actions')
    <a class="a-btn a-btn--primary" href="{{ route('contributions.create', ['locale' => app()->getLocale()]) }}">{{ __('contributions.add') }}</a>
@endsection

@section('content')
    @if ($contributions->isEmpty())
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('contributions.empty_title') }}</div>
                <div class="a-empty__body">{{ __('contributions.empty_body') }}</div>
            </div>
        </div>
    @else
        <section class="a-card a-card--flush">
            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('contributions.col_instrument') }}</th>
                            <th scope="col">{{ __('contributions.col_sector') }}</th>
                            <th scope="col">{{ __('contributions.col_state') }}</th>
                            <th scope="col">{{ __('contributions.col_updated') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contributions as $instrument)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('contributions.edit', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug]) }}">{{ $instrument->translated('title', $instrument->reference_number ?? $instrument->slug) }}</a>
                                    <span class="a-table__sub">{{ $instrument->reference_number }}</span>
                                </th>
                                <td>{{ $instrument->sector?->name }}</td>
                                <td><span class="a-pill" data-status="{{ $instrument->contribution_state?->value }}">{{ __($instrument->contribution_state->labelKey()) }}</span></td>
                                <td>{{ $instrument->updated_at?->isoFormat('D MMMM YYYY') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{ $contributions->links() }}
    @endif
@endsection
