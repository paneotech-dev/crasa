@extends('layouts.admin')
@section('title', __('fa.inbox_title'))
@section('note', __('fa.inbox_note'))

@can(\App\Enums\Permission::ManageSettings->value)
    @section('actions')
        <a class="a-btn a-btn--secondary" href="{{ route('admin.approvals.rules', ['locale' => app()->getLocale()]) }}">{{ __('fa.inbox_rules') }}</a>
    @endsection
@endcan

@section('content')
    @php $locale = app()->getLocale(); @endphp

    @if ($mayApprove && ($scopeLabel = \App\Support\ModeratorScope::label(auth()->user())) !== null)
        <p class="a-notice" data-moderation-scope>{{ __('m3_scope.your_scope', ['scope' => $scopeLabel]) }}</p>
    @endif

    @if ($mayApprove)
        <section class="a-card" aria-labelledby="aw-waiting">
            <div class="a-card__head">
                <h2 id="aw-waiting">{{ __('fa.inbox_waiting') }}</h2>
                <span class="aw-count"><span data-awaiting-count>{{ $awaiting->count() }}</span><span class="c-sr"> {{ __('fa.count_waiting') }}</span></span>
            </div>
            @if ($awaiting->isEmpty())
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('fa.inbox_empty_title') }}</div>
                    <div class="a-empty__body">{{ __('fa.inbox_empty_body') }}</div>
                </div>
            @else
                <div class="a-tablewrap">
                    <table class="a-table aw-table">
                        <caption class="c-sr">{{ __('fa.inbox_waiting') }}</caption>
                        <thead><tr>
                            <th scope="col">{{ __('fa.col_item') }}</th>
                            <th scope="col">{{ __('fa.col_type') }}</th>
                            <th scope="col">{{ __('fa.col_by') }}</th>
                            <th scope="col">{{ __('fa.col_when') }}</th>
                            <th scope="col"><span class="c-sr">{{ __('fa.review') }}</span></th>
                        </tr></thead>
                        <tbody>
                        @foreach ($awaiting as $row)
                            @php $own = $row->submitted_by === auth()->id(); @endphp
                            <tr>
                                <td>{{ \App\Support\Approvals::title($row->approvable) }}</td>
                                <td>{{ __('fa.type_'.(\App\Support\Approvals::typeOf($row->approvable ?? new \App\Models\Page) ?? 'pages')) }}</td>
                                <td>{{ $row->submitter?->name ?? __('admin.no_actor') }}</td>
                                <td class="c-num">{{ $row->submitted_at?->displayed()->isoFormat('D MMM YYYY, HH:mm') }}</td>
                                <td>
                                    <a class="a-btn a-btn--small {{ $own ? 'a-btn--secondary' : 'a-btn--primary' }}" href="{{ route('admin.approvals.show', ['locale' => $locale, 'approval' => $row->id]) }}">{{ __('fa.review') }}<span class="c-sr"> {{ \App\Support\Approvals::title($row->approvable) }}</span></a>
                                    @if ($own)<span class="a-hint">{{ __('fa.own_item') }}</span>@endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    <section class="a-card" aria-labelledby="aw-mine">
        <div class="a-card__head"><h2 id="aw-mine">{{ __('fa.inbox_mine') }}</h2></div>
        @if ($mine->isEmpty())
            <div class="a-empty"><div class="a-empty__body">{{ __('fa.inbox_mine_empty') }}</div></div>
        @else
            <div class="a-tablewrap">
                <table class="a-table aw-table">
                    <caption class="c-sr">{{ __('fa.inbox_mine') }}</caption>
                    <thead><tr>
                        <th scope="col">{{ __('fa.col_item') }}</th>
                        <th scope="col">{{ __('fa.col_state') }}</th>
                        <th scope="col">{{ __('fa.col_when') }}</th>
                        <th scope="col">{{ __('fa.col_comment') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($mine as $row)
                        @php $st = ['in_review' => 'in_review', 'approved' => 'published', 'returned' => 'returned', 'withdrawn' => 'draft'][$row->state] ?? 'draft'; @endphp
                        <tr>
                            <td><a href="{{ route('admin.approvals.show', ['locale' => $locale, 'approval' => $row->id]) }}">{{ \App\Support\Approvals::title($row->approvable) }}</a></td>
                            <td>@include('pages.admin.approvals._state', ['state' => $st])</td>
                            <td class="c-num">{{ $row->submitted_at?->displayed()->isoFormat('D MMM YYYY, HH:mm') }}</td>
                            <td>{{ $row->comment }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($mayApprove && $decided->isNotEmpty())
        <section class="a-card" aria-labelledby="aw-decided">
            <div class="a-card__head"><h2 id="aw-decided">{{ __('fa.inbox_decided') }}</h2></div>
            <div class="a-tablewrap">
                <table class="a-table aw-table">
                    <caption class="c-sr">{{ __('fa.inbox_decided') }}</caption>
                    <thead><tr>
                        <th scope="col">{{ __('fa.col_item') }}</th>
                        <th scope="col">{{ __('fa.col_state') }}</th>
                        <th scope="col">{{ __('fa.col_by') }}</th>
                        <th scope="col">{{ __('fa.col_decided') }}</th>
                        <th scope="col">{{ __('fa.col_comment') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($decided as $row)
                        <tr>
                            <td><a href="{{ route('admin.approvals.show', ['locale' => $locale, 'approval' => $row->id]) }}">{{ \App\Support\Approvals::title($row->approvable) }}</a></td>
                            <td>@include('pages.admin.approvals._state', ['state' => $row->state === 'approved' ? 'published' : 'returned'])</td>
                            <td>{{ $row->submitter?->name }}</td>
                            <td>{{ $row->decider?->name }}, {{ $row->decided_at?->displayed()->isoFormat('D MMM, HH:mm') }}</td>
                            <td>{{ $row->comment }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
