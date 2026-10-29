@extends('layouts.admin')
@section('title', __('fa.rules_title'))
@section('note', __('fa.rules_note'))

@section('crumbs')
    <li><a href="{{ route('admin.approvals', ['locale' => app()->getLocale()]) }}">{{ __('fa.inbox_title') }}</a></li>
    <li aria-current="page">{{ __('fa.rules_title') }}</li>
@endsection

@section('content')
    <form class="a-form a-panel" method="POST" action="{{ route('admin.approvals.rules.update', ['locale' => app()->getLocale()]) }}">
        @csrf @method('PUT')
        @foreach ($types as $type)
            <fieldset class="a-fieldset aw-rule">
                <legend class="a-label">{{ __('fa.types_'.$type) }}</legend>
                @foreach (['one', 'none'] as $mode)
                    <label class="a-check">
                        <input type="radio" name="mode[{{ $type }}]" value="{{ $mode }}" @checked(($rules[$type] ?? 'one') === $mode)>
                        <span class="a-check__label">{{ __('fa.rules_mode_'.$mode) }}</span>
                    </label>
                @endforeach
            </fieldset>
        @endforeach
        <div class="a-form__actions">
            <button type="submit" class="a-btn a-btn--primary">{{ __('fa.rules_save') }}</button>
        </div>
    </form>
@endsection
