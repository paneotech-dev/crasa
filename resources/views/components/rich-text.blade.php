{{--
    Stored content, rendered safely. The only way a template prints one.

    Everything that used to be `{!! $value !!}` or `{!! nl2br(e($value)) !!}`
    goes through here, so there is one answer to "what happens to stored
    markup" rather than one answer per template. The three that improvised
    produced three different faults: escaped tags shown as text, raw scraped
    HTML, and a page builder's shortcodes.

    Usage:  <x-rich-text :value="$body?->value" />
            <x-rich-text :value="$meeting->agenda" :record="'meeting:'.$meeting->id" />

    `record` is only for the log, when an unhandled shortcode turns up.
--}}
@props(['value' => null, 'record' => null, 'class' => 'c-prose', 'lang' => null])

@php
    $rendered = app(\App\Services\ContentRenderer::class)->render($value, $record);
@endphp

@if ($rendered->toHtml() !== '')
    <div class="{{ $class }}" @if ($lang) lang="{{ $lang }}" @endif>{!! $rendered !!}</div>
@endif
