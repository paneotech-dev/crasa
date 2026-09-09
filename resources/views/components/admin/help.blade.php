{{--
    Inline help on a control.

    A disclosure, not a hover tooltip. A tooltip does not exist for anyone
    on a touch screen, does not exist for anyone using a keyboard unless it
    is rebuilt from scratch with focus handling, and disappears the moment
    the reader looks away from it to type. A <details> works everywhere,
    works with no JavaScript, and can be left open while the person fills in
    the field it explains.
--}}
@props(['title'])

<details class="a-help">
    <summary>{{ $title }}</summary>
    <div class="a-help__body">{{ $slot }}</div>
</details>
