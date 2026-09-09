@extends('layouts.admin')

@section('title', __('admin.search_title'))
@section('note', __('admin.search_note'))

@section('crumbs')
    <li aria-current="page">{{ __('admin.search_title') }}</li>
@endsection

@section('content')
    @if ($term === '')
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('admin.search_prompt_title') }}</div>
                <div class="a-empty__body">{{ __('admin.search_prompt_body') }}</div>
            </div>
        </div>
    @elseif ($groups === [])
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('admin.search_empty_title', ['term' => $term]) }}</div>
                <div class="a-empty__body">{{ __('admin.search_empty_body') }}</div>
            </div>
        </div>
    @else
        {{-- Grouped by type. "Botswana" is a member state and an authority
             and a folder, and a flat list of eighteen rows makes the reader
             do the grouping themselves. --}}
        @foreach ($groups as $group)
            <section class="a-card">
                <div class="a-card__head">
                    <h2>{{ $group['label'] }}</h2>
                </div>

                <ul class="a-results" role="list">
                    @foreach ($group['results'] as $result)
                        <li>
                            <a href="{{ $result['url'] }}">{{ $result['title'] }}</a>
                            @if ($result['meta'] !== '')
                                <span class="a-results__meta">{{ $result['meta'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
@endsection
