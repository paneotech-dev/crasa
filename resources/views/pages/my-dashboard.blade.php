@extends('layouts.public')

{{--
    A viewer's dashboard (F4): the latest published items and the
    comparator. Readers with a back office or member area never reach this
    page; they are sent to their own dashboard.
--}}

@php
    $user = auth()->user();
    $dash = \App\Support\RoleDashboard::for($user);
    $live = collect($dash['blocks'])->firstWhere('key', 'live');
    $comparator = collect($dash['blocks'])->firstWhere('key', 'comparator');
@endphp

@section('title', $dash['heading'])

@section('content')
    <section class="u-hero" aria-labelledby="my-dash-title">
        <div class="u-container">
            <p class="u-eyebrow">{{ $user->name }}, {{ $user->roleLabel() }}</p>
            <h1 class="u-h1" id="my-dash-title">{{ $dash['heading'] }}</h1>
            <p class="u-lead">{{ $dash['intro'] }}</p>
        </div>
    </section>

    <section class="u-section" aria-labelledby="my-dash-live">
        <div class="u-container u-main-side">
            <div>
                <h2 class="u-h2" id="my-dash-live">{{ $live['title'] }}</h2>
                @if ($live['rows'] === [])
                    <p class="u-text">{{ $live['empty'] }}</p>
                @else
                    <ul class="u-cols-2 my-dash__list" role="list">
                        @foreach ($live['rows'] as $row)
                            <li class="u-card">
                                <div class="u-card__body">
                                    <h3 class="u-card__title"><a href="{{ $row['url'] }}">{{ $row['label'] }}</a></h3>
                                    <p class="u-card__meta">{{ $row['meta'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <aside class="u-panel u-panel--tint" aria-labelledby="my-dash-compare">
                <h2 class="u-h3" id="my-dash-compare">{{ $comparator['title'] }}</h2>
                <p class="u-text">{{ $comparator['empty'] }}</p>
                <p><a class="u-btn u-btn--primary" href="{{ route('comparator') }}">{{ __('fb.dash_open_comparator') }}</a></p>
                <p><a class="u-btn u-btn--secondary" href="{{ route('account') }}">{{ __('account.title') }}</a></p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="u-btn u-btn--ghost">{{ __('admin.sign_out') }}</button>
                </form>
            </aside>
        </div>
    </section>
@endsection

