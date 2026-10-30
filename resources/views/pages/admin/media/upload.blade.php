@extends('layouts.admin')

@section('title', __('admin.upload'))
@section('note', __('admin.upload_note'))

@section('crumbs')
    <li><a href="{{ route('admin.media', ['locale' => app()->getLocale()]) }}">{{ __('admin.media_title') }}</a></li>
    <li aria-current="page">{{ __('admin.upload') }}</li>
@endsection

@section('content')
    <section class="a-panel">
        <form class="a-form" method="POST" action="{{ route('admin.media.store', ['locale' => app()->getLocale()]) }}" enctype="multipart/form-data">
            @csrf

            <div class="a-field">
                <label class="a-label" for="file">{{ __('admin.upload_field') }}</label>
                <input class="a-input" type="file" id="file" name="file" required
                       accept="{{ implode(',', \App\Services\MediaLibrary::ACCEPTED) }}">
                <span class="a-hint">{{ __('admin.upload_hint', ['size' => (int) (\App\Services\MediaLibrary::MAX_BYTES / 1048576)]) }}</span>
            </div>

            @include('pages.admin.media._slot', ['fileInput' => 'file', 'selected' => 'other'])

            <div class="a-rights">
                <strong>{{ __('admin.upload_exif_title') }}</strong>
                {{ __('admin.upload_exif_body') }}
            </div>

            <div class="a-form__actions">
                <button type="submit" class="a-btn a-btn--primary">{{ __('admin.upload_submit') }}</button>
                <a href="{{ route('admin.media', ['locale' => app()->getLocale()]) }}">{{ __('admin.cancel') }}</a>
            </div>
        </form>
    </section>
@endsection
