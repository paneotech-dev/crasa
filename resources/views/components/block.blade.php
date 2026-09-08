{{--
    Renders one page block. Unknown types render nothing rather than
    throwing: a block type removed from the enum should not take a live page
    down with it.
--}}
@props(['block', 'ground' => null])
@php
    $view = $block->type()->view();
@endphp

@if (view()->exists($view))
    @include($view, ['block' => $block, 'ground' => $ground] + \App\Support\BlockData::for($block))
@endif
