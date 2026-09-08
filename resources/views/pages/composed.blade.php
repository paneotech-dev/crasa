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
    @foreach ($page->visibleBlocks as $block)
        <x-block :block="$block" />
    @endforeach
@endsection
