{{--
    The country dashboard, screen 04 and RP-12.

    WHAT THIS PAGE ANSWERS. A national regulator signing in wants one
    question answered: what does my authority owe, and what has changed
    since I was last here. The page opened on a welcome and three panels of
    recent things, which answered neither.

    ORDER IS THE DESIGN, and it is not recency. Anything the Secretariat has
    returned comes first, because it is the only thing on the page that is
    blocked on this reader. Then what the state stands at and what is due.
    Then what has changed. A dashboard sorted by recency puts a document
    filed this morning above a return that was sent back three weeks ago.

    A committee member gets the committee panels without the country ones. A
    person who holds a seat but represents no member state has no return to
    file and no completeness figure of their own; showing them the country
    panels would show them either somebody else's numbers or three empty
    boxes.

    Everything here is filtered by the same Gate the pages behind it use. A
    dashboard is the classic place a permission layer leaks, because it
    assembles records from six sources and it is tempting to trust the
    query rather than the policy.

    Every panel has an empty state. A member state that has filed nothing
    and sits on no committee should meet a sentence, not six blank boxes.
--}}
@extends('layouts.extranet')

@section('title', __('extranet.dashboard_title'))
@section('note', $clearance['scope'] ?? '')

@section('content')
    {{-- The reader's own clearance.

         Screen 04 pins this to the bottom of the rail. It moved here with
         the shell on 10 September 2026: the rail now carries a row per
         committee the reader sits on, and a boundary statement under a
         scrolling list is a boundary statement nobody reads. Here it sits
         above the folders it describes.

         A reader who does not know their own clearance reads an empty
         folder list as a fault rather than as a boundary. --}}
    @if ($clearance)
        <div class="a-alert" role="note">
            <strong>{{ __('extranet.clearance_title') }}</strong>
            {{ $clearance['level'] }}. {{ $clearance['scope'] }}
        </div>
    @endif

    {{-- A proposal, not a feature. Member state representatives and
         committee members only. See the partial. --}}
    @include('partials.assistant-proposal')

    @if ($isCountryView)
        {{-- Returned first. It is the only item on this page that is
             blocked on the reader, and a returned submission that scrolls
             under three panels of recent activity is one nobody answers. --}}
        @if ($returned->isNotEmpty())
            <div class="a-band a-band--error" role="alert">
                <strong>{{ trans_choice('extranet.dash_returned_title', $returned->count(), ['count' => $returned->count()]) }}</strong>
                {{ __('extranet.dash_returned_body') }}
                <ul class="a-stack">
                    @foreach ($returned as $submission)
                        <li>
                            <a href="{{ route('submissions.edit', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">
                                {{ __('extranet.dash_returned_row', ['period' => $submission->period]) }}
                            </a>
                            @if ($submission->reviewed_at)
                                <span class="e-dash__meta">{{ $submission->reviewed_at->isoFormat('D MMMM YYYY') }}</span>
                            @endif
                            @if ($submission->review_note)
                                <span class="e-dash__meta">{{ \Illuminate\Support\Str::limit($submission->review_note, 160) }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- The figure and where it stands. A percentage on its own says
             nothing a regulator can act on: sixty is good or bad depending
             on whether the region is at forty or at ninety, and the
             comparison is the reason the matrix exists. --}}
        <div class="a-tiles">
            <a class="a-tile" href="{{ route('completeness', ['locale' => app()->getLocale()]) }}">
                <span class="a-tile__icon" aria-hidden="true"><x-shell-icon name="completeness" :size="20" /></span>
                <span class="a-tile__text">
                    <span class="a-tile__n">{{ $completeness['percent'] }}%</span>
                    <span class="a-tile__label">{{ __('extranet.dash_completeness_label', ['country' => $country?->name ?? '']) }}</span>
                    @if ($completeness['rank'])
                        <span class="a-tile__note">{{ __('extranet.dash_rank', ['rank' => $completeness['rank'], 'of' => $completeness['of']]) }}</span>
                    @endif
                </span>
            </a>

            <a class="a-tile" href="{{ route('submissions', ['locale' => app()->getLocale()]) }}">
                <span class="a-tile__icon" aria-hidden="true"><x-shell-icon name="submissions" :size="20" /></span>
                <span class="a-tile__text">
                    <span class="a-tile__n">{{ $due->count() }}</span>
                    <span class="a-tile__label">{{ __('extranet.dash_due_label') }}</span>
                    <span class="a-tile__note">{{ __('extranet.dash_due_note') }}</span>
                </span>
            </a>
        </div>

        <section class="a-resource">
            <div class="a-resource__head">
                <h2 class="a-resource__title">{{ __('extranet.dash_due_title') }}</h2>
                <a href="{{ route('submissions', ['locale' => app()->getLocale()]) }}">{{ __('extranet.dash_all_returns') }}</a>
            </div>

            @if ($due->isEmpty())
                <div class="a-empty">
                    <p class="a-empty__title">{{ __('extranet.dash_no_due_title') }}</p>
                    <p class="a-empty__body">{{ __('extranet.dash_no_due_body') }}</p>
                </div>
            @else
                <ul class="e-dash__list" role="list">
                    @foreach ($due as $period)
                        <li class="e-dash__row">
                            <span class="e-dash__main">
                                <a class="e-dash__title" href="{{ route('submissions', ['locale' => app()->getLocale()]) }}">
                                    {{ __('extranet.dash_return_for', ['period' => $period['period']]) }}
                                </a>
                                {{-- The due date is a convention this build
                                     states as provisional, not a deadline
                                     CRASA has set. See QUESTIONS.md. --}}
                                <span class="e-dash__meta">
                                    {{ __('extranet.dash_due_by', ['date' => $period['due_on']->isoFormat('D MMMM YYYY')]) }}
                                    @if ($period['provisional'])({{ __('extranet.dash_provisional') }})@endif
                                </span>
                            </span>
                            <span class="a-pill" data-status="{{ $period['status'] }}">{{ __('extranet.period_'.$period['status']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="a-resource">
            <div class="a-resource__head">
                <h2 class="a-resource__title">{{ __('extranet.dash_announcements') }}</h2>
            </div>

            @if ($announcements->isEmpty())
                <div class="a-empty">
                    <p class="a-empty__title">{{ __('extranet.dash_no_announcements_title') }}</p>
                    <p class="a-empty__body">{{ __('extranet.dash_no_announcements_body') }}</p>
                </div>
            @else
                <ul class="e-dash__list" role="list">
                    @foreach ($announcements as $announcement)
                        <li class="e-dash__row">
                            <span class="e-dash__main">
                                <a class="e-dash__title" href="{{ route('announcements.show', ['locale' => app()->getLocale(), 'announcement' => $announcement->slug]) }}">{{ $announcement->translated('title') }}</a>
                                {{-- The audience, where there is one. An
                                     announcement with neither is for every
                                     member state. --}}
                                @php($audience = collect([$announcement->sector?->name, $announcement->committee?->name])->filter())
                                <span class="e-dash__meta">
                                    {{ collect([$announcement->published_at?->isoFormat('D MMMM YYYY')])->filter()->merge($audience)->implode(', ') }}
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <div class="e-dash">
        <section class="a-resource">
            <div class="a-resource__head">
                <h2 class="a-resource__title">{{ __('extranet.dash_next_meeting') }}</h2>
                <a href="{{ route('extranet.calendar', ['locale' => app()->getLocale()]) }}">{{ __('extranet.dash_open_calendar') }}</a>
            </div>

            @if ($next_meeting === null)
                <div class="a-empty">
                    <p class="a-empty__title">{{ __('extranet.dash_no_meetings_title') }}</p>
                    <p class="a-empty__body">{{ __('extranet.dash_no_meetings_body') }}</p>
                </div>
            @else
                <ul class="e-dash__list" role="list">
                    @php($meeting = $next_meeting)
                        <li class="e-dash__row">
                            {{-- The day-and-month chip screen 11 draws. The
                                 date is also in the link text, so the chip
                                 is decoration for a fact already stated. --}}
                            <span class="e-dash__date" aria-hidden="true">
                                <span class="e-dash__day">{{ $meeting->starts_on->format('j') }}</span>
                                <span class="e-dash__month">{{ $meeting->starts_on->isoFormat('MMM') }}</span>
                            </span>
                            <span class="e-dash__main">
                                <a class="e-dash__title" href="{{ route('extranet.calendar', ['locale' => app()->getLocale()]) }}">{{ $meeting->title }}</a>
                                <span class="e-dash__meta">
                                    {{ $meeting->starts_on->isoFormat('D MMMM YYYY') }}@if ($meeting->committee), {{ $meeting->committee->name }}@endif
                                    @if ($meeting->location), {{ $meeting->location }}@endif
                                </span>
                            </span>
                            <span class="a-pill" data-status="{{ $meeting->kind }}">{{ __('extranet.kind_'.$meeting->kind) }}</span>
                        </li>
                </ul>
            @endif
        </section>

        {{-- The reader's own seats. A committee member had no route to
             their own workspace from anywhere on the platform: the rail is
             flat and carries no committee, and this page listed what had
             been filed without saying where. --}}
        @if ($committees->isNotEmpty())
            <section class="a-resource">
                <div class="a-resource__head">
                    <h2 class="a-resource__title">{{ __('extranet.dash_committees') }}</h2>
                </div>

                <ul class="e-dash__list" role="list">
                    @foreach ($committees as $committee)
                        <li class="e-dash__row">
                            <span class="e-dash__main">
                                <a class="e-dash__title" href="{{ route('extranet.committee', ['locale' => app()->getLocale(), 'committee' => $committee->slug]) }}">{{ $committee->name }}</a>
                                <span class="e-dash__meta">
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
        <section class="a-resource">
            <div class="a-resource__head">
                <h2 class="a-resource__title">{{ __('extranet.dash_documents') }}</h2>
                @can('viewAny', \App\Models\Folder::class)
                    <a href="{{ route('extranet.folders', ['locale' => app()->getLocale()]) }}">{{ __('extranet.dash_all_documents') }}</a>
                @endcan
            </div>

            @if ($documents->isEmpty())
                <div class="a-empty">
                    <p class="a-empty__title">{{ __('extranet.dash_no_documents_title') }}</p>
                    <p class="a-empty__body">{{ __('extranet.dash_no_documents_body') }}</p>
                </div>
            @else
                <ul class="e-dash__list" role="list">
                    @foreach ($documents as $document)
                        <li class="e-dash__row">
                            <span class="c-filetype" aria-hidden="true">{{ strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION)) }}</span>
                            <span class="e-dash__main">
                                <a class="e-dash__title" href="{{ route('documents.show', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ $document->original_filename }}</a>
                                <span class="e-dash__meta">
                                    {{ $document->created_at?->isoFormat('D MMMM YYYY') }}, {{ $document->readableSize() }}
                                </span>
                            </span>
                            {{-- Clearance carried as a label, never as
                                 colour alone: screen 04's rule for the
                                 confidentiality key applies wherever a
                                 clearance is drawn. --}}
                            <span class="a-pill" data-clearance="{{ $document->clearance->value }}">{{ $document->clearance->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="a-resource">
            <div class="a-resource__head">
                <h2 class="a-resource__title">{{ __('extranet.dash_notifications') }}</h2>
            </div>

            @if ($notifications->isEmpty())
                <div class="a-empty">
                    <p class="a-empty__title">{{ __('extranet.dash_no_notifications_title') }}</p>
                    <p class="a-empty__body">{{ __('extranet.dash_no_notifications_body') }}</p>
                </div>
            @else
                <ul class="e-dash__list" role="list">
                    @foreach ($notifications as $notification)
                        <li class="e-dash__row">
                            <span class="e-dash__main">
                                <span class="e-dash__title">{{ $notification->data['title'] ?? __('extranet.dash_notification') }}</span>
                                <span class="e-dash__meta">{{ $notification->created_at?->diffForHumans() }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
