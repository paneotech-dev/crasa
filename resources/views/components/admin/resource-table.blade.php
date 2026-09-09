{{--
    Screen 09: "The repeating resource table. Every content type in the back
    office uses this pattern."

    So it is built once. A section passes its columns, its rows and its
    paginator; it does not draw its own table. That is what keeps the
    Publications table, the Pages table and the Users table from drifting
    apart over the length of the build.

    columns: list<array{key, label, numeric?, quiet?, width?}>
    rows:    a paginator or collection
    row:     a closure returning array{cells: array<string, string|HtmlString>, title: array{label, url}, actions: list<array{label, url, quiet?}>}
--}}
@props([
    'title',
    'note' => null,
    'columns',
    'rows',
    'row',
    'actions' => null,
    'filters' => null,
    'searchName' => 'q',
    'searchLabel' => null,
    'emptyTitle' => null,
    'emptyBody' => null,
])

<section class="a-resource">
    <div class="a-resource__head">
        <div class="a-resource__bar">
            <div>
                <h2 class="a-resource__title">{{ $title }}</h2>
                @if ($note)
                    <div class="a-resource__note">{{ $note }}</div>
                @endif
            </div>
            @if ($actions)
                <div class="a-pagehead__actions">{{ $actions }}</div>
            @endif
        </div>

        <form class="a-filters" method="GET" action="{{ url()->current() }}">
            <label class="a-search">
                <span class="c-sr">{{ $searchLabel ?? __('admin.search_label') }}</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
                <input type="search" name="{{ $searchName }}" value="{{ request($searchName) }}" placeholder="{{ $searchLabel ?? __('admin.search_label') }}">
            </label>
            {{ $filters }}
            <button type="submit" class="a-btn a-btn--secondary">{{ __('admin.apply') }}</button>
        </form>
    </div>

    @if (count($rows) === 0)
        <div style="padding:var(--s-5)">
            <div class="a-empty">
                <div class="a-empty__title">{{ $emptyTitle ?? __('admin.empty_title') }}</div>
                <div class="a-empty__body">{{ $emptyBody ?? __('admin.empty_body') }}</div>
            </div>
        </div>
    @else
        <div class="a-tablewrap">
            <table class="a-table">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th scope="col" @if ($column['numeric'] ?? false) data-numeric @endif>{{ $column['label'] }}</th>
                        @endforeach
                        <th scope="col">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $record)
                        @php $data = $row($record); @endphp
                        <tr>
                            @foreach ($columns as $index => $column)
                                @if ($index === 0)
                                    <th scope="row">
                                        @if (! empty($data['url']))
                                            <a href="{{ $data['url'] }}">{{ $data['cells'][$column['key']] ?? '' }}</a>
                                        @else
                                            {{ $data['cells'][$column['key']] ?? '' }}
                                        @endif
                                    </th>
                                @else
                                    <td @if ($column['numeric'] ?? false) data-numeric @endif @if ($column['quiet'] ?? false) data-quiet @endif>
                                        {!! $data['cells'][$column['key']] ?? '' !!}
                                    </td>
                                @endif
                            @endforeach
                            <td>
                                <span class="a-table__actions">
                                    @foreach ($data['actions'] ?? [] as $action)
                                        <a href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                                    @endforeach
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->hasPages())
            <div class="a-pager">
                <span>{{ __('admin.showing', ['first' => $rows->firstItem(), 'last' => $rows->lastItem(), 'total' => $rows->total()]) }}</span>
                <span class="a-pager__links">
                    @if ($rows->onFirstPage())
                        <span aria-disabled="true">{{ __('admin.previous') }}</span>
                    @else
                        <a href="{{ $rows->previousPageUrl() }}" rel="prev">{{ __('admin.previous') }}</a>
                    @endif

                    @foreach ($rows->getUrlRange(1, $rows->lastPage()) as $page => $url)
                        @if ($page === $rows->currentPage())
                            <span aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($rows->hasMorePages())
                        <a href="{{ $rows->nextPageUrl() }}" rel="next">{{ __('admin.next') }}</a>
                    @else
                        <span aria-disabled="true">{{ __('admin.next') }}</span>
                    @endif
                </span>
            </div>
        @endif
    @endif
</section>
