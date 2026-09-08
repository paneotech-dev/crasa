{{--
    An image slot with nothing in it. The designed absence: says what the
    slot is for and at what ratio, rather than a broken img or a grey box.
--}}
@props(['note', 'ratio' => '16:9'])

<div class="b__slot" style="aspect-ratio: {{ str_replace(':', '/', $ratio) }}" role="img" aria-label="{{ $note }}">
    <div>
        <p class="b__slot-note">{{ $note }}</p>
        <p class="b__slot-ratio">{{ $ratio }}</p>
    </div>
</div>
