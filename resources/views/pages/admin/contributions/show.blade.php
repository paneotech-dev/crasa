@extends('layouts.admin')

{{--
    One contribution, and the decision on it.

    Returning without a reason is refused by the workflow, not only by this
    form: a contributor who gets their work back with no note has to guess,
    and guessing is how a contribution comes back with the same fault.
--}}

@php($title = $instrument->translate('title'))
@php($summary = $instrument->translate('summary'))

@section('title', $title?->value ?? $instrument->reference_number)
@section('note', $instrument->country?->displayName() ?? '')

@section('crumbs')
    <li><a href="{{ route('admin.contributions', ['locale' => app()->getLocale()]) }}">{{ __('contributions.review_title') }}</a></li>
    <li aria-current="page">{{ $title?->value ?? $instrument->reference_number }}</li>
@endsection

@section('content')
    <section class="a-card">
        <div class="a-card__head">
            <h2>{{ __('contributions.state_heading') }}</h2>
            <span class="a-card__meta">
                <span class="a-pill" data-status="{{ $instrument->contribution_state?->value }}">{{ __($instrument->contribution_state->labelKey()) }}</span>
            </span>
        </div>

        <dl class="a-panel__meta">
            <div><dt>{{ __('contributions.col_country') }}</dt><dd>{{ $instrument->country?->displayName() }}</dd></div>
            <div><dt>{{ __('contributions.col_sector') }}</dt><dd>{{ $instrument->sector?->displayName() }}</dd></div>
            <div><dt>{{ __('contributions.field_type') }}</dt><dd>{{ __($instrument->instrument_type->labelKey()) }}</dd></div>
            <div><dt>{{ __('contributions.field_status') }}</dt><dd>{{ __($instrument->status->labelKey()) }}</dd></div>
            <div><dt>{{ __('contributions.field_reference') }}</dt><dd>{{ $instrument->reference_number }}</dd></div>
            <div><dt>{{ __('contributions.contributed_by') }}</dt><dd>{{ $instrument->contributor?->name ?? '–' }}</dd></div>
            <div><dt>{{ __('contributions.col_submitted') }}</dt><dd>{{ $instrument->submitted_at?->displayed()->isoFormat('D MMMM YYYY') ?? __('office.date_not_recorded') }}</dd></div>
        </dl>

        @if ($summary)
            <div class="a-readonly">{!! app(\App\Services\ContentRenderer::class)->render($summary->value, 'instrument:'.$instrument->id) !!}</div>
        @endif

        @if ($instrument->documents->isNotEmpty())
            <h3>{{ __('contributions.the_document') }}</h3>
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
    </section>

    @if ($instrument->contribution_state === \App\Enums\ContributionState::Submitted)
        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('contributions.validate_action') }}</h2>
            </div>

            <div class="a-form__actions">
                <form method="POST" action="{{ route('admin.contributions.validate', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug]) }}">
                    @csrf
                    @method('PUT')
                    <button class="a-btn a-btn--primary" type="submit">{{ __('contributions.validate_action') }}</button>
                </form>
            </div>
        </section>

        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('contributions.return_action') }}</h2>
            </div>

            <form class="a-form" method="POST" action="{{ route('admin.contributions.return', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug]) }}">
                @csrf
                @method('PUT')

                <div class="a-field">
                    <label class="a-label" for="review_note">{{ __('contributions.review_note_label') }}<span class="a-required">{{ __('admin.required') }}</span></label>
                    <textarea class="a-input" id="review_note" name="review_note" rows="5" maxlength="2000" required>{{ old('review_note') }}</textarea>
                    <p class="a-hint">{{ __('contributions.review_note_hint') }}</p>
                    @error('review_note')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="a-form__actions">
                    <button class="a-btn a-btn--secondary" type="submit">{{ __('contributions.return_action') }}</button>
                </div>
            </form>
        </section>
    @endif

    @if ($instrument->versions->isNotEmpty())
        <section class="a-card a-card--flush">
            <div class="a-card__head">
                <h2>{{ __('contributions.history') }}</h2>
            </div>

            <ul class="a-history" role="list">
                @foreach ($instrument->versions->sortByDesc('version_number') as $version)
                    <li>
                        <span class="a-history__n">{{ $version->version_number }}</span>
                        <span class="a-history__what">{{ $version->change_summary }}</span>
                        <span class="a-history__who">{{ $version->created_at?->displayed()->isoFormat('D MMMM YYYY, HH:mm') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
