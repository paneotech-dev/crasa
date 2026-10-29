{{--
    The confirmation session.

    Built for a room rather than for a folder: fourteen rows on a projector,
    a visible count of how many are done, and a delegate who can see their
    own member state's line move from unconfirmed to confirmed while they
    watch.

    The progress figure is there because a room needs to see progress. Ten of
    fourteen at the coffee break is a different conversation from a list
    somebody promises to return by email.
--}}
@extends('layouts.admin')

@section('title', __('admin.confirmations'))
@section('note', __('admin.confirmations_note'))

@section('crumbs')
    <li><a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('admin.dashboard') }}</a></li>
    <li aria-current="page">{{ __('admin.confirmations') }}</li>
@endsection

@section('content')
    <dl class="c-tiles">
        <div class="c-tile">
            <dd>{{ $confirmed }}</dd>
            <dt>{{ __('admin.confirm_done') }}</dt>
        </div>
        <div class="c-tile">
            <dd>{{ $countries->count() - $confirmed }}</dd>
            <dt>{{ __('admin.confirm_outstanding') }}</dt>
        </div>
        <div class="c-tile">
            <dd>{{ $countries->count() }}</dd>
            <dt>{{ __('admin.confirm_total') }}</dt>
        </div>
    </dl>

    <section class="a-resource">
        <div class="a-resource__head">
            <h2 class="a-resource__title">{{ __('admin.confirm_states') }}</h2>
            <div class="a-resource__note">{{ __('admin.confirm_states_note') }}</div>
        </div>

        <div class="a-tablewrap">
            <table class="a-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('blocks.member_state') }}</th>
                        <th scope="col">{{ __('admin.confirm_authority') }}</th>
                        <th scope="col">{{ __('admin.confirm_sectors') }}</th>
                        <th scope="col">{{ __('admin.confirm_seats') }}</th>
                        <th scope="col">{{ __('admin.confirm_status') }}</th>
                        <th scope="col">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($countries as $country)
                        @php $authority = $country->authority; @endphp
                        <tr>
                            <th scope="row">
                                <x-flag :iso="$country->iso_code" />
                                {{ $country->name }}
                            </th>
                            <td>
                                @if ($authority)
                                    {{ $authority->acronym }}
                                    <span class="a-table__sub">{{ $authority->name }}</span>
                                @else
                                    <span class="a-table__sub">{{ __('admin.confirm_no_authority') }}</span>
                                @endif
                            </td>
                            <td>{{ $authority?->sectors->pluck('name')->implode(', ') ?: '–' }}</td>
                            <td>{{ $country->committees->count() ?: '–' }}</td>
                            <td>
                                @if ($authority?->isConfirmed())
                                    <span class="a-pill" data-status="validated">{{ __('admin.confirm_confirmed') }}</span>
                                    <span class="a-table__sub">
                                        {{ __('admin.confirm_by', [
                                            'name' => $authority->confirmed_by_name,
                                            'date' => $authority->confirmed_at->displayed()->isoFormat('D MMM YYYY'),
                                        ]) }}
                                    </span>
                                @else
                                    <span class="a-pill" data-status="draft">{{ __('admin.confirm_unconfirmed') }}</span>
                                @endif
                            </td>
                            <td>
                                <a class="a-btn a-btn--secondary" href="{{ route('admin.confirmations.edit', ['locale' => app()->getLocale(), 'iso' => $country->iso_code]) }}">
                                    {{ $authority?->isConfirmed() ? __('admin.confirm_review') : __('admin.confirm_open') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
