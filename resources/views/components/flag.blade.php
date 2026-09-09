{{--
    A member state's flag.

    NEVER THE ONLY IDENTIFIER. A flag is not accessible on its own: it cannot
    be read out, it cannot be searched for, and a reader who does not know
    fourteen Southern African flags by sight learns nothing from it. It is
    always beside the country's name, and that name is what carries the
    meaning — so the image is decorative and marked as such.

    Served as a file rather than inlined. Several of these SVGs carry
    clipPath ids, and fourteen inlined copies on one page is fourteen id
    collisions.

    A member state with no flag file renders nothing rather than a broken
    image. The name beside it is unaffected.
--}}
@props(['iso', 'size' => 'sm'])

@php
    $file = 'img/flags/'.strtolower($iso).'.svg';
    $exists = is_file(public_path($file));
@endphp

@if ($exists)
    <img
        class="c-flag c-flag--{{ $size }}"
        src="{{ asset($file) }}"
        alt=""
        width="24"
        height="18"
        loading="lazy"
        decoding="async"
    >
@endif
