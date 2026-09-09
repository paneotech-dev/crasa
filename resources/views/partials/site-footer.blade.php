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
                    {{-- From site settings, not from the lang files. The
                         Secretariat's telephone number is the same in all
                         three languages, and changing it used to be a
                         deploy in three files. The lang strings are still
                         the fallback. --}}
                    <div>{{ \App\Support\SiteContact::addressLine1() }}</div>
                    <div>{{ \App\Support\SiteContact::addressLine2() }}</div>
                    <div class="c-footer__email"><a href="mailto:{{ \App\Support\SiteContact::email() }}">{{ \App\Support\SiteContact::email() }}</a></div>
                    <div>{{ \App\Support\SiteContact::phone() }}</div>
                </address>

                {{-- CRASA's own accounts, from site settings.

                     The circular treatment has been in blocks.css since the
                     accounts were built and the footer never rendered it:
                     the marks existed only inside a social_feed block,
                     which is on one page. A regulator's site carries them
                     in the footer, and the rule was written for exactly
                     this.

                     An account with no address renders nothing. YouTube is
                     registered and empty until the Secretariat fills it in,
                     and a dead link on a footer is worse than a gap.

                     The brand colours here are the one sanctioned exception
                     to the no-new-colours rule, scoped to the marks and
                     nothing else. docs/OPEN_ITEMS.md. --}}
                @php $accounts = \App\Support\SocialAccounts::all(); @endphp

                @if ($accounts !== [])
                    <div class="c-footer__social">
                        @foreach ($accounts as $network => $url)
                            <a href="{{ $url }}"
                               data-network="{{ $network }}"
                               rel="noopener"
                               aria-label="{{ __('blocks.social_account', ['network' => \App\Support\SocialAccounts::label($network)]) }}">
                                <x-social-mark :network="$network" tone="white" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- The four link columns, from records.
                 They were written into this template until 9 September
                 2026, so renaming one meant a deploy. Screen 14 draws a
                 navigation builder and the contract requires everything to
                 be manageable from the administration.

                 An item whose destination has gone is skipped by
                 Navigation::footer() rather than rendered broken: a deleted
                 page must not take the footer down on every page of the
                 site at once. --}}
            @foreach (\App\Support\Navigation::footer() as $column)
                <nav aria-label="{{ $column['label'] }}">
                    <h2 class="c-footer__heading">{{ $column['label'] }}</h2>
                    <div class="c-footer__links">
                        @foreach ($column['items'] as $link)
                            <a href="{{ $link['url'] }}"
                               @if (\Illuminate\Support\Str::startsWith($link['url'], ['http://', 'https://']) && ! \Illuminate\Support\Str::startsWith($link['url'], config('app.url')))
                                   rel="noopener"
                               @endif>{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                </nav>
            @endforeach
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
