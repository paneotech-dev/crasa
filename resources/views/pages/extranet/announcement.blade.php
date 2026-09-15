@extends('layouts.extranet')

{{-- One announcement. --}}

@php($title = $announcement->translate('title'))
@php($body = $announcement->translate('body'))

@section('title', $title?->value ?? __('announcements.title'))
@section('note', $announcement->published_at?->isoFormat('D MMMM YYYY') ?? '')

@section('crumbs')
    <li><a href="{{ route('announcements', ['locale' => app()->getLocale()]) }}">{{ __('announcements.title') }}</a></li>
    <li aria-current="page">{{ $title?->value ?? __('announcements.title') }}</li>
@endsection

@section('content')
    <article class="a-card">
        {{-- The record is in English because this reader's language is
             missing. DATA_MODEL section 5: never a blank page, never a
             hidden record, always the notice. --}}
        @if ($title?->isFallback())
            <div class="a-notice">{{ __('site.fallback_body', ['language' => __('site.languages.'.app()->getLocale())]) }}</div>
        @endif

        <dl class="a-panel__meta">
            <div>
                <dt>{{ __('announcements.col_published') }}</dt>
                <dd>{{ $announcement->published_at?->isoFormat('D MMMM YYYY') }}</dd>
            </div>
            <div>
                <dt>{{ __('announcements.audience') }}</dt>
                <dd>{{ $announcement->committee?->name ?? $announcement->sector?->name ?? __('announcements.audience_all') }}</dd>
            </div>
        </dl>

        @if ($body)
            <div class="a-readonly" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                {!! app(\App\Services\ContentRenderer::class)->render($body->value, 'announcement:'.$announcement->id) !!}
            </div>
        @endif
    </article>
@endsection
