@extends('layouts.admin')

@section('title', __('submissions.queue_title'))
@section('note', __('submissions.queue_note'))

@section('content')
    @if ($pending > 0)
        <div class="a-notice a-notice--pending" role="status">
            <strong>{{ trans_choice('submissions.queue_pending', $pending, ['count' => $pending]) }}</strong>
            {{ __('submissions.queue_pending_body') }}
        </div>
    @endif

    <x-admin.resource-table
        :title="__('submissions.queue_title')"
        :note="__('submissions.queue_table_note')"
        :columns="[
            ['key' => 'country', 'label' => __('admin.field_country')],
            ['key' => 'period', 'label' => __('submissions.period')],
            ['key' => 'progress', 'label' => __('submissions.col_progress')],
            ['key' => 'status', 'label' => __('admin.col_status')],
            ['key' => 'submitted', 'label' => __('submissions.col_submitted'), 'quiet' => true],
        ]"
        :rows="$submissions"
        :emptyTitle="__('submissions.queue_empty_title')"
        :emptyBody="__('submissions.queue_empty_body')"
        :row="fn ($s) => [
            'url' => route('admin.submissions.show', ['locale' => app()->getLocale(), 'submission' => $s->id]),
            'cells' => [
                'country' => $s->country?->name ?? '',
                'period' => $s->period,
                'progress' => $s->values->filter->isComplete()->count().' / '.\App\Models\Indicator::active()->count(),
                'status' => new \Illuminate\Support\HtmlString(
                    '<span class=\'a-pill\' data-status=\'' . e($s->status->value) . '\'>' . e(__($s->status->labelKey())) . '</span>'
                ),
                'submitted' => $s->submitted_at?->isoFormat('D MMM YYYY') ?? '',
            ],
            'actions' => [
                ['label' => __('submissions.review'), 'url' => route('admin.submissions.show', ['locale' => app()->getLocale(), 'submission' => $s->id])],
            ],
        ]">

        <x-slot:filters>
            <label class="c-sr" for="status">{{ __('admin.col_status') }}</label>
            <select class="a-filter" id="status" name="status">
                <option value="">{{ __('admin.status_all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filter === $status->value)>{{ __($status->labelKey()) }}</option>
                @endforeach
            </select>
        </x-slot:filters>
    </x-admin.resource-table>
@endsection
