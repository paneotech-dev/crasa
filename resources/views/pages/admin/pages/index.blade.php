@extends('layouts.admin')

@section('title', __('admin.section_content'))
@section('note', __('admin.pages_note'))

@can('create', \App\Models\Page::class)
    @section('actions')
        <a class="a-btn a-btn--primary" href="{{ route('admin.pages.create', ['locale' => app()->getLocale()]) }}">{{ __('fa.page_new') }}</a>
    @endsection
@endcan

@section('content')
    @php
        // The approval column only when a page on this screen has something
        // in it: an empty column of blanks reads as missing data (INC-05).
        $showReview = collect($pages instanceof \Illuminate\Contracts\Pagination\Paginator ? $pages->items() : $pages)
            ->contains(fn ($p) => $p->awaitsClientReview() || $p->client_reviewed_at);
    @endphp
    <x-admin.resource-table
        :title="__('admin.pages_title')"
        :note="__('admin.pages_table_note')"
        :columns="array_values(array_filter([
            ['key' => 'title', 'label' => __('admin.col_title')],
            ['key' => 'slug', 'label' => __('admin.col_slug'), 'quiet' => true],
            ['key' => 'blocks', 'label' => __('admin.col_blocks'), 'numeric' => true],
            ['key' => 'languages', 'label' => __('admin.col_languages'), 'quiet' => true],
            ['key' => 'status', 'label' => __('admin.col_status')],
            $showReview ? ['key' => 'review', 'label' => __('admin.col_review')] : null,
            ['key' => 'updated', 'label' => __('admin.col_updated'), 'quiet' => true],
        ]))"
        :rows="$pages"
        :row="fn ($p) => [
            'url' => route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $p->slug]),
            'cells' => [
                'title' => $p->translate('title')?->value ?? $p->slug,
                'slug' => '/' . $p->slug,
                'blocks' => $p->blocks_count,
                'languages' => \App\Support\TranslationState::cell($p),
                'status' => new \Illuminate\Support\HtmlString(
                    '<span class=\'a-pill\' data-status=\'' . $p->status->value . '\'>'
                    . e(__($p->status->labelKey())) . '</span>'
                ),
                {{-- Administration only. No public template reads this. The
                     count is the number of square-bracketed blanks still in
                     the page's own text, read out of the text itself. --}}
                'review' => new \Illuminate\Support\HtmlString(
                    $p->awaitsClientReview()
                        ? '<span class=\'a-pill\' data-status=\'review\'>'
                            . e(trans_choice('admin.review_outstanding_n', count($p->outstandingClientItems()), ['n' => count($p->outstandingClientItems())]))
                            . '</span>'
                        : ($p->client_reviewed_at
                            ? '<span class=\'a-hint\'>' . e(__('admin.review_cleared')) . '</span>'
                            : '')
                ),
                'updated' => $p->updated_at?->displayed()->isoFormat('D MMM YYYY') ?? '',
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
