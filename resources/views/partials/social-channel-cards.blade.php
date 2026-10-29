{{--
    CRASA's three channels as one slim row of cards. Shown where posts
    would be when none is published, and under the newsroom's feed.
--}}
@php
    $urls = \App\Support\SocialAccounts::all();
    $channels = collect(['x', 'linkedin', 'facebook'])->filter(fn ($n) => filled($urls[$n] ?? null));
@endphp
@if ($channels->isNotEmpty())
    <ul class="s-channels" role="list">
        @foreach ($channels as $network)
            @php $label = \App\Support\SocialAccounts::label($network); @endphp
            <li class="u-card s-channel">
                <x-social-mark :network="$network" />
                <span class="s-channel__text">
                    <a class="s-channel__link" href="{{ $urls[$network] }}" target="_blank" rel="noopener">{{ __('fb.follow_on', ['network' => $label]) }}</a>
                    <span class="s-channel__handle">{{ \App\Support\SocialAccounts::handle($network, $urls[$network]) }}</span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
