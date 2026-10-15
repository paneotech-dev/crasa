{{--
    Links out to CRASA's own accounts. Not an embedded widget: a third-party
    feed script would load tracking on every visitor of a regulatory site.
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
            <div class="b-social">
                @foreach ($available as $network)
                    {{-- The accessible name says whose account it is and
                         where it goes. "LinkedIn" alone tells a screen
                         reader the network and not that this is CRASA's.
                         The mark is decorative; the name carries it. --}}
                    <a class="b-social__link" href="{{ $urls[$network] }}"
                       target="_blank" rel="noopener"
                       aria-label="{{ __('blocks.social_account', ['network' => \App\Support\SocialAccounts::label($network)]) }}">
                        <x-social-mark :network="$network" />
                        <span>{{ \App\Support\SocialAccounts::label($network) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
