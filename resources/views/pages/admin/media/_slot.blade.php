{{--
    "Used for", the recommended size of each slot, a preview of the chosen
    file with its pixel size, and a warning when it is smaller than the
    slot needs (item 1i, Day 3). Shared by the media upload form and the
    news form's "Upload a photo".

    Without script the recommended sizes are still printed beside each
    choice and the server warns after upload; the preview and the early
    warning are added by public/js/media-slots.js, and stay hidden until
    then.

    $fileInput: the id of the file input this belongs to.
    $selected: the slot chosen by default.
--}}
@php
    $slotName = $slotName ?? 'used_for';
    $selected = old($slotName, $selected ?? 'other');
    $slotsId = 'slots-'.$fileInput;
@endphp
@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/media-slots.js') }}" defer></script>
@endpush

<div class="m3-slots" data-media-slots data-file-input="{{ $fileInput }}"
     data-size-template="{{ __('m3_media.preview_size', ['width' => '{w}', 'height' => '{h}']) }}"
     data-warning-template="{{ __('m3_media.warning', ['width' => '{w}', 'height' => '{h}', 'min_width' => '{mw}', 'min_height' => '{mh}', 'slot' => '{slot}']) }}">
    <fieldset class="m3-slots__choices" aria-describedby="{{ $slotsId }}-hint">
        <legend class="a-label">{{ __('m3_media.used_for') }}</legend>
        <p class="a-hint" id="{{ $slotsId }}-hint">{{ __('m3_media.used_for_hint') }}</p>
        @foreach (\App\Support\ImageSlots::all() as $slot => $min)
            <label class="m3-slots__choice">
                <input type="radio" name="{{ $slotName }}" value="{{ $slot }}" @checked($selected === $slot)
                       data-slot-label="{{ \App\Support\ImageSlots::label($slot) }}"
                       @if ($min) data-min-w="{{ $min['width'] }}" data-min-h="{{ $min['height'] }}" @endif>
                <span>{{ \App\Support\ImageSlots::recommendation($slot) }}</span>
            </label>
        @endforeach
    </fieldset>

    <figure class="m3-slots__preview" data-slot-preview hidden>
        <img src="data:," alt="{{ __('m3_media.preview') }}" data-slot-preview-img>
        <figcaption data-slot-preview-size></figcaption>
    </figure>
    <p class="a-band" role="status" data-slot-warning hidden></p>
</div>

<style>
    .m3-slots { display: grid; gap: var(--s-3); }
    .m3-slots__choices { border: 0; padding: 0; margin: 0; display: grid; gap: var(--s-2); }
    .m3-slots__choice { display: flex; gap: var(--s-2); align-items: baseline; font-size: 14px; }
    .m3-slots__preview { margin: 0; max-width: 360px; }
    .m3-slots__preview img { display: block; max-width: 100%; height: auto; border: 1px solid var(--c-line); border-radius: var(--r-md); }
    .m3-slots__preview figcaption { margin-top: var(--s-1); font-size: 13.5px; color: var(--c-slate); }
    .m3-slots [hidden] { display: none; }
</style>
