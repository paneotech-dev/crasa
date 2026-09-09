@extends('layouts.admin')

@section('title', __('submissions.title'))
@section('note', __('submissions.index_note'))

@section('content')
    @if ($canOpen)
        <section class="a-panel">
            <div class="a-panel__head">
                <h2>{{ __('submissions.open_title') }}</h2>
                <span class="a-panel__meta">{{ __('submissions.open_note') }}</span>
            </div>
            <form class="a-form" method="POST" action="{{ route('submissions.store', ['locale' => app()->getLocale()]) }}">
                @csrf
                <div class="a-field">
                    <label class="a-label" for="period">{{ __('submissions.period') }}</label>
                    <select class="a-select" id="period" name="period">
                        @foreach ($periods as $period)
                            <option value="{{ $period }}">{{ $period }}</option>
                        @endforeach
                    </select>
                    <span class="a-hint">{{ __('submissions.period_hint') }}</span>
                </div>
                <div class="a-form__actions">
                    <button type="submit" class="a-btn a-btn--primary">{{ __('submissions.open_action') }}</button>
                </div>
            </form>
        </section>
    @endif

    <x-admin.resource-table
        :title="__('submissions.title')"
        :note="__('submissions.table_note')"
        :columns="[
            ['key' => 'period', 'label' => __('submissions.period')],
            ['key' => 'country', 'label' => __('admin.field_country')],
            ['key' => 'progress', 'label' => __('submissions.col_progress')],
            ['key' => 'status', 'label' => __('admin.col_status')],
            ['key' => 'updated', 'label' => __('admin.col_updated'), 'quiet' => true],
        ]"
        :rows="$submissions"
        :emptyTitle="__('submissions.empty_title')"
        :emptyBody="__('submissions.empty_body')"
        :row="fn ($s) => [
            'url' => route('submissions.edit', ['locale' => app()->getLocale(), 'submission' => $s->id]),
            'cells' => [
                'period' => $s->period,
                // The flag is decorative and the name carries the
                // meaning, exactly as everywhere else it appears.
                'country' => $s->country
                    ? new \Illuminate\Support\HtmlString(view('components.flag', ['iso' => $s->country->iso_code])->render().' '.e($s->country->name))
                    : '',
                'progress' => $s->values->filter->isComplete()->count().' / '.\App\Models\Indicator::active()->count(),
                'status' => new \Illuminate\Support\HtmlString(
                    '<span class=\'a-pill\' data-status=\'' . e($s->status->value) . '\'>' . e(__($s->status->labelKey())) . '</span>'
                ),
                'updated' => $s->updated_at?->isoFormat('D MMM YYYY') ?? '',
            ],
            'actions' => [
                ['label' => __('admin.edit'), 'url' => route('submissions.edit', ['locale' => app()->getLocale(), 'submission' => $s->id])],
            ],
        ]" />
@endsection
