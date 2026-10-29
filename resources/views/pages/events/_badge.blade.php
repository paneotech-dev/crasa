{{-- The committee code on its colour. The code is the text, so the colour is never the only signal. --}}
@php($g = $group ?? $meeting->group())
<span class="e-badge" style="--e-c: {{ $g->colour }}" title="{{ $g->label() }}"><span aria-hidden="true">{{ $g->code }}</span><span class="c-sr">{{ $g->label() }}</span></span>
