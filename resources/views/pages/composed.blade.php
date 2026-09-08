{{--
    A page composed from its block records. There is no page-specific markup
    here by design: the arrangement is data, so an editor changes it from the
    back office rather than through a deploy.
--}}
@extends('layouts.public')

@php
    $title = $page->translate('title');
    $description = $page->translate('meta_description');
@endphp

@section('title', $title?->value ?? __('nav.home'))
@if ($description)
    @section('description', $description->value)
@endif

@section('content')
    @unless ($page->blocksRenderH1())
        {{-- The hero carries the h1 where there is one. Without a hero the
             page needs its own, or its headings start at level 2 and the
             document outline has no title. --}}
        <div class="c-section c-section--title">
            <div class="c-page">
                <h1 @if ($title?->isFallback()) lang="{{ $title->locale }}" @endif>{{ $title?->value ?? '' }}</h1>
            </div>
        </div>
    @endunless

    @foreach ($page->visibleBlocks as $block)
        <x-block :block="$block" />
    @endforeach
@endsection
