{{--
    CRASA on social media, the short version: each channel's mark, handle
    and link. Not an embedded widget: a third-party feed on the homepage
    would load another site's code for every visitor. The newsroom has the
    fuller section with the Facebook timeline.
--}}
@php
    /*
     * From site settings, not from this file.
     *
     * Three URLs were hardcoded here and two were wrong -- x.com/Crasa2C,
     * facebook.com/pages/CRASA and linkedin.com/company/crasa, none of
     * which is CRASA's account. A link on a regulator's site pointing at an
     * account that is not theirs is worse than no link, and a value nobody
     * can edit is a value nobody checks.
     */
    $heading = $block->translate('heading');
    $urls = \App\Support\SocialAccounts::all();

    // The block still chooses which of the configured accounts it shows.
    $networks = collect($block->setting('networks', array_keys($urls)));
    $available = $networks->filter(fn ($n) => filled($urls[$n] ?? null))->values();
@endphp

<section class="b b--tight" @if ($block->anchor) id="{{ $block->anchor }}" @endif @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif

        @if ($available->isEmpty())
            @include('blocks._empty', [
                'editorOnly' => true,
                'title' => __('blocks.social_empty_title'),
                'body' => __('blocks.social_empty_body'),
            ])
        @else
            {{-- The channels only: no embedded feed on the homepage, so
                 it loads nothing from another site. The newsroom carries
                 the Facebook timeline. --}}
            @include('partials.social-channels', ['urls' => $urls, 'networks' => $available->all()])
        @endif
    </div>
</section>
