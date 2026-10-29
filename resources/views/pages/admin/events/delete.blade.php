@extends('layouts.admin')

@section('title', __('e.admin_delete_title'))
@section('note', $meeting->title)

@section('crumbs')
    <li><a href="{{ route('admin.meetings', ['locale' => app()->getLocale()]) }}">{{ __('e.admin_title') }}</a></li>
    <li aria-current="page">{{ __('e.admin_delete') }}</li>
@endsection

@section('content')
    <section class="a-card" aria-labelledby="del-title">
        <h2 id="del-title">{{ __('e.admin_delete_title') }}</h2>
        <p>{{ __('e.admin_delete_body', ['title' => $meeting->title]) }}</p>
        <p class="a-hint">{{ trans_choice('e.delegates_count', $meeting->delegate_registrations_count, ['count' => $meeting->delegate_registrations_count]) }}</p>
        <form method="POST" action="{{ route('admin.meetings.destroy', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]) }}" class="a-form__actions">
            @csrf
            @method('DELETE')
            <a class="a-btn a-btn--secondary" href="{{ route('admin.meetings', ['locale' => app()->getLocale()]) }}">{{ __('e.admin_cancel') }}</a>
            <button type="submit" class="a-btn a-btn--danger">{{ __('e.admin_delete_confirm') }}</button>
        </form>
    </section>
@endsection
