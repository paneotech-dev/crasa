{{--
    Display options: text size, line spacing, reading spacing, high
    contrast, underlined links, pause animations.

    One button and the panel it discloses. The floating page controls
    (partials/float-controls) place it in the bottom corner of every
    public page, an icon square at every width that opens upwards
    (a11y.css, .c-float). The choices are classes on
    <html>, kept in localStorage by public/js/a11y.js. The script just
    below applies a stored choice as soon as the header is parsed, so a
    reader with wide spacing does not see the page jump; the layout head
    snippet does the same for the first three options before first paint.
    Without JavaScript the control is not drawn (a11y.css), because nothing
    could run it; browser zoom and the skip link still work.
--}}
@php
    $sizes = ['smaller', 'normal', 'larger', 'largest'];
    $flags = ['spacing', 'dyslexia', 'contrast', 'underline', 'pause'];
@endphp
<script>try{var s=JSON.parse(localStorage.getItem('crasa-a11y')||'{}'),r=document.documentElement;['spacing','dyslexia','contrast','underline','pause'].forEach(function(f){if(s[f]===true)r.classList.add('a11y-'+f);});}catch(e){}</script>
<div class="c-a11y" data-a11y
     data-announce-size="{{ __('a11y.announce_size', ['size' => ':size']) }}"
     data-announce-on="{{ __('a11y.announce_on', ['option' => ':option']) }}"
     data-announce-off="{{ __('a11y.announce_off', ['option' => ':option']) }}"
     data-announce-reset="{{ __('a11y.announce_reset') }}">
    <button type="button" class="c-a11y__toggle"
            aria-expanded="false" aria-controls="a11y-panel" data-a11y-toggle>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <circle cx="16" cy="4" r="1"></circle><path d="m18 19 1-7-6 1"></path><path d="m5 8 3-3 5.5 3-2.36 3.5"></path>
            <path d="M4.24 14.5a5 5 0 0 0 6.88 6"></path><path d="M13.76 17.5a5 5 0 0 0-6.88-6"></path>
        </svg>
        <span class="c-a11y__text">{{ __('a11y.open') }}</span>
    </button>

    <div class="c-a11y__panel" id="a11y-panel" role="group" aria-labelledby="a11y-title" hidden data-a11y-panel>
        <div class="c-a11y__head">
            <p class="c-a11y__title" id="a11y-title">{{ __('a11y.title') }}</p>
            <button type="button" class="c-a11y__close" aria-label="{{ __('a11y.close') }}" data-a11y-close>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12"></path><path d="M18 6L6 18"></path></svg>
            </button>
        </div>

        <div role="group" aria-labelledby="a11y-size-label">
            <p class="c-a11y__label" id="a11y-size-label">{{ __('a11y.text_size') }}</p>
            <div class="c-a11y__row">
                @foreach ($sizes as $size)
                    <button type="button" class="c-a11y__option c-a11y__option--{{ $size }}"
                            aria-pressed="{{ $size === 'normal' ? 'true' : 'false' }}"
                            data-a11y-size="{{ $size }}">{{ __('a11y.size_'.$size) }}</button>
                @endforeach
            </div>
        </div>

        <div class="c-a11y__toggles" role="group" aria-labelledby="a11y-reading-label">
            <p class="c-a11y__label" id="a11y-reading-label">{{ __('a11y.reading') }}</p>
            @foreach ($flags as $flag)
                <button type="button" class="c-a11y__switch" aria-pressed="false" data-a11y-flag="{{ $flag }}"
                        aria-describedby="a11y-hint-{{ $flag }}">
                    <span class="c-a11y__switch-text">
                        <span class="c-a11y__switch-name">{{ __('a11y.'.$flag) }}</span>
                        <span class="c-a11y__hint" id="a11y-hint-{{ $flag }}">{{ __('a11y.'.$flag.'_hint') }}</span>
                    </span>
                </button>
            @endforeach
        </div>

        <button type="button" class="c-a11y__reset" data-a11y-reset>{{ __('a11y.reset') }}</button>

        <p class="c-sr" role="status" aria-live="polite" data-a11y-status></p>
    </div>
</div>
