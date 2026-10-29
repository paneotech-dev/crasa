@extends('layouts.admin')

@php
    $isNew = ! $meeting->exists;
    $locale = app()->getLocale();
    $steps = ['basics', 'when', 'people', 'documents', 'summary'];
    $fmt = fn ($d) => $d ? $d->toDateString() : '';
@endphp

@section('title', $isNew ? __('e.admin_new') : $meeting->title)
@section('note', $isNew ? __('e.admin_lead') : $meeting->dateRange())

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/ui.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/events.css') }}">
@endpush
@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/events-admin.js') }}" defer></script>
@endpush

@section('crumbs')
    <li><a href="{{ route('admin.meetings', ['locale' => $locale]) }}">{{ __('e.admin_title') }}</a></li>
    <li aria-current="page">{{ $isNew ? __('e.admin_new') : __('e.admin_edit') }}</li>
@endsection

@section('actions')
    @unless ($isNew)
        @can('view', $meeting)
            <a class="a-btn a-btn--secondary" href="{{ route('events.show', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('e.admin_preview') }}</a>
        @endcan
        <a class="a-btn a-btn--secondary" href="{{ route('admin.delegates', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('e.admin_delegates') }}</a>
    @endunless
@endsection

@section('content')
    @if (session('status'))
        <div class="a-notice a-notice--success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="a-notice a-notice--error e-errors" role="alert" tabindex="-1" data-errors>
            <p><strong>{{ trans_choice('e.errors_count', $errors->count(), ['count' => $errors->count()]) }}</strong></p>
            <ul>
                @foreach ($errors->keys() as $key)
                    <li><a href="#{{ str_replace(['.', '*'], ['_', ''], $key) }}">{{ $errors->first($key) }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Send for approval, Approve or Return: the same panel as news,
         publications and pages. Approving an event makes it public. --}}
    @unless ($isNew)
        @includeIf('pages.admin.approvals._panel', ['record' => $meeting])
    @endunless

    @unless ($isNew)
        <section class="a-card e-status-card" aria-labelledby="status-title">
            <div class="a-card__head">
                <h2 id="status-title">{{ __('e.status_title') }}</h2>
                @if ($meeting->source === 'newsroom')<span class="e-admin-source">{{ __('e.source_newsroom') }}</span>@endif
            </div>
            <p class="a-hint">{{ __('e.status_lead') }}</p>
            <form method="POST" action="{{ route('admin.meetings.status', ['locale' => $locale, 'meeting' => $meeting->slug]) }}" class="e-status-form">
                @csrf
                @method('PUT')
                <fieldset class="e-radios">
                    <legend class="a-label">{{ __('e.field_status') }}</legend>
                    @foreach (\App\Models\Meeting::STATUSES as $status)
                        <label class="e-radio"><input type="radio" name="status" value="{{ $status }}" @checked(old('status', $meeting->status ?: 'scheduled') === $status)> {{ __('e.status_'.$status) }}</label>
                    @endforeach
                </fieldset>
                @include('pages.admin.events._field', ['name' => 'status_note', 'label' => __('e.field_status_note'), 'help' => __('e.field_status_note_help'), 'value' => $meeting->status_note, 'max' => 255])
                <div class="a-form__actions">
                    <button type="submit" class="a-btn a-btn--primary">{{ __('e.status_save') }}</button>
                </div>
            </form>
        </section>
    @endunless

    <form class="a-form a-form--card e-steps" method="POST" enctype="multipart/form-data" novalidate data-steps
          action="{{ $isNew ? route('admin.meetings.store', ['locale' => $locale]) : route('admin.meetings.update', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <ol class="e-stepper" data-stepper hidden>
            @foreach ($steps as $i => $step)
                <li><button type="button" class="e-stepper__btn" data-goto="{{ $i + 1 }}"><span class="e-stepper__n" aria-hidden="true">{{ $i + 1 }}</span>{{ __('e.step_'.$step) }}</button></li>
            @endforeach
        </ol>
        <p class="c-sr" aria-live="polite" data-step-status></p>

        <fieldset class="e-step" data-step="1" id="step-1">
            <legend class="e-step__legend"><span class="e-step__count">{{ __('e.step', ['n' => 1, 'total' => 5]) }}</span> {{ __('e.step_basics') }}</legend>
            @include('pages.admin.events._field', ['name' => 'title', 'label' => __('e.field_title'), 'required' => true, 'help' => __('e.field_title_help'), 'value' => $meeting->title, 'max' => 255])
            <div class="e-cols">
                @include('pages.admin.events._field', ['name' => 'committee_code', 'label' => __('e.field_code'), 'type' => 'select', 'help' => __('e.field_code_help'), 'value' => $meeting->committee_code ?: 'CRASA',
                    'options' => collect($groups)->mapWithKeys(fn ($g) => [$g->code => $g->code.': '.$g->label()])->all()])
                @include('pages.admin.events._field', ['name' => 'kind', 'label' => __('e.field_kind'), 'type' => 'select', 'required' => true, 'value' => $meeting->kind,
                    'options' => collect($kinds)->mapWithKeys(fn ($k) => [$k => __('e.kind_'.$k)])->all()])
            </div>
            @include('pages.admin.events._field', ['name' => 'committee_id', 'label' => __('e.field_committee'), 'type' => 'select', 'help' => __('e.field_committee_help'), 'value' => $meeting->committee_id,
                'options' => ['' => __('e.field_committee_none')] + $committees->pluck('name', 'id')->all()])
            @include('pages.admin.events._field', ['name' => 'summary', 'label' => __('e.field_summary'), 'type' => 'textarea', 'rows' => 3, 'help' => __('e.field_summary_help'), 'value' => $meeting->summary])
            @include('pages.admin.events._field', ['name' => 'organiser', 'label' => __('e.field_organiser'), 'value' => $meeting->organiser, 'max' => 200])
        </fieldset>

        <fieldset class="e-step" data-step="2" id="step-2">
            <legend class="e-step__legend"><span class="e-step__count">{{ __('e.step', ['n' => 2, 'total' => 5]) }}</span> {{ __('e.step_when') }}</legend>
            <div class="e-cols">
                @include('pages.admin.events._field', ['name' => 'starts_on', 'label' => __('e.field_starts_on'), 'type' => 'date', 'required' => true, 'value' => $fmt($meeting->starts_on)])
                @include('pages.admin.events._field', ['name' => 'ends_on', 'label' => __('e.field_ends_on'), 'type' => 'date', 'help' => __('e.field_ends_on_help'), 'value' => $fmt($meeting->ends_on)])
            </div>
            <div class="e-cols e-cols--3">
                @include('pages.admin.events._field', ['name' => 'starts_at', 'label' => __('e.field_starts_at'), 'type' => 'time', 'value' => $meeting->starts_at ? substr($meeting->starts_at, 0, 5) : ''])
                @include('pages.admin.events._field', ['name' => 'ends_at', 'label' => __('e.field_ends_at'), 'type' => 'time', 'value' => $meeting->ends_at ? substr($meeting->ends_at, 0, 5) : ''])
                @include('pages.admin.events._field', ['name' => 'timezone', 'label' => __('e.field_timezone'), 'type' => 'select', 'help' => __('e.field_timezone_help'), 'value' => $meeting->timezone ?: \App\Support\LocalTime::zone(),
                    'options' => collect(['Africa/Gaborone', 'Africa/Johannesburg', 'Africa/Maputo', 'Africa/Lusaka', 'Africa/Harare', 'Africa/Blantyre', 'Africa/Lubumbashi', 'Africa/Kinshasa', 'Africa/Luanda', 'Africa/Windhoek', 'Africa/Dar_es_Salaam', 'Indian/Mauritius', 'Indian/Comoro', 'Europe/Brussels', 'UTC'])->mapWithKeys(fn ($z) => [$z => $z.' ('.\Carbon\CarbonImmutable::now($z)->format('T, \U\T\CP').')'])->all()])
            </div>
            <div class="e-cols">
                @include('pages.admin.events._field', ['name' => 'location', 'label' => __('e.field_location'), 'help' => __('e.field_location_help'), 'value' => $meeting->location, 'max' => 255])
                @include('pages.admin.events._field', ['name' => 'venue_country', 'label' => __('e.field_country'), 'value' => $meeting->venue_country, 'max' => 120])
            </div>
            @include('pages.admin.events._field', ['name' => 'venue', 'label' => __('e.field_venue'), 'value' => $meeting->venue, 'max' => 200])
            @include('pages.admin.events._field', ['name' => 'online_url', 'label' => __('e.field_online_url'), 'type' => 'url', 'help' => __('e.field_online_url_help'), 'value' => $meeting->online_url, 'max' => 255])
        </fieldset>

        <fieldset class="e-step" data-step="3" id="step-3">
            <legend class="e-step__legend"><span class="e-step__count">{{ __('e.step', ['n' => 3, 'total' => 5]) }}</span> {{ __('e.step_people') }}</legend>
            @include('pages.admin.events._field', ['name' => 'clearance', 'label' => __('e.field_clearance'), 'type' => 'select', 'required' => true, 'help' => __('e.field_clearance_help'), 'value' => $meeting->clearance?->value ?? 'public',
                'options' => collect($clearances)->mapWithKeys(fn ($c) => [$c->value => __('e.clearance_'.$c->value)])->all()])
            <div class="e-cols e-cols--3">
                @include('pages.admin.events._field', ['name' => 'registration_opens_on', 'label' => __('e.field_registration_opens_on'), 'type' => 'date', 'value' => $fmt($meeting->registration_opens_on)])
                @include('pages.admin.events._field', ['name' => 'registration_closes_on', 'label' => __('e.field_registration_closes_on'), 'type' => 'date', 'value' => $fmt($meeting->registration_closes_on)])
                @include('pages.admin.events._field', ['name' => 'capacity', 'label' => __('e.field_capacity'), 'type' => 'number', 'min' => 1, 'help' => __('e.field_capacity_help'), 'value' => $meeting->capacity])
            </div>
            @include('pages.admin.events._field', ['name' => 'registration_url', 'label' => __('e.field_registration_url'), 'type' => 'url', 'value' => $meeting->registration_url, 'max' => 255])
        </fieldset>

        <fieldset class="e-step" data-step="4" id="step-4">
            <legend class="e-step__legend"><span class="e-step__count">{{ __('e.step', ['n' => 4, 'total' => 5]) }}</span> {{ __('e.step_documents') }}</legend>
            @include('pages.admin.events._field', ['name' => 'agenda', 'label' => __('e.field_agenda'), 'type' => 'textarea', 'rows' => 6, 'help' => __('e.field_agenda_help'), 'value' => $meeting->agenda])
            @if (! $isNew && $meeting->documents->isNotEmpty())
                <fieldset class="e-radios">
                    <legend class="a-label">{{ __('e.field_existing_files') }}</legend>
                    @foreach ($meeting->documents as $doc)
                        <label class="e-radio"><input type="checkbox" name="remove_documents[]" value="{{ $doc->id }}"> {{ __('e.field_remove_file', ['name' => $doc->original_filename]) }}</label>
                    @endforeach
                </fieldset>
            @endif
            <div class="a-field e-field @error('files.*') e-field--error @enderror">
                <label class="a-label" for="files">{{ __('e.field_files') }} <span class="e-req">{{ __('e.optional') }}</span></label>
                <input class="a-input" type="file" id="files" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" aria-describedby="files-help @error('files.*') files-error @enderror">
                <p class="a-hint" id="files-help">{{ __('e.field_files_help') }}</p>
                @error('files.*')<p class="e-error" id="files-error">{{ $message }}</p>@enderror
            </div>
        </fieldset>

        <section class="e-step" data-step="5" id="step-5" aria-labelledby="step-5-title">
            <h2 class="e-step__legend" id="step-5-title"><span class="e-step__count">{{ __('e.step', ['n' => 5, 'total' => 5]) }}</span> {{ __('e.step_summary') }}</h2>
            <p class="a-hint">{{ __('e.summary_lead') }}</p>
            <div class="e-summary">
                <div>
                    <h3 class="e-summary__h">{{ __('e.summary_card') }}</h3>
                    <div class="e-summary__card" data-preview aria-live="polite"></div>
                </div>
                <div>
                    <h3 class="e-summary__h">{{ __('e.summary_fields') }}</h3>
                    <dl class="e-summary__list" data-summary-list></dl>
                </div>
            </div>
            {{-- The card the summary fills in. Same classes as the public card. --}}
            <template data-preview-template>
                <article class="u-card e-card e-card--preview">
                    <div class="u-card__body">
                        <div class="e-card__top">
                            <span class="u-date e-date"><span class="u-date__day" data-p="day"></span><span class="u-date__month" data-p="month"></span><span class="e-date__year" data-p="year"></span></span>
                            <span class="e-card__group"><span class="e-badge" data-p="badge"></span><span class="e-card__group-name" data-p="group"></span></span>
                        </div>
                        <p class="u-card__title" data-p="title"></p>
                        <p class="e-card__line" data-p="dates"></p>
                        <p class="e-card__line" data-p="place"></p>
                        <p class="e-card__pills"><span class="u-pill" data-p="visibility"></span></p>
                    </div>
                </article>
            </template>
            @php
                $groupsData = collect($groups)->mapWithKeys(fn ($g) => [$g->code => ['name' => $g->label(), 'colour' => $g->colour]]);
                $labelsData = ['public' => __('e.visibility_public'), 'members' => __('e.visibility_members'), 'online' => __('e.online'), 'empty' => __('e.summary_empty')];
            @endphp
            {{-- @json hex-escapes <, >, &, ' and ", so a committee name cannot close the script element. --}}
            <script type="application/json" data-groups>@json($groupsData)</script>
            <script type="application/json" data-labels>@json($labelsData)</script>
        </section>

        <div class="a-form__actions e-steps__nav">
            <button type="button" class="a-btn a-btn--secondary" data-step-back hidden>{{ __('e.step_back') }}</button>
            <button type="button" class="a-btn a-btn--primary" data-step-next hidden>{{ __('e.step_next') }}</button>
            <button type="submit" class="a-btn a-btn--primary" data-step-save>{{ __('e.save') }}</button>
        </div>
    </form>

    @unless ($isNew)
        <section class="a-card e-danger" aria-labelledby="danger-title">
            <h2 id="danger-title">{{ __('e.admin_delete_title') }}</h2>
            <p class="a-hint">{{ __('e.admin_delete_body', ['title' => $meeting->title]) }}</p>
            <a class="a-btn a-btn--danger" href="{{ route('admin.meetings.delete', ['locale' => $locale, 'meeting' => $meeting->slug]) }}"
               data-delete-event data-title="{{ $meeting->title }}" data-action="{{ route('admin.meetings.destroy', ['locale' => $locale, 'meeting' => $meeting->slug]) }}">{{ __('e.admin_delete') }}</a>
        </section>
        <dialog class="a-modal e-admin-dialog" id="event-delete" aria-labelledby="event-delete-title" aria-describedby="event-delete-body">
            <form class="a-modal__inner" method="POST" action="" data-delete-form>
                @csrf
                @method('DELETE')
                <h2 id="event-delete-title">{{ __('e.admin_delete_title') }}</h2>
                <p id="event-delete-body" data-delete-body data-template="{{ __('e.admin_delete_body', ['title' => '__TITLE__']) }}"></p>
                <div class="a-form__actions">
                    <button type="button" class="a-btn a-btn--secondary" data-modal-close autofocus>{{ __('e.admin_cancel') }}</button>
                    <button type="submit" class="a-btn a-btn--danger">{{ __('e.admin_delete_confirm') }}</button>
                </div>
            </form>
        </dialog>
    @endunless
@endsection
