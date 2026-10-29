@extends('layouts.admin')

{{--
    Announcements, extranet item 10.

    The Secretariat's own channel to the fourteen. Publishing is a separate
    action from saving, and it is the action that notifies, so a draft
    edited six times reaches nobody until somebody decides it is ready.
--}}

@section('title', __('announcements.admin_title'))
@section('note', __('announcements.admin_note'))

@section('crumbs')
    <li aria-current="page">{{ __('announcements.admin_title') }}</li>
@endsection

@section('actions')
    <a class="a-btn a-btn--primary" href="{{ route('admin.announcements.create', ['locale' => app()->getLocale()]) }}">{{ __('announcements.write') }}</a>
@endsection

@section('content')
    @if ($announcements->isEmpty())
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('announcements.empty_title') }}</div>
                <div class="a-empty__body">{{ __('announcements.empty_body') }}</div>
            </div>
        </div>
    @else
        <section class="a-card a-card--flush">
            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('announcements.col_title') }}</th>
                            <th scope="col">{{ __('announcements.col_audience') }}</th>
                            <th scope="col">{{ __('announcements.col_status') }}</th>
                            <th scope="col">{{ __('announcements.col_published') }}</th>
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($announcements as $announcement)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('admin.announcements.edit', ['locale' => app()->getLocale(), 'announcement' => $announcement->slug]) }}">{{ $announcement->translated('title', $announcement->slug) }}</a>
                                </th>
                                <td>
                                    @if ($announcement->committee)
                                        {{ $announcement->committee->name }}
                                    @elseif ($announcement->sector)
                                        {{ $announcement->sector->name }}
                                    @else
                                        {{ __('announcements.audience_all') }}
                                    @endif
                                </td>
                                <td><span class="a-pill" data-status="{{ $announcement->status }}">{{ __('announcements.status_'.$announcement->status) }}</span></td>
                                <td>{{ $announcement->published_at?->displayed()->isoFormat('D MMMM YYYY') ?? __('office.date_not_recorded') }}</td>
                                <td class="a-table__actions">
                                    <a href="{{ route('admin.announcements.edit', ['locale' => app()->getLocale(), 'announcement' => $announcement->slug]) }}">{{ __('admin.edit') }}</a>
                                    @can('delete', $announcement)
                                        <form class="aw-inline-form" method="POST" action="{{ route('admin.announcements.destroy', ['locale' => app()->getLocale(), 'announcement' => $announcement->slug]) }}"
                                              data-confirm="{{ __('fa.announcement_delete_confirm') }}" data-confirm-title="{{ __('fa.confirm_title') }}" data-confirm-action="{{ __('fa.delete') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="a-linkbutton a-linkbutton--danger">{{ __('fa.delete') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{ $announcements->links() }}
    @endif
@endsection
