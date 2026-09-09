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
