{{--
    "Your next meeting": the next meeting this account may see whose
    registration is open, with its date and the way to the form. Takes
    $user, and $registrationMeeting when the caller already has it from
    App\Support\NextRegistration. Self-contained so the member dashboard can place it anywhere;
    renders nothing when no meeting is open for registration.
--}}
@php
    // $registrationMeeting when the caller has already looked it up.
    $nextMeeting = $registrationMeeting ?? \App\Support\NextRegistration::for($user);
    $nextMine = $nextMeeting
        ? \App\Models\DelegateRegistration::where('meeting_id', $nextMeeting->id)->where('user_id', $user->id)->first()
        : null;
@endphp
@if ($nextMeeting)
    @once
        @push('styles')
            <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/d3-people-public.css') }}">
        @endpush
    @endonce
    <section class="a-card d3p-next" aria-labelledby="d3p-next-title">
        <div>
            <h2 class="d3p-next__title" id="d3p-next-title">{{ __('d3_people.next_title') }}</h2>
            <p class="d3p-next__meta">
                <a href="{{ route('events.show', ['locale' => app()->getLocale(), 'meeting' => $nextMeeting->slug]) }}">{{ $nextMeeting->title }}</a>,
                <time datetime="{{ $nextMeeting->starts_on->toDateString() }}">{{ $nextMeeting->dateRange() }}</time>@if ($nextMeeting->location), {{ $nextMeeting->location }}@endif
            </p>
            @if ($nextMine)
                <p class="a-hint">{{ __('d3_people.reg_yours', ['status' => __('d3_people.reg_'.($nextMine->status ?: 'filed'))]) }}</p>
            @endif
        </div>
        @if (! $nextMine)
            <a class="a-btn a-btn--primary" href="{{ route('events.register', ['locale' => app()->getLocale(), 'meeting' => $nextMeeting->slug]) }}">{{ __('d3_people.register_attendance') }}<span class="c-sr">: {{ $nextMeeting->title }}</span></a>
        @elseif (($nextMine->status ?: 'filed') !== 'confirmed')
            <a class="a-btn a-btn--secondary" href="{{ route('events.register', ['locale' => app()->getLocale(), 'meeting' => $nextMeeting->slug]) }}">{{ __('d3_people.reg_edit') }}<span class="c-sr">: {{ $nextMeeting->title }}</span></a>
        @endif
    </section>
@endif
