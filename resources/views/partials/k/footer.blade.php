{{--
    Compact footer (Concept 3): identity and one line, the contact card,
    CRASA's own social accounts, then the legal row. Contact details come
    from site settings (SiteContact), the accounts from SocialAccounts.
--}}
@php
    $nav = fn (string $path) => \App\Support\Navigation::url($path);
    // The accessibility statement (g stream): its own localised address
    // where the controller exists, the named route otherwise.
    $a11yUrl = class_exists(\App\Http\Controllers\AccessibilityStatementController::class)
        ? \App\Http\Controllers\AccessibilityStatementController::url()
        : (Route::has('accessibility') ? route('accessibility', ['locale' => app()->getLocale()]) : null);
    $accounts = \App\Support\SocialAccounts::all();
@endphp
<footer class="k-foot" data-surface-tone="dark">
    <div class="k-wrap">
        <div class="k-ft">
            <div class="k-ft__id">
                <a class="k-ft__brand" href="{{ $nav('') }}" aria-label="{{ __('site.home_link') }}">
                    <img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="62" height="62" loading="lazy">
                    <b aria-hidden="true">{{ __('site.full_name') }}</b>
                </a>
                <p>{{ __('chome.footer_blurb') }}</p>
            </div>
            <div class="k-ft__card">
                <h2 class="k-ft__h">{{ __('chome.contact') }}</h2>
                <div class="k-ft__row">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>
                    <address aria-label="{{ __('chome.address') }}">{{ \App\Support\SiteContact::addressLine1() }}<br>{{ \App\Support\SiteContact::addressLine2() }}</address>
                </div>
                <div class="k-ft__row">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    <a href="mailto:{{ \App\Support\SiteContact::email() }}">{{ \App\Support\SiteContact::email() }}</a>
                </div>
                <div class="k-ft__row">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', \App\Support\SiteContact::phone()) }}">{{ \App\Support\SiteContact::phone() }}</a>
                </div>
            </div>
            <div>
                <h2 class="k-ft__h">{{ __('chome.follow') }}</h2>
                @if ($accounts !== [])
                    <div class="k-fsoc">
                        @foreach ($accounts as $network => $url)
                            <a href="{{ $url }}" data-network="{{ $network }}" rel="noopener" target="_blank"
                               aria-label="{{ __('blocks.social_account', ['network' => \App\Support\SocialAccounts::label($network)]) }}">
                                <x-social-mark :network="$network" tone="white" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="k-fbar">
            <span>{{ __('footer.copyright', ['year' => now()->year]) }}</span>
            <nav aria-label="{{ __('footer.legal_label') }}">
                <a href="{{ $nav('contact') }}">{{ __('chome.contact_us') }}</a>
                <a href="{{ $nav('terms') }}">{{ __('footer.terms') }}</a>
                <a href="{{ $nav('privacy') }}">{{ __('footer.privacy') }}</a>
                {{-- Day 3: privacy. Reopens the cookie settings; without
                     scripting it leads to the cookies section of the notice. --}}
                <a href="{{ $nav('privacy') }}" data-consent-open aria-haspopup="dialog">{{ __('d3_privacy.footer_link') }}</a>
                @if ($a11yUrl)
                    <a href="{{ $a11yUrl }}">{{ __('footer.accessibility') }}</a>
                @endif
                <a href="#top">{{ __('chome.back_to_top') }}</a>
            </nav>
        </div>
    </div>
</footer>
