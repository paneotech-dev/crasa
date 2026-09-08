{{--
    Links out to CRASA's own accounts. Not an embedded widget: a third-party
    feed script would load tracking on every visitor of a regulatory site.
--}}
@php
    $heading = $block->translate('heading');
    $networks = collect($block->setting('networks', []));
    $urls = [
        'x' => 'https://x.com/Crasa2C',
        'facebook' => 'https://facebook.com/pages/CRASA',
        'linkedin' => 'https://www.linkedin.com/company/crasa',
        'youtube' => null,
    ];
    $labels = ['x' => 'X', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube'];
    $available = $networks->filter(fn ($n) => filled($urls[$n] ?? null));
@endphp

<section class="b b--tight" @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif

        @if ($available->isEmpty())
            @include('blocks._empty', [
                'title' => __('blocks.social_empty_title'),
                'body' => __('blocks.social_empty_body'),
            ])
        @else
            <div class="b-social">
                @foreach ($available as $network)
                    <a class="b-social__link" href="{{ $urls[$network] }}" rel="noopener">{{ $labels[$network] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</section>
