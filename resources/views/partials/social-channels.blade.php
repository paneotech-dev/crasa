{{--
    CRASA's own channels: the network's mark, the handle and the link.

    Used twice. With $timeline (the newsroom) CRASA's published social
    posts follow the channels; partials.newsroom.social is the full
    replacement for that section.

    Every URL comes from site settings through SocialAccounts. The handle is
    read off the same URL, so the two cannot disagree.

    @param  bool  $timeline  Show the published posts (newsroom only).
--}}
@php
    $urls = $urls ?? \App\Support\SocialAccounts::all();
    $networks = collect($networks ?? ['x', 'facebook', 'linkedin'])
        ->filter(fn ($n) => filled($urls[$n] ?? null))
        ->values();
    $timeline = (bool) ($timeline ?? false);
@endphp

@if ($networks->isNotEmpty())
    <div class="c-socials">
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
            {{-- The newsroom: CRASA's published posts under the channels.
                 The Facebook page plugin that stood here drew an empty
                 frame: Facebook sends a reader who is not signed in to its
                 login page instead of the CRASA page. --}}
            @php
                $socialPosts = \Illuminate\Support\Facades\Schema::hasTable('social_posts')
                    ? \App\Models\SocialPost::live()->limit(12)->get()
                    : collect();
            @endphp
            @if ($socialPosts->isNotEmpty())
                @once
                    @push('styles')
                        <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/social.css') }}">
                    @endpush
                @endonce
                <div class="s-posts c-socials__posts">
                    @foreach ($socialPosts as $post)
                        @include('partials.social-post', ['post' => $post])
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endif
