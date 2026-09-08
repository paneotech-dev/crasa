{{-- Article page. --}}
@extends('layouts.public')

@php
    $title = $item->translate('title');
    $summary = $item->translate('summary');
    $body = $item->translate('body');
    $slot = $item->fallbackImageSlot();
    $url = route('newsroom.show', ['locale' => app()->getLocale(), 'slug' => $item->slug]);
@endphp

@section('title', $title?->value ?? __('news.title'))
@if ($summary)
    @section('description', $summary->value)
@endif

@section('content')
    <article class="c-section">
        <div class="c-page c-page--reading">
            <nav class="c-breadcrumb" aria-label="{{ __('news.breadcrumb') }}">
                <a href="{{ route('newsroom', ['locale' => app()->getLocale()]) }}">{{ __('news.title') }}</a>
                <span aria-hidden="true">/</span>
                <span>{{ __($item->category->labelKey()) }}</span>
            </nav>

            @if ($title)
                <h1 @if ($title->isFallback()) lang="{{ $title->locale }}" @endif>{{ $title->value }}</h1>
            @endif

            <p class="c-article__meta">
                <span>{{ $item->published_at?->translatedFormat('j F Y') }}</span>
                @if ($item->author_name)<span>{{ $item->author_name }}</span>@endif
                @if ($item->sector)<span>{{ $item->sector->name }}</span>@endif
            </p>

            @if ($title?->isFallback())
                <x-fallback-notice />
            @endif

            <figure class="c-article__hero">
                @if ($item->hero_image_path)
                    <img class="c-img" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->hero_image_path) }}"
                         alt="" loading="eager" decoding="async">
                @else
                    <x-responsive-image :name="$slot" :alt="__('images.'.$slot)" sizes="(max-width: 1240px) 100vw, 1240px" loading="eager" />
                    <figcaption class="c-article__caption">{{ __('images.illustrative') }}</figcaption>
                @endif
            </figure>

            @if ($summary)
                <p class="c-lead">{{ $summary->value }}</p>
            @endif

            @if ($body)
                <div class="c-prose" @if ($body->isFallback()) lang="{{ $body->locale }}" @endif>
                    {!! nl2br(e($body->value)) !!}
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
            </nav>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <div class="c-section b--tight">
            <div class="c-page">
                <h2>{{ __('news.related') }}</h2>
                <div class="b-cards">
                    @foreach ($related as $item)
                        @include('pages.newsroom._card', ['item' => $item])
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endsection
