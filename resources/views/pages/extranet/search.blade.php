@extends('layouts.extranet')

@section('title', __('office.member_search_title'))
@section('note', __('office.member_search_note'))

@section('crumbs')
    <li><a href="{{ route('extranet.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('extranet.title') }}</a></li>
    <li aria-current="page">{{ __('office.member_search_title') }}</li>
@endsection

@section('content')
    @php($total = $meetings->count() + $folders->count() + $documents->count())

    @if (mb_strlen($term) < 2)
        <div class="a-empty">
            <p class="a-empty__title">{{ __('office.member_search_prompt') }}</p>
            <p class="a-empty__body">{{ ($textSearch ?? false) ? __('d3_docs.search_scope_text') : __('d3_docs.search_scope_names') }}</p>
        </div>
    @elseif ($total === 0)
        <div class="a-empty">
            <p class="a-empty__title">{{ __('office.member_search_none', ['term' => $term]) }}</p>
            <p class="a-empty__body">{{ __('office.member_search_none_body') }}</p>
        </div>
    @else
        <p class="a-pagehead__note" role="status">{{ trans_choice('office.member_search_count', $total, ['count' => $total, 'term' => $term]) }}</p>

        @if ($meetings->isNotEmpty())
            <section class="a-resource e-search">
                <div class="a-resource__head"><h2 class="a-resource__title">{{ __('extranet.calendar') }}</h2></div>
                <ul class="a-results">
                    @foreach ($meetings as $meeting)
                        <li>
                            <a href="{{ route('extranet.calendar', ['locale' => app()->getLocale(), 'month' => $meeting->starts_on?->format('Y-m')]) }}">{{ $meeting->title }}</a>
                            <span class="a-results__meta">{{ $meeting->date_unknown ? __('events.date_not_stated') : $meeting->starts_on->isoFormat('D MMMM YYYY') }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($folders->isNotEmpty())
            <section class="a-resource e-search">
                <div class="a-resource__head"><h2 class="a-resource__title">{{ __('office.member_search_folders') }}</h2></div>
                <ul class="a-results">
                    @foreach ($folders as $folder)
                        <li><a href="{{ route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $folder]) }}">{{ $folder->name }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($documents->isNotEmpty())
            <section class="a-resource e-search">
                <div class="a-resource__head"><h2 class="a-resource__title">{{ __('office.member_search_files') }}</h2></div>
                <ul class="a-results">
                    @foreach ($documents as $document)
                        <li>
                            <a href="{{ route('extranet.document', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ $document->original_filename }}</a>
                            <span class="a-results__meta">{{ $document->created_at?->displayed()->isoFormat('D MMMM YYYY') }}@if (mb_stripos($document->original_filename, $term) === false), {{ __('d3_docs.search_in_text') }}@endif</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
@endsection
