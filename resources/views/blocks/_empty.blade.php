{{--
    The empty state every block falls back to. Names what is missing and
    offers the action that fills it. Never blank space.
--}}
@props(['title', 'body', 'actionLabel' => null, 'actionUrl' => null])

<div class="b__empty">
    <p class="b__empty-title">{{ $title }}</p>
    <p class="b__empty-body">{{ $body }}</p>
    @if ($actionLabel && $actionUrl)
        <a class="c-btn c-btn--secondary" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
    @endif
</div>
