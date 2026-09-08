{{--
    Holding page. Replaced by the composed homepage in the block library
    task, which reads its blocks from a page record rather than this file.

    The copy is from the approved homepage screen. Nothing on any rendered
    page describes the project, the contract or the programme.
--}}
@extends('layouts.public')

@section('title', __('nav.home'))
@section('description', __('home.body'))

@section('content')
    <div class="c-section">
        <div class="c-page c-page--reading c-stack">
            <h1>{{ __('home.heading') }}</h1>
            <p class="c-lead">{{ __('home.body') }}</p>
        </div>
    </div>
@endsection
