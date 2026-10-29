{{--
    The machine translation notice, on French and Portuguese pages.

    A <details> element, open, whose summary is the close control: it
    closes without JavaScript, by keyboard as well as by pointer, and it
    never covers content. a11y.js remembers the close in localStorage, and
    the layout head hides the notice before first paint on later visits.
--}}
@if (in_array(app()->getLocale(), ['fr', 'pt'], true))
    <div class="c-translation" data-translation-notice>
        <div class="c-page">
            <details class="c-translation__box" open>
                <summary class="c-translation__close">
                    <span class="c-sr">{{ __('a11y.translation_dismiss') }}</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6L6 18"></path></svg>
                </summary>
                <div>
                    <p class="c-translation__title">{{ __('a11y.translation_title') }}</p>
                    <p class="c-translation__body">{{ __('a11y.translation_body') }}</p>
                </div>
            </details>
        </div>
    </div>
@endif
