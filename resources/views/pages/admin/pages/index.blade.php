@extends('layouts.admin')

@section('title', __('admin.section_content'))
@section('note', __('admin.pages_note'))

@section('content')
    <x-admin.resource-table
        :title="__('admin.pages_title')"
        :note="__('admin.pages_table_note')"
        :columns="[
            ['key' => 'title', 'label' => __('admin.col_title')],
            ['key' => 'slug', 'label' => __('admin.col_slug'), 'quiet' => true],
            ['key' => 'blocks', 'label' => __('admin.col_blocks'), 'numeric' => true],
            ['key' => 'languages', 'label' => __('admin.col_languages'), 'quiet' => true],
            ['key' => 'status', 'label' => __('admin.col_status')],
            ['key' => 'updated', 'label' => __('admin.col_updated'), 'quiet' => true],
        ]"
        :rows="$pages"
        :row="fn ($p) => [
            'url' => route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $p->slug]),
            'cells' => [
                'title' => $p->translate('title')?->value ?? $p->slug,
                'slug' => '/' . $p->slug,
                'blocks' => $p->blocks_count,
                'languages' => new \Illuminate\Support\HtmlString(
                    '<span class=\'a-langs\'' . (count($p->localesWithTitle()) < 3 ? ' data-incomplete' : '') . '>'
                    . e(implode(' ', array_map('strtoupper', $p->localesWithTitle()))) . '</span>'
                ),
                'status' => new \Illuminate\Support\HtmlString(
                    '<span class=\'a-pill\' data-status=\'' . $p->status->value . '\'>'
                    . e(__($p->status->labelKey())) . '</span>'
                ),
                'updated' => $p->updated_at?->isoFormat('D MMM YYYY') ?? '',
            ],
            'actions' => [
                ['label' => __('admin.edit'), 'url' => route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $p->slug])],
                ['label' => __('admin.preview'), 'url' => route('admin.pages.preview', ['locale' => app()->getLocale(), 'page' => $p->slug])],
            ],
        ]">

        <x-slot:filters>
            <label class="c-sr" for="status">{{ __('admin.col_status') }}</label>
            <select class="a-filter" id="status" name="status">
                <option value="">{{ __('admin.status_all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ __($status->labelKey()) }}</option>
                @endforeach
            </select>
        </x-slot:filters>
    </x-admin.resource-table>
@endsection
