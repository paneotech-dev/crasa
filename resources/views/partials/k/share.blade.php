{{--
    Share button for a content page (news article, publication, event,
    project, thematic area), under the title. The system share sheet where
    there is one, otherwise the address is copied and "Link copied" is
    announced (public/js/chrome.js). Hidden without JavaScript.
--}}
<div class="k-share-row">
    <button class="k-share" type="button" data-k-share data-copied="{{ __('chome.share_copied') }}" aria-label="{{ __('chome.share_label') }}">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
        <span>{{ __('chome.share') }}</span>
    </button>
    <span class="c-sr" aria-live="polite" data-k-share-live></span>
</div>
