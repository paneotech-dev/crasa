{{--
    The member area's home page, screen 04 and RP-12, redrawn on Day 3 (8c)
    on the UI system's card anatomy: 12px radius, a 1px border, no shadow.

    WHAT THIS PAGE ANSWERS. A national regulator signing in wants to know
    what their authority owes and what has changed since they were last
    here. So: a returned return first, then my return with its completion
    and the next field to fill, then meetings, the Secretariat's notices
    and my folders. The right-hand column carries notifications, quick
    actions, the completeness figure and the reader's clearance.

    A committee member gets their committees in place of the return. A
    person who represents no member state has no return to file; showing
    them the country cards would show them somebody else's numbers or an
    empty box.

    Everything is filtered by the same Gate or scope the page behind it
    uses (App\Support\MemberHome, CountryDashboard, RoleDashboard).

    Two cards are built by other streams and dropped in when they exist:
    partials.extranet.next-meeting-card and partials.notifications.latest.
    Until then this page draws its own.
--}}
@extends('layouts.extranet')

@section('title', __('extranet.dashboard_title'))
@section('heading', $greeting['name'])
@section('note', $greeting['line'])

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/member-home.css') }}">
@endpush

@section('content')
@php
    $locale = app()->getLocale();
    $isRep = $myReturn !== null;
    $upcoming = $roleBlocks->get('upcoming');
    $indicators = $roleBlocks->get('indicators');
    // The last three files in the reader's folders; a reader with no
    // folder files (the Secretariat) gets what was filed most recently.
    $files = $myFolders['files']->isNotEmpty() ? $myFolders['files'] : $documents->take(3);
