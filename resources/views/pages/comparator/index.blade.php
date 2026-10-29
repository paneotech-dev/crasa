@extends('layouts.public')

@section('title', __('comparator.title'))
@section('description', __('comparator.meta_description'))

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/comparator.css') }}">
@endpush

@section('content')
    @php
        $locale = app()->getLocale();
        $validatedCount = \App\Models\IndicatorValue::where('confidence', 'validated')->count();
    @endphp

    {{-- A short hero: what the tool does and three figures. The console
         starts right under it and opens on a result (S4, 29 October). --}}
    <section class="u-hero c-cmp__hero">
        <div class="u-container">
            <div class="c-cmp__hero-grid">
            <div>
            <p class="u-eyebrow">{{ __('d.eyebrow') }}</p>
            <h1 class="u-h1">{{ __('comparator.heading') }}</h1>
            <p class="u-lead">{{ __('comparator.meta_description') }}</p>
            </div>
            <dl class="u-figures">
                <div class="u-figure"><dt>{{ __('d.figure_indicators') }}</dt><dd>{{ \App\Models\Indicator::where('is_active', true)->count() }}</dd></div>
                <div class="u-figure"><dt>{{ __('d.figure_states') }}</dt><dd>14</dd></div>
                <div class="u-figure"><dt>{{ __('d.figure_validated') }}</dt><dd>{{ $validatedCount }}</dd></div>
            </dl>
            </div>
        </div>
    </section>

    <div class="u-container c-cmp" data-comparator>
        {{-- Outside the swapped region, so a new text is announced. --}}
        <p class="c-sr" role="status" aria-live="polite" data-cmp-status></p>

        <div data-cmp-swap data-summary="{{ $comparison->summary() }}">
            @include('pages.comparator._console', ['comparison' => $comparison])
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/comparator.js') }}" defer></script>
@endpush
