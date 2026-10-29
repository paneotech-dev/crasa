{{--
    Home: "Upcoming meetings" (C12). Self-contained: its own query, its
    own stylesheet. Up to three public meetings still to come; with fewer
    than two, the next one and the two most recent past meetings, labelled
    Past; with two, the latest past meeting takes the third place. Rendered by the home page with @includeIf('partials.home.upcoming').
--}}
@php
    $homeUpcoming = \App\Models\Meeting::public()->upcoming()->with(['committee', 'newsItem'])->limit(3)->get();
    // Fewer than two to come: the next one and the two most recent past
    // meetings. Two to come: the third place goes to the latest past one,
    // so the row is never two thirds full.
    if ($homeUpcoming->count() < 3) {
        $keep = $homeUpcoming->count() < 2 ? 1 : 2;
        $homeUpcoming = $homeUpcoming->take($keep)->concat(
            \App\Models\Meeting::public()->dated()
                ->whereRaw('COALESCE(ends_on, starts_on) < ?', [\App\Support\LocalTime::today()])
                ->with(['committee', 'newsItem'])->orderByDesc('starts_on')->limit(3 - min($keep, $homeUpcoming->count()))->get()
        );
    }
@endphp
@once
    @push('styles')
        <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/events.css') }}">
    @endpush
@endonce
@if ($homeUpcoming->isNotEmpty())
    <section class="u-section e-home" aria-labelledby="home-upcoming-title">
        <div class="u-container">
            <div class="e-home__head">
                <div>
                    <h2 class="u-h2" id="home-upcoming-title">{{ __('e.home_title') }}</h2>
                    <p class="u-lead e-home__lead">{{ __('e.home_lead') }}</p>
                </div>
                <a class="u-btn u-btn--secondary" href="{{ route('events', ['locale' => app()->getLocale()]) }}">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>{{ __('e.home_all') }}</a>
            </div>
            <ul class="u-cols-3 e-cards" role="list">
                @foreach ($homeUpcoming as $meeting)
                    <li>@include('pages.events._card', ['meeting' => $meeting, 'level' => 3, 'label' => true, 'noRegister' => true])</li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
