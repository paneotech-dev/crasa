{{--
    Floating page controls, on every public page: "Back to top" and the
    display options, in the bottom right corner, clear of the safe areas.

    Placed after the header in the source, so a keyboard reader meets the
    display options early. "Back to top" is a plain link to the header; it
    is hidden until the reader has scrolled one screen (public/js/float.js)
    and is not drawn without script. Hidden in print (a11y.css).
--}}
<div class="c-float" role="group" aria-label="{{ __('d3_pub.float_label') }}" data-float>
    <a class="c-float__top" href="#top" aria-label="{{ __('chome.back_to_top') }}" hidden data-float-top>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
    </a>
    @include('partials.a11y-toolbar')
</div>
