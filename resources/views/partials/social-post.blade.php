{{--
    One of CRASA's posts on X, Facebook or LinkedIn, as a card.

    X: the blockquote X's oEmbed returned when the post was saved. X's
    widget script turns it into the post; until it does, and if it never
    does, the blockquote is the post's text with a link, so the card is
    never empty.
    Facebook and LinkedIn: the network's own post frame, lazy, on a pale
    ground that carries the caption behind it until the frame paints.
    Without either, the card is the caption and "Open the post".

    The link under every post is the same: the post on its network.
--}}
@php
    $network = $post->network;
    $label = \App\Support\SocialAccounts::label($network);
    $frameHeight = $network === 'linkedin' ? 560 : 520;
@endphp
<article class="u-card s-post" data-network="{{ $network }}">
    <header class="s-post__head">
        <x-social-mark :network="$network" />
        <span class="s-post__on">{{ __('fb.posted_on', ['network' => $label]) }}</span>
        @if ($post->posted_at)
            <time class="s-post__date" datetime="{{ $post->posted_at->toDateString() }}">{{ $post->posted_at->isoFormat('D MMMM YYYY') }}</time>
        @endif
    </header>

    <div class="s-post__body">
        @if ($network === 'x' && filled($post->embed_html))
            <div class="s-post__x">
                @if ($post->caption)
                    <p class="s-post__behind" aria-hidden="true">{{ $post->caption }}</p>
                @endif
                {!! $post->embed_html !!}
            </div>
            @once
                @push('scripts')
                    <script src="https://platform.twitter.com/widgets.js" async charset="utf-8"></script>
                @endpush
            @endonce
        @elseif (filled($post->embed_url))
            <div class="s-post__frame" style="--s-frame-h: {{ $frameHeight }}px">
                @if ($post->caption)
                    <p class="s-post__behind" aria-hidden="true">{{ $post->caption }}</p>
                @endif
                <iframe src="{{ $post->embed_url }}" title="{{ __('fb.frame_title', ['network' => $label]) }}{{ $post->posted_at ? ', '.$post->posted_at->isoFormat('D MMMM YYYY') : '' }}"
                        loading="lazy" height="{{ $frameHeight }}" width="500" frameborder="0" allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        @else
            <p class="s-post__caption">{{ $post->caption ?: __('fb.posted_on', ['network' => $label]) }}</p>
        @endif
    </div>

    <p class="s-post__open">
        <a href="{{ $post->url }}" target="_blank" rel="noopener">{{ __('fb.open_post', ['network' => $label]) }}</a>
    </p>
</article>
