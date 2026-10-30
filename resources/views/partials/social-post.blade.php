{{--
    One of CRASA's posts on X, Facebook or LinkedIn, as a card.

    Item 1h: before the reader allows social content, every embed is a
    placeholder (see below and public/js/consent.js); nothing is loaded
    from the network until then.

    X: the blockquote X's oEmbed returned when the post was saved. X's
    widget script turns it into the post; until it does, and if it never
    does, the blockquote is the post's text with a link, so the card is
    never empty.
    Facebook and LinkedIn: the network's own post frame, lazy, on a pale
    ground that carries the caption behind it until the frame paints.
    Without either, the card is the caption and "Open the post".

    One line of caption above every post names the network and the date,
    "Posted on LinkedIn, 28 October" (the year is added when it is not
    the current one). The embed carries its own link to the network, so
    there is no second link under it.
--}}
@php
    $network = $post->network;
    $label = \App\Support\SocialAccounts::label($network);
    $frameHeight = $network === 'linkedin' ? 560 : 520;
    $posted = $post->posted_at?->copy()->locale(app()->getLocale());
    $postedDate = $posted?->isoFormat($posted->year === now()->year ? __('d3_polish.tile_date_format') : __('d3_polish.tile_date_format').' YYYY');
    // Cleaned again at render: only the tweet blockquote survives,
    // whatever the stored value has become since it was saved.
    $rendered = $network === 'x' && filled($post->embed_html) ? \App\Support\SocialEmbed::cleanXHtml((string) $post->embed_html) : null;
    $isX = $rendered !== null;
    $hasEmbed = $isX || filled($post->embed_url);
@endphp
<article class="u-card s-post" data-network="{{ $network }}">
    <p class="s-post__head">
        @if ($posted)
            <span>{{ __('d3_polish.posted_caption', ['network' => $label, 'date' => $postedDate]) }}</span>
        @else
            <span>{{ __('fb.posted_on', ['network' => $label]) }}</span>
        @endif
    </p>

    <div class="s-post__body">
        @if ($hasEmbed)
            {{-- Before consent (item 1h): the post's own text from this
                 platform, a note naming the network, a button that loads
                 this one post and the link to it. consent.js swaps in the
                 network's frame or X's widget once the reader allows it. --}}
            <div class="s-post__consent" data-consent-embed="social" data-embed="{{ $isX ? 'x' : 'frame' }}"
                 @unless ($isX)
                     data-embed-src="{{ $post->embed_url }}" data-embed-height="{{ $frameHeight }}"
                     data-embed-title="{{ __('fb.frame_title', ['network' => $label]) }}{{ $post->posted_at ? ', '.$post->posted_at->isoFormat('D MMMM YYYY') : '' }}"
                 @endunless>
                @if ($isX)
                    {{-- The tweet's text as X's oEmbed returned it, with the
                         class X's widget looks for taken off until consent. --}}
                    {!! preg_replace('~^<blockquote class="twitter-tweet"~', '<blockquote class="s-post__quote" data-x-quote', $rendered) !!}
                @elseif ($post->caption)
                    <p class="s-post__caption" data-consent-notice>{{ $post->caption }}</p>
                @endif
                <div class="s-post__gate" data-consent-notice>
                    <p class="s-post__gate-text">{{ __('d3_privacy.post_hosted', ['network' => $label]) }}</p>
                    <p class="s-post__gate-actions">
                        <button type="button" class="u-btn u-btn--secondary" data-consent-show>{{ __('d3_privacy.post_show') }}<span class="c-sr">: {{ $label }}{{ $postedDate ? ', '.$postedDate : '' }}</span></button>
                        <a href="{{ $post->url }}" target="_blank" rel="noopener">{{ __('fb.open_post', ['network' => $label]) }}</a>
                    </p>
                </div>
            </div>
        @else
            <p class="s-post__caption">{{ $post->caption ?: __('fb.posted_on', ['network' => $label]) }}</p>
        @endif
    </div>

    @unless ($hasEmbed)
        <p class="s-post__open">
            <a href="{{ $post->url }}" target="_blank" rel="noopener">{{ __('fb.open_post', ['network' => $label]) }}</a>
        </p>
    @endunless
</article>
