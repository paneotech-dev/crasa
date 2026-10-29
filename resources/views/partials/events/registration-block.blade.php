{{--
    "File your arrival and departure" on an event page: who files, what
    happens to a filing, and the way in. A members-only meeting asks a
    signed-out reader to sign in first; the form itself checks again.
--}}
@php
    $locale = app()->getLocale();
    $membersOnly = $meeting->clearance !== \App\Enums\Clearance::Public;
    $mine = auth()->check()
        ? \App\Models\DelegateRegistration::where('meeting_id', $meeting->id)->where('user_id', auth()->id())->first()
        : null;
    $fmt = fn ($d) => $d->copy()->locale($locale)->isoFormat('D MMMM YYYY');
@endphp
@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/d3-people-public.css') }}">
@endpush
<div class="u-panel u-panel--tint e-reg" aria-labelledby="reg-title">
    <h2 class="u-h3" id="reg-title">{{ __('events.registration') }}</h2>
    @if ($meeting->isCancelled())
        <p>{{ __('e.registration_cancelled') }}</p>
    @else
        <h3 class="d3p-reg__h">{{ __('d3_people.reg_who_title') }}</h3>
        <p>{{ __('d3_people.reg_who') }}@if ($membersOnly) {{ __('d3_people.reg_members_only') }}@endif</p>

        @if ($meeting->registrationIsOpen())
            <h3 class="d3p-reg__h">{{ __('d3_people.reg_next_title') }}</h3>
            <ul class="d3p-reg__list">
                <li>{{ __('d3_people.reg_next_copy') }}</li>
                <li>{{ __('d3_people.reg_next_review', ['date' => $fmt($meeting->registrationReplyBy())]) }}</li>
                <li>{{ __('d3_people.reg_next_change') }}</li>
            </ul>
            @if ($meeting->registration_opens_on && $meeting->registration_closes_on)
                <p class="u-help">{{ __('e.registration_window', ['from' => $fmt($meeting->registration_opens_on), 'to' => $fmt($meeting->registration_closes_on)]) }}</p>
            @elseif ($meeting->registration_closes_on)
                <p class="u-help">{{ __('e.registration_until', ['date' => $fmt($meeting->registration_closes_on)]) }}</p>
            @endif
            @if ($meeting->capacity)<p class="u-help">{{ __('e.capacity', ['count' => $meeting->capacity]) }}</p>@endif
            @if ($mine)
                <p class="u-help" role="status">{{ __('d3_people.reg_yours', ['status' => __('d3_people.reg_'.($mine->status ?: 'filed'))]) }}</p>
            @endif
            <div class="u-actions">
                @if ($membersOnly && ! auth()->check())
                    <a class="u-btn u-btn--primary" href="{{ route('events.register', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('d3_people.sign_in_to_register') }}</a>
                @elseif (! $mine || ($mine->status ?: 'filed') !== 'confirmed')
                    <a class="u-btn u-btn--primary" href="{{ route('events.register', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ $mine ? __('d3_people.reg_edit') : __('e.file_arrival') }}</a>
                @endif
                @if ($meeting->registration_url)
                    <a class="u-btn u-btn--secondary" href="{{ $meeting->registration_url }}" rel="noopener">{{ __('events.register_elsewhere') }}</a>
                @endif
            </div>
        @elseif ($meeting->registration_opens_on && ! $meeting->date_unknown && ! $meeting->hasEnded() && $meeting->registration_opens_on->toDateString() > \App\Support\LocalTime::today())
            <p>{{ __('e.registration_from', ['date' => $fmt($meeting->registration_opens_on)]) }}</p>
        @elseif ($meeting->date_unknown || $meeting->hasEnded())
            {{-- An undated legacy meeting is read as past, as on its card. --}}
            <p>{{ __('delegates.closed') }}</p>
        @else
            <p>{{ __('e.registration_closed') }}</p>
        @endif
    @endif
</div>
