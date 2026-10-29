{{-- The help for one screen takes the reader's own shell, like the
     notifications page: back office or member area. --}}
@extends(\App\Support\Workspace::isBackOffice(auth()->user()) ? 'layouts.admin' : 'layouts.extranet')

{{--
    Help per screen (client brief, item 7). What the screen is for, its
    three main actions, and the training guide. A screen without its own
    entry shows its section's general help and says so.
--}}

@php
    $sectionLabel = $help['section'] === \App\Support\ScreenHelp::BACK_OFFICE
        ? __('d3_notify.help_section_back_office')
        : __('d3_notify.help_section_member');
    $heading = $help['title']
        ? __('d3_notify.help_title', ['screen' => $help['title']])
        : __('d3_notify.help_title', ['screen' => $sectionLabel]);
@endphp

@section('title', $heading)

@section('content')
    <section class="a-card s-help" aria-labelledby="help-purpose">
        @if ($help['general'])
            <p class="a-hint">{{ __('d3_notify.help_general_note') }}</p>
        @endif

        <h2 id="help-purpose">{{ __('d3_notify.help_purpose') }}</h2>
        <p class="s-help__purpose">{{ $help['purpose'] }}</p>

        @if ($help['actions'] !== [])
            <h2>{{ __('d3_notify.help_actions') }}</h2>
            <ol class="s-help__actions">
                @foreach ($help['actions'] as $action)
                    <li>{{ $action }}</li>
                @endforeach
            </ol>
        @endif

        <div class="s-help__links">
            @if ($back)
                <a class="a-btn a-btn--secondary" href="{{ $back }}">{{ __('d3_notify.help_back') }}</a>
            @endif
            @if ($guide)
                <a class="a-btn a-btn--primary" href="{{ $guide }}" target="_blank" rel="noopener">
                    {{ __('d3_notify.help_guide') }}<span class="c-sr"> {{ __('d3_notify.help_new_tab') }}</span>
                </a>
            @endif
            @if ($help['section'] === \App\Support\ScreenHelp::BACK_OFFICE && Route::has('admin.help'))
                <a class="s-help__more" href="{{ route('admin.help', ['locale' => app()->getLocale()]) }}">{{ __('d3_notify.help_introduction') }}</a>
            @endif
        </div>
        @if ($guide)
            <p class="a-hint">{{ __('d3_notify.help_guide_note') }}</p>
        @endif
    </section>
@endsection
