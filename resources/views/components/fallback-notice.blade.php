{{--
    Shown when a record is rendered in English because the requested
    language is missing. The rule is in docs/DATA_MODEL.md, section 5:
    request locale, then English, then hide the field and log.

    Never a blank page, never a hidden record.

    Usage: <x-fallback-notice />
--}}
@props(['requested' => null, 'shown' => \App\Support\Locales::FALLBACK])
@php
    $requested ??= app()->getLocale();
@endphp

@if ($requested !== $shown)
    <div {{ $attributes->merge(['class' => 'c-notice c-notice--info']) }}>
        <svg class="c-notice__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--c-navy)" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"></circle><path d="M12 11v5"></path><path d="M12 8h.01"></path>
        </svg>
        <div>
            <div class="c-notice__title">{{ __('site.fallback_title', ['language' => __('site.languages.'.$requested)]) }}</div>
            <div class="c-notice__body">{{ __('site.fallback_body', ['language' => __('site.languages.'.$requested)]) }}</div>
        </div>
    </div>
@endif
