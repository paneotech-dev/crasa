{{--
    The reading note an indicator carries, such as subscriptions per 100
    inhabitants running above 100. Driven by the indicator's own flag, so
    every surface that shows the indicator shows the note: the comparator in
    both modes, the member page and the home comparator block.

    Pass one indicator or a collection; the note appears once.
--}}
@props(['indicators'])
@php
    $list = $indicators instanceof \Illuminate\Support\Collection ? $indicators : collect(\Illuminate\Support\Arr::wrap($indicators));
@endphp
@if ($list->filter()->contains(fn ($indicator) => $indicator->canExceedHundred()))
    <p {{ $attributes->merge(['class' => 'c-indicator-note']) }}>{{ __('comparator.note_above_100') }}</p>
@endif
