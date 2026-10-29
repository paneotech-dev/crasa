@extends('layouts.extranet')

{{-- One instrument in the register. --}}

@php($title = $instrument->translate('title'))
@php($summary = $instrument->translate('summary'))

@section('title', $title?->value ?? $instrument->reference_number)
@section('note', $instrument->country?->name ?? '')

@section('crumbs')
    <li><a href="{{ route('register', ['locale' => app()->getLocale()]) }}">{{ __('register.title') }}</a></li>
    <li aria-current="page">{{ $title?->value ?? $instrument->reference_number }}</li>
@endsection

@section('content')
    <article class="a-card">
        @if ($title?->isFallback())
            <div class="a-notice">{{ __('site.fallback_body', ['language' => __('site.languages.'.app()->getLocale())]) }}</div>
        @endif

        <dl class="a-panel__meta">
            <div><dt>{{ __('register.col_country') }}</dt><dd>{{ $instrument->country?->name }}</dd></div>
            <div><dt>{{ __('register.col_sector') }}</dt><dd>{{ $instrument->sector?->name }}</dd></div>
            <div><dt>{{ __('register.col_type') }}</dt><dd>{{ __($instrument->instrument_type->labelKey()) }}</dd></div>
            <div><dt>{{ __('register.col_status') }}</dt><dd>{{ __($instrument->status->labelKey()) }}</dd></div>
            <div><dt>{{ __('register.col_reference') }}</dt><dd>{{ $instrument->reference_number }}</dd></div>
            <div><dt>{{ __('register.col_enacted') }}</dt><dd>{{ $instrument->enacted_on?->isoFormat('D MMMM YYYY') ?? __('office.date_not_recorded') }}</dd></div>
            @if ($instrument->effective_from)
                <div><dt>{{ __('register.col_effective') }}</dt><dd>{{ $instrument->effective_from->isoFormat('D MMMM YYYY') }}</dd></div>
            @endif
        </dl>

        @if ($summary)
            <div class="a-readonly" @if ($summary->isFallback()) lang="{{ $summary->locale }}" @endif>{!! app(\App\Services\ContentRenderer::class)->render($summary->value, 'instrument:'.$instrument->id) !!}</div>
        @endif

        @if ($documents->isNotEmpty())
            <h2>{{ __('register.documents') }}</h2>
            <ul class="a-rows" role="list">
                @foreach ($documents as $document)
                    <li class="a-row">
                        <span class="a-row__grow">
                            <a href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ $document->original_filename }}</a>
                        </span>
                        <span class="a-row__who">{{ $document->readableSize() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </article>
@endsection
