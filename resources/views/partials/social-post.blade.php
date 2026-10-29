{{--
    One of CRASA's posts on X, Facebook or LinkedIn, as a card.

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
    $hasEmbed = ($network === 'x' && filled($post->embed_html)) || filled($post->embed_url);
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
        @if ($network === 'x' && filled($post->embed_html))
            <div class="s-post__x">
                @if ($post->caption)
                    <p class="s-post__behind" aria-hidden="true">{{ $post->caption }}</p>
                @endif
                {{-- Cleaned again at render: only the tweet blockquote survives,
                     whatever the stored value has become since it was saved. --}}
                @php $rendered = \App\Support\SocialEmbed::cleanXHtml((string) $post->embed_html); @endphp
                @if ($rendered){!! $rendered !!}@endif
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

    @unless ($hasEmbed)
        <p class="s-post__open">
            <a href="{{ $post->url }}" target="_blank" rel="noopener">{{ __('fb.open_post', ['network' => $label]) }}</a>
        </p>
    @endunless
</article>
