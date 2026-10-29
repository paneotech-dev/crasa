@extends('layouts.admin')

@php($isNew = ! $post->exists)

@section('title', $isNew ? __('fb.social_add') : __('fb.social_edit'))
@section('note', __('fb.social_add_note'))

@section('crumbs')
    <li><a href="{{ route('admin.social') }}">{{ __('fb.social_title') }}</a></li>
    <li aria-current="page">{{ $isNew ? __('fb.social_add') : __('admin.edit') }}</li>
@endsection

@section('content')
    @unless ($isNew)
        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('fb.col_status') }}</h2>
                <span class="a-card__meta">
                    <span class="a-pill" data-status="{{ $post->status }}">{{ __('fb.status_'.$post->status) }}</span>
                </span>
            </div>

            @unless ($post->isPublished())
                <p class="a-hint">{{ __('fb.publish_note') }}</p>
            @endunless

            <div class="a-form__actions">
                @can(\App\Enums\Permission::PublishContent->value)
                    <form method="POST" action="{{ route($post->isPublished() ? 'admin.social.unpublish' : 'admin.social.publish', ['post' => $post->id]) }}">
                        @csrf
                        @method('PUT')
                        <button class="a-btn {{ $post->isPublished() ? 'a-btn--secondary' : 'a-btn--primary' }}" type="submit">
                            {{ $post->isPublished() ? __('fb.unpublish') : __('fb.publish') }}
                        </button>
                    </form>
                @endcan
                <form method="POST" action="{{ route('admin.social.destroy', ['post' => $post->id]) }}"
                      onsubmit="return confirm('{{ __('fb.delete_confirm') }}')">
                    @csrf
                    @method('DELETE')
                    <button class="a-btn a-btn--danger" type="submit">{{ __('fb.delete') }}</button>
                </form>
            </div>
        </section>

        <section class="a-card">
            <div class="a-card__head"><h2>{{ __('fb.preview') }}</h2></div>
            <div class="fb-preview">
                @include('partials.social-post', ['post' => $post])
            </div>
        </section>
    @endunless

    <form class="a-form a-form--card" method="post"
          action="{{ $isNew ? route('admin.social.store') : route('admin.social.update', ['post' => $post->id]) }}">
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <div @class(['a-field', 'a-field--error' => $errors->has('url')])>
            <label class="a-label" for="url">{{ __('fb.field_url') }} <span class="a-required">{{ __('admin.required') }}</span></label>
            <input class="a-input" type="url" id="url" name="url" maxlength="500" required inputmode="url"
                   aria-describedby="url-help @error('url') url-error @enderror"
                   value="{{ old('url', $post->url) }}">
            <p class="a-hint" id="url-help">{{ __('fb.field_url_help') }}</p>
            @error('url')<p class="a-hint a-hint--error" id="url-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div @class(['a-field', 'a-field--error' => $errors->has('posted_at')])>
            <label class="a-label" for="posted_at">{{ __('fb.field_date') }}</label>
            <input class="a-input" type="date" id="posted_at" name="posted_at" aria-describedby="date-help"
                   value="{{ old('posted_at', $post->posted_at?->toDateString()) }}">
            <p class="a-hint" id="date-help">{{ __('fb.field_date_help') }}</p>
            @error('posted_at')<p class="a-hint a-hint--error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div @class(['a-field', 'a-field--error' => $errors->has('caption')])>
            <label class="a-label" for="caption">{{ __('fb.field_caption') }}</label>
            <textarea class="a-input" id="caption" name="caption" rows="3" maxlength="500" aria-describedby="caption-help">{{ old('caption', $post->caption) }}</textarea>
            <p class="a-hint" id="caption-help">{{ __('fb.field_caption_help') }}</p>
            @error('caption')<p class="a-hint a-hint--error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="a-form__actions">
            <button class="a-btn a-btn--primary" type="submit">{{ __('fb.save') }}</button>
        </div>
    </form>
@endsection
