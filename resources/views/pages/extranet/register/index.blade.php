@extends('layouts.extranet')

{{--
    The harmonised register, read by any signed-in member.

    Not country-scoped, deliberately: it exists so that fourteen regulators
    can read each other's instruments, which is the harmonisation mandate.
    Contributing and editing are scoped; reading is not.
--}}

@section('title', __('register.title'))
@section('note', __('register.note'))

@section('content')
    <nav class="a-filters" aria-label="{{ __('register.filter_sector') }}">
        <a @class(['a-chip', 'is-current' => $sector === null])
           href="{{ route('register', ['locale' => app()->getLocale()]) }}">{{ __('register.all_sectors') }}</a>
        @foreach ($sectors as $option)
            <a @class(['a-chip', 'is-current' => $sector === $option->slug])
               href="{{ route('register', ['locale' => app()->getLocale(), 'sector' => $option->slug]) }}">{{ $option->name }}</a>
        @endforeach
        {{-- In force by default; repealed instruments only when asked. --}}
        <a @class(['a-chip', 'is-current' => $includeRepealed])
           @if ($includeRepealed) aria-current="true" @endif
           href="{{ request()->fullUrlWithQuery(['repealed' => $includeRepealed ? null : 1, 'page' => null]) }}">{{ __('office.register_include_repealed') }}</a>
    </nav>

    @if ($instruments->isEmpty())
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('register.empty_title') }}</div>
                <div class="a-empty__body">{{ __('register.empty_body') }}</div>
            </div>
        </div>
    @else
        <section class="a-card a-card--flush">
            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('register.col_instrument') }}</th>
                            <th scope="col">{{ __('register.col_country') }}</th>
                            <th scope="col">{{ __('register.col_sector') }}</th>
                            <th scope="col">{{ __('register.col_status') }}</th>
                            <th scope="col">{{ __('register.col_enacted') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($instruments as $instrument)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('register.show', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug]) }}">{{ $instrument->translated('title', $instrument->reference_number ?? $instrument->slug) }}</a>
                                    <span class="a-table__sub">{{ $instrument->reference_number }}</span>
                                </th>
                                <td>{{ $instrument->country?->name }}</td>
                                <td>{{ $instrument->sector?->name }}</td>
                                <td><span class="a-pill" data-status="{{ $instrument->status?->value }}">{{ __($instrument->status->labelKey()) }}</span></td>
                                <td>{{ $instrument->enacted_on?->isoFormat('D MMMM YYYY') ?? __('office.date_not_recorded') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{ $instruments->links() }}
    @endif
@endsection
