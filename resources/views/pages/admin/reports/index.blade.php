@extends('layouts.admin')

@section('title', __('reports.title'))
@section('note', __('reports.note'))

@section('content')
    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('reports.generate') }}</h2>
            <span class="a-panel__meta">{{ __('reports.generate_note') }}</span>
        </div>

        <div class="a-grid a-grid--medium">
            {{-- One click on a member state. --}}
            <form class="a-form" method="POST" action="{{ route('admin.reports.store', ['locale' => app()->getLocale()]) }}">
                @csrf
                <input type="hidden" name="subject_type" value="country">
                <h3 style="font-size:16px">{{ __('reports.on_country') }}</h3>
                <div class="a-field">
                    <label class="a-label" for="country">{{ __('admin.field_country') }}</label>
                    <select class="a-select" id="country" name="subject_key">
                        @foreach ($countries as $country)
                            <option value="{{ $country->iso_code }}">{{ $country->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="a-field">
                    <label class="a-label" for="country-period">{{ __('submissions.period') }}</label>
                    <select class="a-select" id="country-period" name="period">
                        <option value="">{{ __('reports.latest_period') }}</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period }}">{{ $period }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="a-form__actions">
                    <button type="submit" class="a-btn a-btn--primary">{{ __('reports.generate_action') }}</button>
                </div>
            </form>

            {{-- Or one click on an indicator, across the region. Not
                 offered to an account whose export permission is
                 conditional on its own member state. --}}
            @unless ($conditional)
            <form class="a-form" method="POST" action="{{ route('admin.reports.store', ['locale' => app()->getLocale()]) }}">
                @csrf
                <input type="hidden" name="subject_type" value="indicator">
                <h3 style="font-size:16px">{{ __('reports.on_indicator') }}</h3>
                <div class="a-field">
                    <label class="a-label" for="indicator">{{ __('admin.field_indicator') }}</label>
                    <select class="a-select" id="indicator" name="subject_key">
                        @foreach ($indicators as $indicator)
                            <option value="{{ $indicator->slug }}">{{ $indicator->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="a-field">
                    <label class="a-label" for="indicator-period">{{ __('submissions.period') }}</label>
                    <select class="a-select" id="indicator-period" name="period">
                        <option value="">{{ __('reports.latest_period') }}</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period }}">{{ $period }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="a-form__actions">
                    <button type="submit" class="a-btn a-btn--primary">{{ __('reports.generate_action') }}</button>
                </div>
            </form>
            @endunless
        </div>

        @if ($conditional)
            <p class="a-hint">{{ __('reports.conditional_note') }}</p>
        @endif
    </section>

    <x-admin.resource-table
        :title="__('reports.stored')"
        :note="__('reports.stored_note')"
        :columns="[
            ['key' => 'title', 'label' => __('admin.field_title')],
            ['key' => 'period', 'label' => __('submissions.period')],
            ['key' => 'exported', 'label' => __('reports.col_exported'), 'quiet' => true],
            ['key' => 'author', 'label' => __('reports.col_author'), 'quiet' => true],
            ['key' => 'created', 'label' => __('admin.col_updated'), 'quiet' => true],
        ]"
        :rows="$reports"
        :emptyTitle="__('reports.empty_title')"
        :emptyBody="__('reports.empty_body')"
        :row="fn ($r) => [
            'url' => route('admin.reports.show', ['locale' => app()->getLocale(), 'report' => $r->id]),
            'cells' => [
                'title' => $r->title,
                'period' => $r->period ?? __('reports.latest_period'),
                'exported' => $r->exported_at?->isoFormat('D MMM YYYY') ?? __('reports.not_exported'),
                'author' => $r->author?->name ?? '',
                'created' => $r->created_at->isoFormat('D MMM YYYY'),
            ],
            'actions' => [
                ['label' => __('reports.view'), 'url' => route('admin.reports.show', ['locale' => app()->getLocale(), 'report' => $r->id])],
                ['label' => __('reports.pdf'), 'url' => route('admin.reports.pdf', ['locale' => app()->getLocale(), 'report' => $r->id])],
            ],
        ]" />
@endsection
