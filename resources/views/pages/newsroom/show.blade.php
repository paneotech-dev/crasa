{{-- Article page. --}}
@extends('layouts.public')

@php
    $title = $item->translate('title');
    $summary = $item->translate('summary');
    $body = $item->translate('body');
    $url = route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $item->slug]);
@endphp

@section('title', $title?->value ?? __('news.title'))
@if ($summary)
    @section('description', $summary->value)
@endif

@section('content')
    <article class="c-section n-article">
        <div class="c-page c-page--reading">
            <nav class="c-breadcrumb" aria-label="{{ __('news.breadcrumb') }}">
                <a href="{{ route('newsroom', ['locale' => app()->getLocale()]) }}">{{ __('news.title') }}</a>
                <span aria-hidden="true">/</span>
                <span>{{ __($item->category->labelKey()) }}</span>
            </nav>

            @if ($title)
                <h1 @if ($title->isFallback()) lang="{{ $title->locale }}" @endif>{{ $title->value }}</h1>
            @endif

            @include('pages.newsroom._meta', ['item' => $item])

            @if ($title?->isFallback())
                <x-fallback-notice />
            @endif

            <figure class="c-article__hero n-hero">
                <img src="{{ $item->hero_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->hero_image_path) : \App\Support\NewsFormat::placeholder($item->category->value) }}"
                     alt="" loading="eager" decoding="async">
            </figure>

            @if ($summary)
                <p class="c-lead">{{ $summary->value }}</p>
            @endif

            @if ($body)
                <div class="c-prose" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                    {{-- Through the renderer, which is what turns the
                         stored <p> and <sup> into a paragraph and a
                         superscript rather than into text a reader has to
                         look past. --}}
                    {!! app(\App\Services\ContentRenderer::class)->render($body->value, 'news:'.$item->id) !!}
                </div>
            @endif

            @if ($item->tags->isNotEmpty())
                <nav class="c-tags" aria-label="{{ __('news.tag') }}">
                    @foreach ($item->tags as $tag)
                        <a class="c-chip" href="{{ route('newsroom', ['locale' => app()->getLocale(), 'tag' => $tag->slug]) }}">{{ $tag->translated('name', $tag->slug) }}</a>
                    @endforeach
                </nav>
            @endif

            {{-- Share links, not share widgets. A third-party button script
                 tracks every reader of a regulatory page whether they click
                 it or not; a plain link tracks nobody. --}}
            <nav class="c-share" aria-label="{{ __('news.share') }}">
                <span class="c-share__label">{{ __('news.share') }}</span>
                <a class="c-chip" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($url) }}">LinkedIn</a>
                <a class="c-chip" rel="noopener" href="https://x.com/intent/tweet?url={{ urlencode($url) }}&amp;text={{ urlencode($title?->value ?? '') }}">X</a>
                <a class="c-chip" href="mailto:?subject={{ rawurlencode($title?->value ?? '') }}&amp;body={{ rawurlencode($url) }}">{{ __('news.share_email') }}</a>
                <label class="n-link">
                    <span>{{ __('news.copy_link') }}</span>
                    <input class="c-input" type="text" readonly value="{{ $url }}" onclick="this.select()">
                </label>
            </nav>

            <nav class="n-pager" aria-label="{{ __('news.title') }}">
                <a class="n-pager__back" href="{{ route('newsroom', ['locale' => app()->getLocale()]) }}">{{ __('news.back') }}</a>
                <span class="n-pager__steps">
                    @if ($newer)
                        <a rel="prev" href="{{ route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $newer->slug]) }}">{{ __('news.newer') }}: {{ \App\Support\NewsFormat::excerpt($newer->translated('title'), 60) }}</a>
                    @endif
                    @if ($older)
                        <a rel="next" href="{{ route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $older->slug]) }}">{{ __('news.older') }}: {{ \App\Support\NewsFormat::excerpt($older->translated('title'), 60) }}</a>
                    @endif
                </span>
            </nav>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <div class="c-section b--tight">
            <div class="c-page">
                <h2>{{ __('news.related') }}</h2>
                <div class="n-grid">
                    @foreach ($related as $item)
                        @include('pages.newsroom._card', ['item' => $item, 'featured' => false])
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endsection
