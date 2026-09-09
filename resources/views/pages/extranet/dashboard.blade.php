{{--
    The extranet landing page, screen 11.

      <h3>Your next meetings</h3>              <a>Open the calendar</a>
      <h3>Recently filed in your committees</h3> <a>All documents</a>

    Each meeting row is a day-and-month chip, the title, a meta line and a
    status. Each document row is a format chip, the title, a meta line, its
    visibility and its status. Both are followed.

    Everything on this page is filtered by the same Gate the pages behind it
    use. A dashboard is the classic place a permission layer leaks, because
    it assembles records from four sources and it is tempting to trust the
    query rather than the policy.

    Every panel has an empty state. A member state that has filed nothing and
    sits on no committee should meet a sentence, not three blank boxes.
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

    <div class="e-dash">
        <section class="a-resource">
            <div class="a-resource__head">
                <h2 class="a-resource__title">{{ __('extranet.dash_meetings') }}</h2>
                <a href="{{ route('extranet.calendar', ['locale' => app()->getLocale()]) }}">{{ __('extranet.dash_open_calendar') }}</a>
            </div>

            @if ($meetings->isEmpty())
                <div class="a-empty">
                    <p class="a-empty__title">{{ __('extranet.dash_no_meetings_title') }}</p>
                    <p class="a-empty__body">{{ __('extranet.dash_no_meetings_body') }}</p>
                </div>
            @else
                <ul class="e-dash__list" role="list">
                    @foreach ($meetings as $meeting)
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
                    @endforeach
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
                <a href="{{ route('extranet.folders', ['locale' => app()->getLocale()]) }}">{{ __('extranet.dash_all_documents') }}</a>
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
