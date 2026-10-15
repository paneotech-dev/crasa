{{--
    The empty state every block falls back to. Names what is missing and
    offers the action that fills it. Never blank space.
--}}
@props(['title', 'body', 'actionLabel' => null, 'actionUrl' => null, 'editorOnly' => false])

{{-- editorOnly: the message is guidance for an editor ("Add the copy
     ..."), so only the back office sees it, in preview; a reader sees
     nothing rather than an instruction meant for an editor (15 October
     2026). Reader messages (no results, nothing in the calendar) show to
     everyone. --}}
@if (! $editorOnly || (auth()->user()?->can('enter-back-office') ?? false))
<div class="b__empty">
    <p class="b__empty-title">{{ $title }}</p>
    <p class="b__empty-body">{{ $body }}</p>
    @if ($actionLabel && $actionUrl)
        <a class="c-btn c-btn--secondary" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
    @endif
</div>
@endif
