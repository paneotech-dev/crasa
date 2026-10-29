{{--
    CRASA's own channels: the network's mark, the handle and the link.

    Used twice. The homepage shows the three channels and nothing else, so
    it loads no third-party code at all. The newsroom adds the Facebook page
    timeline, the only one of the three a platform lets a site show without
    an access token. X and LinkedIn need one, so they stay links.

    The timeline is not in the markup. A script puts the frame in when the
    reader scrolls near it; until then, and with scripting off, the reader
    has a plain link to the page, which stays under the frame either way.

    Every URL comes from site settings through SocialAccounts. The handle is
    read off the same URL, so the two cannot disagree.

    @param  bool  $timeline  Show the Facebook timeline (newsroom only).
--}}
@php
    $urls = $urls ?? \App\Support\SocialAccounts::all();
    $networks = collect($networks ?? ['x', 'facebook', 'linkedin'])
        ->filter(fn ($n) => filled($urls[$n] ?? null))
        ->values();
    $timeline = ($timeline ?? false) && filled($urls['facebook'] ?? null);
@endphp

@if ($networks->isNotEmpty())
    <div @class(['c-socials', 'c-socials--timeline' => $timeline])>
        <ul class="c-socials__list" role="list">
            @foreach ($networks as $network)
                @php $handle = \App\Support\SocialAccounts::handle($network, $urls[$network]); @endphp
                <li>
                    {{-- The accessible name says whose account it is. The
                         mark is decorative and the handle is read out as
                         part of the visible text. --}}
                    <a class="b-social__link" href="{{ $urls[$network] }}"
                       target="_blank" rel="noopener"
                       aria-label="{{ __('blocks.social_account', ['network' => \App\Support\SocialAccounts::label($network)]) }}, {{ $handle }}">
                        <x-social-mark :network="$network" />
                        <span class="c-socials__text">
                            <span class="c-socials__network">{{ \App\Support\SocialAccounts::label($network) }}</span>
                            <span class="c-socials__handle" title="{{ $handle }}">{{ $handle }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($timeline)
            @php
                $frame = 'https://www.facebook.com/plugins/page.php?'.http_build_query([
                    'href' => \App\Support\SocialAccounts::facebookPluginHref($urls['facebook']),
                    'tabs' => 'timeline',
                    'width' => 500,
                    'height' => 560,
                    'small_header' => 'true',
                    'adapt_container_width' => 'true',
                    'hide_cover' => 'true',
                    'show_facepile' => 'false',
                    'locale' => match (app()->getLocale()) { 'fr' => 'fr_FR', 'pt' => 'pt_PT', default => 'en_GB' },
                ]);
            @endphp
            <div class="c-socials__timeline">
                <div class="c-socials__frame" data-fb-timeline
                     data-src="{{ $frame }}"
                     data-title="{{ __('social.timeline_title') }}"></div>
                <p class="c-socials__fallback">
                    <a href="{{ $urls['facebook'] }}" target="_blank" rel="noopener">{{ __('social.timeline_link') }}</a>
                </p>
            </div>

            @once
                @push('scripts')
                    <script src="{{ \App\Support\Assets::url('js/social-timeline.js') }}" defer></script>
                @endpush
            @endonce
        @endif
    </div>
@endif