@endphp
<div class="mh" data-role="{{ $roleKey }}">

    {{-- Returned first: the only thing on the page that is blocked on the
         reader. --}}
    @if ($isCountryView && $returned->isNotEmpty())
        <div class="a-band a-band--error mh-returned" role="alert">
            <strong>{{ trans_choice('extranet.dash_returned_title', $returned->count(), ['count' => $returned->count()]) }}</strong>
            {{ __('extranet.dash_returned_body') }}
            <ul class="a-stack">
                @foreach ($returned as $submission)
                    <li>
                        <a href="{{ route('submissions.edit', ['locale' => $locale, 'submission' => $submission->id]) }}">{{ __('extranet.dash_returned_row', ['period' => $submission->period]) }}</a>
                        @if ($submission->review_note)
                            <span class="e-dash__meta">{{ \Illuminate\Support\Str::limit($submission->review_note, 160) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mh-cols">
        <div class="mh-main">

            @if ($isRep)
                {{-- My return: the open period this country should be
                     working on, how far it is, and the next field. --}}
                <section class="mh-card mh-return" aria-labelledby="mh-return-h" data-block="return">
                    <div class="mh-card__head">
                        <h2 class="mh-card__title" id="mh-return-h">{{ __('d3_look.my_return') }}</h2>
                        <span class="a-pill" data-status="{{ $myReturn['status'] === 'not_started' ? 'archived' : $myReturn['status'] }}">{{ __('extranet.period_'.$myReturn['status']) }}</span>
                    </div>
                    <p class="mh-return__period">
                        <b>{{ __('extranet.dash_return_for', ['period' => $myReturn['period']]) }}</b>
                        <span class="mh-meta">{{ __('d3_look.due', ['date' => $myReturn['due_on']->isoFormat('D MMMM YYYY')]) }}</span>
                    </p>
                    <div class="mh-progress">
                        <div class="mh-progress__label">
                            <span id="mh-progress-l">{{ __('d3_look.progress', ['filled' => $myReturn['filled'], 'total' => $myReturn['total']]) }}</span>
                            <b>{{ $myReturn['percent'] }}%</b>
                        </div>
                        <div class="mh-bar" role="progressbar" aria-labelledby="mh-progress-l" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $myReturn['percent'] }}">
                            <span class="mh-bar__fill" style="width: {{ $myReturn['percent'] }}%"></span>
                        </div>
                    </div>
                    <dl class="mh-next">
                        <dt>{{ __('d3_look.next_field') }}</dt>
                        <dd>
                            @if ($myReturn['next'] && $myReturn['submission'] && $myReturn['editable'])
                                <a href="{{ route('submissions.edit', ['locale' => $locale, 'submission' => $myReturn['submission']->id]) }}#v-{{ $myReturn['next']->id }}">{{ $myReturn['next']->name }}</a>
                            @elseif ($myReturn['next'])
                                {{ $myReturn['next']->name }}
                            @else
                                {{ __('d3_look.next_field_none') }}
                            @endif
                        </dd>
                    </dl>
                    <p class="mh-meta">{{ __('fb.dash_next_'.($myReturn['status'] === 'not_started' ? 'none' : $myReturn['status'])) }}</p>
                    <div class="mh-card__actions">
                        @if ($myReturn['editable'])
                            <a class="a-btn a-btn--primary" href="{{ route('submissions.edit', ['locale' => $locale, 'submission' => $myReturn['submission']->id]) }}">{{ __('d3_look.continue_return') }}</a>
                        @elseif ($myReturn['can_open'])
                            <form method="POST" action="{{ route('submissions.store', ['locale' => $locale]) }}">
                                @csrf
                                <input type="hidden" name="period" value="{{ $myReturn['period'] }}">
                                <button type="submit" class="a-btn a-btn--primary">{{ __('d3_look.open_return', ['period' => $myReturn['period']]) }}</button>
                            </form>
                        @elseif ($myReturn['submission'])
                            <a href="{{ route('submissions.edit', ['locale' => $locale, 'submission' => $myReturn['submission']->id]) }}">{{ __('d3_look.view_return') }}</a>
                        @endif
                        <a href="{{ route('submissions', ['locale' => $locale]) }}">{{ __('extranet.dash_all_returns') }}</a>
                    </div>
                </section>
            @endif

            {{-- Meetings: the next one, with its registration, and for a
                 representative the two after it. --}}
            <section class="mh-card" aria-labelledby="mh-meet-h" data-block="next">
                <div class="mh-card__head">
                    <h2 class="mh-card__title" id="mh-meet-h">{{ __('d3_look.meetings') }}</h2>
                    <a href="{{ route('extranet.calendar', ['locale' => $locale]) }}">{{ __('extranet.dash_open_calendar') }}</a>
                </div>
                <h3 class="mh-sub">{{ __('extranet.dash_next_meeting') }}</h3>
                {{-- The registration card when a meeting of yours is open for
                     registration; otherwise the next meeting as it stands. --}}
                @php($registrationCard = trim(view('partials.extranet.next-meeting-card', ['user' => auth()->user()])->render()))
                @if ($registrationCard !== '')
                    {!! $registrationCard !!}
                @elseif ($next_meeting === null)
                    <div class="a-empty">
                        <p class="a-empty__title">{{ __('extranet.dash_no_meetings_title') }}</p>
                        <p class="a-empty__body">{{ __('extranet.dash_no_meetings_body') }}</p>
                    </div>
                @else
                    <div class="mh-meeting">
                        <span class="u-date" aria-hidden="true">
                            <span class="u-date__day">{{ $next_meeting->starts_on->isoFormat('D') }}</span>
                            <span class="u-date__month">{{ $next_meeting->starts_on->copy()->locale($locale)->isoFormat('MMM') }}</span>
                        </span>
                        <div class="mh-meeting__body">
                            <p class="mh-meeting__title">{{ $next_meeting->title }}</p>
                            <p class="mh-meta">
                                {{ collect([$next_meeting->starts_on->copy()->locale($locale)->isoFormat('D MMMM YYYY'), $next_meeting->committee?->name, $next_meeting->location])->filter()->implode(', ') }}
                            </p>
                        </div>
                    </div>
                @endif

                @if ($isRep && $upcoming)
                    @php($later = collect($upcoming['rows'])->reject(fn ($row) => $next_meeting && $row['label'] === $next_meeting->title)->take(2))
                    <div data-block="upcoming">
                        @if ($later->isNotEmpty())
                            <h3 class="mh-sub">{{ __('d3_look.also_coming') }}</h3>
                            <ul class="mh-list" role="list">
                                @foreach ($later as $row)
                                    <li class="mh-list__row">
                                        @if ($row['url'])<a href="{{ $row['url'] }}">{{ $row['label'] }}</a>@else<span>{{ $row['label'] }}</span>@endif
                                        @if ($row['meta'])<span class="mh-meta">{{ $row['meta'] }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </section>

            @if (! $isRep && $committees->isNotEmpty())
                <section class="mh-card" aria-labelledby="mh-com-h" data-block="committees">
                    <div class="mh-card__head">
                        <h2 class="mh-card__title" id="mh-com-h">{{ __('d3_look.my_committees') }}</h2>
                    </div>
                    <ul class="mh-list" role="list">
                        @foreach ($committees as $committee)
                            <li class="mh-list__row mh-list__row--split">
                                <span>
                                    <a href="{{ route('extranet.committee', ['locale' => $locale, 'committee' => $committee->slug]) }}">{{ $committee->name }}</a>
                                    <span class="mh-meta">
                                        {{ trans_choice('extranet.dash_committee_documents', $committee->documents_count, ['count' => $committee->documents_count]) }},
                                        {{ trans_choice('extranet.dash_committee_meetings', $committee->meetings_count, ['count' => $committee->meetings_count]) }}
                                    </span>
                                </span>
                                <span class="a-pill" data-status="{{ $committee->pivot->role }}">{{ __('extranet.seat_'.$committee->pivot->role) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- From the Secretariat: the three latest notices. --}}
            <section class="mh-card" aria-labelledby="mh-notices-h">
                <div class="mh-card__head">
                    <h2 class="mh-card__title" id="mh-notices-h">{{ __('extranet.dash_announcements') }}</h2>
                    <a href="{{ route('announcements', ['locale' => $locale]) }}">{{ __('d3_look.all_notices') }}</a>
                </div>
                @if ($notices->isEmpty())
                    <div class="a-empty">
                        <p class="a-empty__title">{{ __('extranet.dash_no_announcements_title') }}</p>
                        <p class="a-empty__body">{{ __('extranet.dash_no_announcements_body') }}</p>
                    </div>
                @else
                    @include('pages.extranet._notice-list', ['items' => $notices, 'compact' => true])
                @endif
            </section>

            {{-- My folders, and the last three files filed in them. --}}
            <section class="mh-card" aria-labelledby="mh-folders-h" data-block="documents">
                <div class="mh-card__head">
                    <h2 class="mh-card__title" id="mh-folders-h">{{ __('d3_look.my_folders') }}</h2>
                    @can('viewAny', \App\Models\Folder::class)
                        <a href="{{ route('extranet.folders', ['locale' => $locale]) }}">{{ __('d3_look.all_folders') }}</a>
                    @endcan
                </div>
                @if ($myFolders['folders']->isNotEmpty())
                    <ul class="mh-folderlinks" role="list" aria-label="{{ __('d3_look.folders') }}">
                        @foreach ($myFolders['folders'] as $folder)
                            <li><a href="{{ route('extranet.folder', ['locale' => $locale, 'folder' => $folder->id]) }}">{{ $folder->name }}</a> <span class="mh-meta">{{ trans_choice('d3_look.folder_files', $folder->documents_count, ['count' => $folder->documents_count]) }}</span></li>
                        @endforeach
                    </ul>
                @endif
                <div>
                    <div>
                        <h3 class="mh-sub">{{ __('d3_look.latest_files') }}</h3>
                        @if ($files->isEmpty())
                            <div class="a-empty">
                                <p class="a-empty__title">{{ __('extranet.dash_no_documents_title') }}</p>
                                <p class="a-empty__body">{{ __('extranet.dash_no_documents_body') }}</p>
                            </div>
                        @else
                            <ul class="mh-list" role="list">
                                @foreach ($files as $document)
                                    <li class="mh-list__row mh-file">
                                        <span class="c-filetype" aria-hidden="true">{{ strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION)) }}</span>
                                        <span class="mh-file__text">
                                            <a href="{{ route('documents.show', ['locale' => $locale, 'document' => $document->id]) }}">{{ $document->original_filename }}</a>
                                            <span class="mh-meta">{{ $document->folder?->name ? $document->folder->name.', ' : '' }}{{ $document->created_at?->displayed()->isoFormat('D MMMM YYYY') }}, {{ $document->readableSize() }}</span>
                                        </span>
                                        <span class="a-pill" data-clearance="{{ $document->clearance->value }}">{{ $document->clearance->label() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </section>

            @if ($isRep)
                <section class="mh-card" aria-labelledby="mh-due-h">
                    <div class="mh-card__head">
                        <h2 class="mh-card__title" id="mh-due-h">{{ __('extranet.dash_due_title') }}</h2>
                    </div>
                    @if ($due->isEmpty())
                        <div class="a-empty">
                            <p class="a-empty__title">{{ __('extranet.dash_no_due_title') }}</p>
                            <p class="a-empty__body">{{ __('extranet.dash_no_due_body') }}</p>
                        </div>
                    @else
                        <ul class="mh-list" role="list">
                            @foreach ($due as $period)
                                <li class="mh-list__row mh-list__row--split">
                                    <span>
                                        <b>{{ __('extranet.dash_return_for', ['period' => $period['period']]) }}</b>
                                        <span class="mh-meta">{{ __('d3_look.due', ['date' => $period['due_on']->isoFormat('D MMMM YYYY')]) }}</span>
                                    </span>
                                    <span class="a-pill" data-status="{{ $period['status'] === 'not_started' ? 'archived' : $period['status'] }}">{{ __('extranet.period_'.$period['status']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>

        <aside class="mh-side" aria-label="{{ __('d3_look.side') }}">
            <section class="mh-card" aria-labelledby="mh-notif-h">
                <div class="mh-card__head">
                    <h2 class="mh-card__title" id="mh-notif-h">{{ __('extranet.dash_notifications') }}</h2>
                    <a href="{{ route('notifications', ['locale' => $locale]) }}">{{ __('d3_look.all_notifications') }}</a>
                </div>
                @if (View::exists('partials.notifications.latest'))
                    @includeIf('partials.notifications.latest', ['user' => auth()->user(), 'notifications' => $notifications])
                @elseif ($notifications->isEmpty())
                    <div class="a-empty">
                        <p class="a-empty__title">{{ __('extranet.dash_no_notifications_title') }}</p>
                        <p class="a-empty__body">{{ __('extranet.dash_no_notifications_body') }}</p>
                    </div>
                @else
                    <ul class="mh-list" role="list">
                        @foreach ($notifications as $notification)
                            <li class="mh-list__row">
                                <a href="{{ route('notifications.open', ['locale' => $locale, 'notification' => $notification->id]) }}">{{ \App\Support\MemberHome::notificationLabel($notification) }}</a>
                                <span class="mh-meta">
                                    {{ $notification->created_at?->displayed()->isoFormat('D MMMM YYYY') }}
                                    @if ($notification->read_at === null)<span class="a-pill" data-status="pending">{{ __('d3_look.unread') }}</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <nav class="mh-card" aria-labelledby="mh-qa-h">
                <div class="mh-card__head">
                    <h2 class="mh-card__title" id="mh-qa-h">{{ __('d3_look.quick_actions') }}</h2>
                </div>
                <ul class="mh-links" role="list">
                    @foreach ($quickActions as $action)
                        <li><a href="{{ $action['url'] }}">{{ $action['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            @if ($isRep)
                <section class="mh-card" aria-labelledby="mh-comp-h" data-block="indicators">
                    <div class="mh-card__head">
                        <h2 class="mh-card__title" id="mh-comp-h">{{ __('d3_look.completeness') }}</h2>
                    </div>
                    <p class="mh-figure"><b>{{ min(100, $completeness['percent']) }}%</b> {{ __('extranet.dash_completeness_label', ['country' => $country?->name ?? '']) }}</p>
                    @if ($completeness['rank'])
                        <p class="mh-meta">{{ __('extranet.dash_rank', ['rank' => $completeness['rank'], 'of' => $completeness['of']]) }}</p>
                    @endif
                    @if ($indicators && $indicators['rows'] !== [])
                        <p class="mh-meta">{{ $indicators['rows'][0]['label'] }}, {{ $indicators['rows'][0]['meta'] }}</p>
                    @endif
                    <p class="mh-more"><a href="{{ route('completeness', ['locale' => $locale]) }}">{{ __('d3_look.open_completeness') }}</a></p>
                </section>
            @endif

            @if ($clearance)
                <section class="mh-card mh-card--quiet" aria-labelledby="mh-clear-h">
                    <h2 class="mh-card__title" id="mh-clear-h">{{ __('extranet.clearance_title') }}</h2>
                    <p class="mh-meta">{{ $clearance['level'] }}. {{ $clearance['scope'] }}</p>
                </section>
            @endif

            @include('partials.assistant-proposal')
        </aside>
    </div>
</div>
@endsection
