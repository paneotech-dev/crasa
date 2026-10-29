@extends('layouts.extranet')

{{--
    Contributing a national instrument. RP-03 and RP-06.

    English is required and the other two are not, because CLAUDE.md says
    requiring all three blocks all publishing. A regulator who has the act
    in English should not be stopped from filing it because nobody has
    translated the summary yet.

    There is no country field. The server fixes the country to the
    contributor's own, so a field the form does not carry cannot be
    tampered with.
--}}

@php($isNew = ! $instrument->exists)
@php($state = $instrument->contribution_state)
@php($editable = $isNew || $instrument->isEditableByContributor())

@section('title', $isNew ? __('contributions.add') : __('contributions.edit_title'))
@section('note', $isNew ? __('contributions.add_note') : $instrument->reference_number)

@section('crumbs')
    <li><a href="{{ route('contributions', ['locale' => app()->getLocale()]) }}">{{ __('contributions.title') }}</a></li>
    <li aria-current="page">{{ $isNew ? __('contributions.add') : __('admin.edit') }}</li>
@endsection

@section('content')
    @unless ($isNew)
        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('contributions.state_heading') }}</h2>
                <span class="a-card__meta">
                    <span class="a-pill" data-status="{{ $state?->value }}">{{ __($state->labelKey()) }}</span>
                </span>
            </div>

            @if ($state === \App\Enums\ContributionState::Returned && $instrument->review_note)
                {{-- What the Secretariat said, first. A contributor who has
                     to hunt for the reason answers the wrong thing. --}}
                <div class="a-band a-band--error" role="alert">
                    <strong>{{ __('contributions.returned_heading') }}</strong>
                    <p>{{ $instrument->review_note }}</p>
                    @if ($instrument->reviewed_at)
                        <p class="a-hint">{{ __('contributions.reviewed_on', ['date' => $instrument->reviewed_at->displayed()->isoFormat('D MMMM YYYY')]) }}</p>
                    @endif
                </div>
            @endif

            @if ($state === \App\Enums\ContributionState::Submitted)
                <p class="a-hint">{{ __('contributions.with_secretariat') }}</p>
            @endif

            @if ($editable)
                <div class="a-form__actions">
                    <form method="POST" action="{{ route('contributions.submit', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug]) }}">
                        @csrf
                        <button class="a-btn a-btn--primary" type="submit">{{ __('contributions.submit_action') }}</button>
                    </form>
                </div>
            @endif
        </section>
    @endunless

    <form class="a-form a-form--card" method="POST" enctype="multipart/form-data"
          action="{{ $isNew
              ? route('contributions.store', ['locale' => app()->getLocale()])
              : route('contributions.update', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug]) }}">
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <fieldset class="a-fieldset" @unless ($editable) disabled @endunless>
            <legend class="a-legend">{{ __('contributions.what_it_is') }}</legend>

            <div class="a-field">
                <label class="a-label" for="reference_number">{{ __('contributions.field_reference') }}<span class="a-required">{{ __('admin.required') }}</span></label>
                <input class="a-input" type="text" id="reference_number" name="reference_number" maxlength="120" required
                       value="{{ old('reference_number', $instrument->reference_number) }}">
                <p class="a-hint">{{ __('contributions.reference_hint') }}</p>
                @error('reference_number')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="a-field">
                <label class="a-label" for="instrument_type">{{ __('contributions.field_type') }}<span class="a-required">{{ __('admin.required') }}</span></label>
                <select class="a-select" id="instrument_type" name="instrument_type" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(old('instrument_type', $instrument->instrument_type?->value) === $type->value)>{{ __($type->labelKey()) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a-field">
                <label class="a-label" for="sector_id">{{ __('contributions.field_sector') }}<span class="a-required">{{ __('admin.required') }}</span></label>
                <select class="a-select" id="sector_id" name="sector_id" required>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected(old('sector_id', $instrument->sector_id) == $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a-field">
                <label class="a-label" for="status">{{ __('contributions.field_status') }}<span class="a-required">{{ __('admin.required') }}</span></label>
                <select class="a-select" id="status" name="status" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $instrument->status?->value) === $status->value)>{{ __($status->labelKey()) }}</option>
                    @endforeach
                </select>
                <p class="a-hint">{{ __('contributions.status_hint') }}</p>
            </div>

            <div class="a-field">
                <label class="a-label" for="enacted_on">{{ __('contributions.field_enacted') }}</label>
                <input class="a-input" type="date" id="enacted_on" name="enacted_on" value="{{ old('enacted_on', $instrument->enacted_on?->toDateString()) }}">
            </div>

            <div class="a-field">
                <label class="a-label" for="effective_from">{{ __('contributions.field_effective') }}</label>
                <input class="a-input" type="date" id="effective_from" name="effective_from" value="{{ old('effective_from', $instrument->effective_from?->toDateString()) }}">
            </div>
        </fieldset>

        @foreach (\App\Support\Locales::SUPPORTED as $locale)
            <fieldset class="a-fieldset" @unless ($editable) disabled @endunless>
                <legend class="a-legend">{{ \App\Support\Locales::nameOf($locale) }}</legend>

                <div class="a-field">
                    <label class="a-label" for="title_{{ $locale }}">
                        {{ __('contributions.field_title') }}
                        @if ($locale === 'en')<span class="a-required">{{ __('admin.required') }}</span>@endif
                    </label>
                    <input class="a-input" type="text" id="title_{{ $locale }}" name="title_{{ $locale }}" maxlength="255"
                           @if ($locale === 'en') required @endif
                           value="{{ old('title_'.$locale, $instrument->translated('title', '', $locale)) }}">
                    @error('title_'.$locale)<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="a-field">
                    <label class="a-label" for="summary_{{ $locale }}">{{ __('contributions.field_summary') }}</label>
                    <textarea class="a-input" id="summary_{{ $locale }}" name="summary_{{ $locale }}" rows="4" maxlength="2000">{{ old('summary_'.$locale, $instrument->translated('summary', '', $locale)) }}</textarea>
                </div>
            </fieldset>
        @endforeach

        <fieldset class="a-fieldset" @unless ($editable) disabled @endunless>
            <legend class="a-legend">{{ __('contributions.the_document') }}</legend>

            @if (! $isNew && $instrument->documents->isNotEmpty())
                <ul class="a-rows" role="list">
                    @foreach ($instrument->documents as $document)
                        <li class="a-row">
                            <span class="a-row__grow">
                                <a href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ $document->original_filename }}</a>
                            </span>
                            <span class="a-row__who">{{ $document->readableSize() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="a-field">
                <label class="a-label" for="file">{{ __('contributions.field_file') }}</label>
                <input class="a-input" type="file" id="file" name="file" accept="application/pdf">
                <p class="a-hint">{{ __('contributions.file_hint') }}</p>
                @error('file')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
            </div>
        </fieldset>

        @if (! $isNew && $related->isNotEmpty())
            <fieldset class="a-fieldset" @unless ($editable) disabled @endunless>
                <legend class="a-legend">{{ __('contributions.related') }}</legend>

                <div class="a-field">
                    <label class="a-label" for="superseded_by_id">{{ __('contributions.field_supersedes') }}</label>
                    <select class="a-select" id="superseded_by_id" name="superseded_by_id">
                        <option value="">{{ __('contributions.supersedes_none') }}</option>
                        @foreach ($related as $other)
                            <option value="{{ $other->id }}" @selected(old('superseded_by_id', $instrument->superseded_by_id) == $other->id)>{{ $other->translated('title', $other->reference_number ?? $other->slug) }}</option>
                        @endforeach
                    </select>
                    <p class="a-hint">{{ __('contributions.supersedes_hint') }}</p>
                </div>
            </fieldset>
        @endif

        @if ($editable)
            <div class="a-form__actions">
                <button class="a-btn a-btn--primary" type="submit">{{ __('contributions.save') }}</button>
            </div>
        @else
            <p class="a-hint">{{ __('contributions.locked') }}</p>
        @endif
    </form>
@endsection
