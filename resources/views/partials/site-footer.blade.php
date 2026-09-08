{{--
    Site footer. Contact, four link columns and the legal row.

    No partner or funder attribution appears here. That copy is supplied by
    CRASA and is never drafted or guessed. See the writing rules in
    CLAUDE.md: the rendered site carries only content CRASA would publish.
--}}
@php
    $nav = fn (string $path) => \App\Support\Navigation::url($path);
@endphp

<footer class="c-footer">
    <div class="c-footer__inner">

        <div class="c-footer__columns">
            <div>
                <a class="c-footer__identity" href="{{ $nav('') }}" aria-label="{{ __('site.home_link') }}">
                    <img class="c-footer__mark" src="{{ asset('img/crasa-logo.svg') }}" alt="" width="38" height="38">
                    <span class="c-footer__acronym">{{ __('site.acronym') }}</span>
                </a>
                <p class="c-footer__blurb">{{ __('footer.blurb') }}</p>
                <address class="c-footer__address" aria-label="{{ __('footer.address_label') }}">
                    <div>{{ __('footer.address_line_1') }}</div>
                    <div>{{ __('footer.address_line_2') }}</div>
                    <div class="c-footer__email"><a href="mailto:{{ __('footer.email') }}">{{ __('footer.email') }}</a></div>
                    <div>{{ __('footer.telephone') }}</div>
                </address>
            </div>

            <nav aria-label="{{ __('footer.about') }}">
                <h2 class="c-footer__heading">{{ __('footer.about') }}</h2>
                <div class="c-footer__links">
                    <a href="{{ $nav('about') }}">{{ __('footer.who_we_are') }}</a>
                    <a href="{{ $nav('about').'#governance' }}">{{ __('footer.governance') }}</a>
                    <a href="{{ $nav('about').'#executive-committee' }}">{{ __('footer.executive_committee') }}</a>
                    <a href="{{ $nav('about').'#secretariat' }}">{{ __('footer.secretariat') }}</a>
                    <a href="{{ $nav('about').'#partnerships' }}">{{ __('footer.partnerships') }}</a>
                </div>
            </nav>

            <nav aria-label="{{ __('footer.publications') }}">
                <h2 class="c-footer__heading">{{ __('footer.publications') }}</h2>
                <div class="c-footer__links">
                    <a href="{{ $nav('publications') }}">{{ __('footer.regulatory_library') }}</a>
                    <a href="{{ $nav('comparator') }}">{{ __('nav.comparator') }}</a>
                    <a href="{{ $nav('committees') }}">{{ __('nav.committees') }}</a>
                    <a href="{{ $nav('members') }}">{{ __('nav.members') }}</a>
                </div>
            </nav>

            <nav aria-label="{{ __('footer.newsroom') }}">
                <h2 class="c-footer__heading">{{ __('footer.newsroom') }}</h2>
                <div class="c-footer__links">
                    <a href="{{ $nav('newsroom') }}">{{ __('nav.newsroom') }}</a>
                    <a href="{{ $nav('events') }}">{{ __('nav.events') }}</a>
                    <a href="{{ $nav('contact') }}">{{ __('nav.contact') }}</a>
                    <a href="{{ $nav(config('navigation.extranet_path')) }}">{{ __('nav.extranet') }}</a>
                </div>
            </nav>

            <nav aria-label="{{ __('footer.follow_us') }}">
                <h2 class="c-footer__heading">{{ __('footer.follow_us') }}</h2>
                <div class="c-footer__links">
                    <a href="https://x.com/Crasa2C" rel="noopener">X</a>
                    <a href="https://facebook.com/pages/CRASA" rel="noopener">Facebook</a>
                    <a href="https://www.linkedin.com/company/crasa" rel="noopener">LinkedIn</a>
                </div>
            </nav>
        </div>

        <div class="c-footer__legal">
            <div>{{ __('footer.copyright', ['year' => now()->year]) }}</div>
            <nav class="c-footer__legal-links" aria-label="{{ __('footer.legal_label') }}">
                <a href="{{ $nav('privacy') }}">{{ __('footer.privacy') }}</a>
                <a href="{{ $nav('terms') }}">{{ __('footer.terms') }}</a>
                <a href="{{ $nav('accessibility') }}">{{ __('footer.accessibility') }}</a>
            </nav>
        </div>
    </div>
</footer>
